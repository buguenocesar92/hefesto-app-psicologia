<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultorio;
use App\Services\Auth\ProveedorOidcAuthentik;
use App\Services\Auth\VerificadorIdToken;
use App\Services\Permisos\CapacidadesDeUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * El ingreso y la salida.
 *
 * POR QUÉ NO HAY UN FORMULARIO CON USUARIO Y CONTRASEÑA
 * -----------------------------------------------------
 * Porque no hay contraseñas en esta aplicación. Quien entra llega desde
 * Authentik, y de ahí salen las tres cosas que importan: quién es la persona,
 * qué rol tiene y a qué consultorio pertenece.
 *
 * El flujo es: `entrar` manda a Authentik, Authentik devuelve un token, `callback`
 * lo verifica y abre la sesión.
 *
 * POR QUÉ EL TOKEN VIAJA EN EL FRAGMENTO Y NO EN LA CONSULTA
 * ------------------------------------------------------
 * Porque el fragmento no viaja al servidor en la primera petición. El token no
 * queda en los registros del servidor web ni en el historial del proxy, que es
 * justo donde quedan las URLs de la consulta.
 *
 * LO QUE EL ROL HACE EN CADA INGRESO
 * ----------------------------------
 * Se relee de los equipos de Authentik y se vuelve a escribir. Quitar un equipo
 * en Authentik surte efecto en la sesión siguiente, no en un plazo de días.
 *
 * Ver docs/03.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly ProveedorOidcAuthentik $proveedor,
        private readonly VerificadorIdToken $verificador,
        private readonly CapacidadesDeUsuario $capacidades,
    ) {
    }

    /**
     * Manda a la pantalla de ingreso del proveedor de identidad.
     */
    public function entrar(): JsonResponse
    {
        return response()->json([
            'url' => $this->proveedor->urlDeIngreso(),
        ]);
    }

    /**
     * Recibe el token, lo verifica y abre la sesión.
     */
    public function callback(Request $peticion): JsonResponse
    {
        $idToken = $peticion->query('id_token');

        if (! is_string($idToken) || $idToken === '') {
            return response()->json([
                'mensaje' => 'No llegó el token de ingreso. Vuelva a entrar.',
            ], 400);
        }

        try {
            $claims = $this->verificador->verificar($idToken);
            $usuario = $this->proveedor->resolverUsuario($claims);
        } catch (RuntimeException $e) {
            // El motivo va al registro del sistema, que va sin datos
            // personales, y a la persona se le dice que entre de nuevo.
            Log::warning('Ingreso rechazado.', ['error' => $e->getMessage()]);

            return response()->json([
                'mensaje' => 'No se pudo completar el ingreso. Vuelva a intentarlo o comuníquese con la dirección del consultorio.',
            ], 401);
        }

        Auth::login($usuario, remember: false);

        $peticion->session()->regenerate();

        return $this->yo($peticion);
    }

    /**
     * Cierra la sesión.
     */
    public function salir(Request $peticion): JsonResponse
    {
        Auth::logout();

        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return response()->json([
            'mensaje' => 'Sesión cerrada.',
        ]);
    }

    /**
     * Quién es la persona que está adentro ahora.
     *
     * Esta es la respuesta de la que la interfaz saca todo lo que necesita saber
     * sobre el rol, incluida la explicación de por qué no ve ciertas cosas. Por
     * eso trae `explicacion` y no solo `rol`.
     */
    public function yo(Request $peticion): JsonResponse
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            return response()->json([
                'mensaje' => 'La sesión no es válida o venció. Entre de nuevo.',
            ], 401);
        }

        return response()->json([
            'datos' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'rol' => $usuario->rol?->value,
                'rol_etiqueta' => $usuario->rol?->etiqueta(),
                'es_tratante' => $usuario->esTratante(),
                'profesional_id' => $usuario->profesional_id,
                'consultorio' => Consultorio::query()
                    ->whereKey($usuario->tenant_id)
                    ->first(['id', 'nombre', 'slug']),
                've_notas' => $this->capacidades->puedeAbrirContenidoDeSesiones($usuario),
                'explicacion' => $this->capacidades->explicacionDelRol($usuario),
                'acceso_al_contenido' => $usuario->rol?->accesoAlContenidoDeSesion(),
            ],
        ]);
    }
}