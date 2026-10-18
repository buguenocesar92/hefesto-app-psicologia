<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\RolClinico;
use App\Models\Consultorio;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Traduce los equipos de Authentik en un rol y un consultorio.
 *
 * EL ROL NO SE LEE DE LA BASE, SE LEE DEL PROVEEDOR
 * -------------------------------------------------
 * En cada ingreso se leen los equipos que trae el token y se vuelve a escribir
 * el rol en `usuarios.rol`.
 *
 * Por qué releerlo en cada ingreso y no guardarlo para siempre: si en Authentik
 * le quitan un equipo a alguien, el efecto tiene que verse en la sesión
 * siguiente. Si el rol quedara congelado en la base el día del primer ingreso,
 * quitar el equipo no haría nada hasta que alguien corriera un comando, y
 * mientras tanto esa persona conservaría el acceso.
 *
 * EL EQUIPO DEL CONSULTORIO
 * -------------------------
 * El equipo con prefijo `consultorio-` declara a qué consultorio pertenece la
 * persona. Su contenido es el identificador o el slug del consultorio.
 *
 * Sin ese equipo, la persona no tiene consultorio, y sin consultorio no hay
 * filas que le pertenezcan: el ingreso falla con un mensaje claro. Es mejor
 * que dejarlo entrar a una aplicación donde todo le aparece vacío.
 *
 * Ver docs/03.
 */
class ProveedorOidcAuthentik
{
    public function __construct(
        private readonly DescubridorOidc $descubridor,
    ) {
    }

    /**
     * Resuelve la identidad del token a un usuario de esta base.
     *
     * EL ORDEN DE ESTOS PASOS NO SE PUEDE CAMBIAR
     * ------------------------------------------
     * `usuarios` tiene Row Level Security, así que sin `app.tenant_id` fijado
     * no se puede leer ninguna fila. Por eso el consultorio se resuelve PRIMERO
     * —desde `consultorios`, que es la única tabla sin RLS, y por eso desde
     * los equipos del token— y solo después se abre la transacción con el
     * consultorio ya fijado.
     *
     * Al revés, el ingreso no tendría por dónde empezar.
     *
     * @param  array<string, mixed>  $claims
     *
     * @throws RuntimeException si el token no declara un consultorio
     */
    public function resolverUsuario(array $claims): Usuario
    {
        $sub = $claims['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw new RuntimeException('El token no trae identificador de usuario.');
        }

        $equipos = $this->equiposDel($claims);

        // Primero el consultorio, porque sin él no se puede leer nada.
        $consultorio = $this->consultorioDe($equipos);
        $rol = $this->rolDe($equipos);

        DB::connection('pgsql')->beginTransaction();

        try {
            ContextoTenant::establecer($consultorio->id, 'pgsql');

            $usuario = $this->sincronizarUsuario($consultorio, $rol, $sub, $claims);
        } catch (\Throwable $e) {
            DB::connection('pgsql')->rollBack();

            throw $e;
        } finally {
            ContextoTenant::olvidar();
        }

        DB::connection('pgsql')->commit();

        return $usuario;
    }

    /**
     * Crea o actualiza la fila del usuario.
     *
     * Se llama con el contexto ya puesto, así que el alcance y la Row Level
     * Security dejan pasar la consulta.
     *
     * @param  array<string, mixed>  $claims
     */
    private function sincronizarUsuario(
        Consultorio $consultorio,
        RolClinico $rol,
        string $sub,
        array $claims,
    ): Usuario {
        $email = (string) ($claims['email'] ?? '');
        $nombre = (string) ($claims['name'] ?? $claims['preferred_username'] ?? $email);

        $usuario = Usuario::query()->where('sub', $sub)->first();

        if ($usuario === null) {
            return Usuario::query()->create([
                'tenant_id' => $consultorio->id,
                'sub' => $sub,
                'email' => $email,
                'nombre' => $nombre !== '' ? $nombre : $email,
                'rol' => $rol->value,
                'activo' => true,
                'ultimo_ingreso_en' => now(),
                'rol_actualizado_en' => now(),
            ]);
        }

        // El rol se relee SIEMPRE, en cada ingreso. Ver la nota de esta clase.
        $usuario->forceFill([
            'email' => $email,
            'nombre' => $nombre !== '' ? $nombre : $email,
            'rol' => $rol->value,
            'rol_actualizado_en' => now(),
            'ultimo_ingreso_en' => now(),
            'activo' => true,
        ])->save();

        return $usuario->refresh();
    }

    /**
     * La URL a la que se manda a la persona para entrar.
     */
    public function urlDeIngreso(): string
    {
        $url = $this->descubridor->urlDeAutorizacion();

        $parametros = http_build_query([
            'client_id' => config('authentik.client_id'),
            'redirect_uri' => config('authentik.redirect_uri'),
            'response_type' => 'id_token',
            'scope' => 'openid email profile',
            'response_mode' => 'fragment',
        ]);

        return $url . '?' . $parametros;
    }

    /**
     * Los equipos del token, en mayúsculas.
     *
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    private function equiposDel(array $claims): array
    {
        $claim = (string) config('authentik.claim_grupos');
        $valores = $claims[$claim] ?? [];

        if (! is_array($valores)) {
            return [];
        }

        // La comparación es insensible a mayúsculas porque Authentik entrega
        // los equipos tal como se escribieron en su consola, y nadie los
        // escribe siempre igual.
        return array_values(array_filter(array_map(
            static fn ($valor): string => is_string($valor) ? mb_strtoupper($valor) : '',
            $valores,
        )));
    }

    /**
     * El consultorio que declara el equipo con prefijo.
     *
     * @param  list<string>  $equipos
     */
    private function consultorioDe(array $equipos): Consultorio
    {
        $prefijo = mb_strtoupper((string) config('authentik.prefijo_equipo_consultorio'));

        foreach ($equipos as $equipo) {
            if (! str_starts_with($equipo, $prefijo)) {
                continue;
            }

            $slug = mb_strtolower(mb_substr($equipo, mb_strlen($prefijo)));

            if ($slug === '') {
                continue;
            }

            // Se busca por identificador o por slug: son las dos formas en que
            // un equipo puede nombrar un consultorio, y el software no debería
            // obligar a elegir una.
            $consultorio = Consultorio::query()
                ->where('slug', $slug)
                ->orWhere('id', $slug)
                ->where('activo', true)
                ->first();

            if ($consultorio !== null) {
                return $consultorio;
            }
        }

        throw new RuntimeException(
            'Su cuenta no está asociada a ningún consultorio. Pídale a la dirección del '
            . 'consultorio que agregue su equipo con el prefijo configurado.'
        );
    }

    /**
     * El rol que declara el equipo.
     *
     * @param  list<string>  $equipos
     */
    private function rolDe(array $equipos): RolClinico
    {
        $grupo = (string) config('authentik.grupo_por_consultorio');

        $porEquipo = [];

        foreach ([RolClinico::Profesional, RolClinico::Administrativo, RolClinico::Administrador] as $rol) {
            $porEquipo[mb_strtoupper($rol->equipoAuthentik($grupo))] = $rol;
        }

        foreach ($equipos as $equipo) {
            if (isset($porEquipo[$equipo])) {
                return $porEquipo[$equipo];
            }
        }

        throw new RuntimeException(
            'Su cuenta no tiene un rol asignado en el proveedor de identidad. Pídale a la '
            . 'direccion del consultorio que la agregue a un equipo de rol.'
        );
    }
}