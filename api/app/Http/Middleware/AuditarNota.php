<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AccionNota;
use App\Models\NotaSesion;
use App\Models\Usuario;
use App\Services\Auditoria\RegistroDeAccesos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La puerta del contenido de las sesiones.
 *
 * QUÉ HACE
 * --------
 * Anota en la bitácora CADA intento de abrir el contenido de una sesión, y
 * corta con 404 a quien no tiene permiso.
 *
 * POR QUÉ ES EL MIDDLEWARE MÁS IMPORTANTE DE ESTA APLICACIÓN
 * ----------------------------------------------------------
 * Porque el contenido de una sesión de terapia es el dato más sensible que hay
 * acá, y la diferencia entre esta aplicación y una agenda con una caja de
 * texto es qué pasa cuando alguien que no corresponde lo pide.
 *
 * Lo que hay abajo, en la lista de rutas marcadas, es exactamente el conjunto
 * de operaciones que tocan ese contenido. Agendar una cita, ver la ficha de un
 * paciente o registrar un pago NO lo tocan, y por eso no están marcadas: un
 * administrativo hace esas tres cosas todos los días, y no por eso debería
 * dejar de poder trabajar.
 *
 * POR QUÉ 404 Y NO 403
 * -------------------
 * Un 403 confirmaría que la nota existe. En una clínica de salud mental, decir
 * "sí hay una sesión con esa persona" a quien no corresponde ya es filtrar. El
 * 404 no dice si existe o no, y con eso no hay nada que filtrar. Ver docs/03.
 *
 * CÓMO SE SABE QUÉ ACCIÓN ES
 * -------------------------
 * Por el verbo y el camino. Un `GET` es intentar abrir; un `PUT` es intentar
 * editar; un `POST` sobre `/cerrar` es intentar cerrar; un `POST` sobre
 * `/correcciones` es intentar corregir. No hay que pasar la acción a mano en
 * cada ruta, y por lo tanto no se puede olvidar en una.
 */
class AuditarNota
{
    public function __construct(
        private readonly RegistroDeAccesos $registro,
    ) {
    }

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if (! $usuario instanceof Usuario) {
            return $this->noEncontrado();
        }

        $accion = $this->accionDe($peticion);
        $notaId = $peticion->route('nota');
        $nota = null;

        // Para crear la nota todavía no existe el identificador: el permiso se
        // comprueba contra la cita, que es lo que se está creando.
        if (is_string($notaId) && $notaId !== '') {
            $nota = NotaSesion::query()->find($notaId);

            if ($nota === null) {
                $this->registro->denegado(
                    $usuario,
                    'nota_sesion',
                    $accion->value,
                    null,
                    'La nota no existe en este consultorio, o no le corresponde.',
                );

                return $this->noEncontrado();
            }

            if (! $usuario->puedeSobreNota($nota, $accion)) {
                $this->registro->denegado(
                    $usuario,
                    'nota_sesion',
                    $accion->value,
                    $nota->id,
                    $usuario->motivoDeFaltaDeAcceso($nota, $accion),
                );

                return $this->noEncontrado();
            }

            $this->registro->permitido(
                $usuario,
                'nota_sesion',
                $accion->value,
                $nota->id,
            );

            return $siguiente($peticion);
        }

        // Listado o creación: el permiso se decide por el rol y por la
        // relación con el profesional.
        if ($usuario->noPuedeOperarSobreNotas($accion)) {
            $this->registro->denegado(
                $usuario,
                'nota_sesion',
                $accion->value,
                null,
                $usuario->motivoDeFaltaDeAcceso(null, $accion),
            );

            return $this->noEncontrado();
        }

        $this->registro->permitido($usuario, 'nota_sesion', $accion->value);

        return $siguiente($peticion);
    }

    /**
     * Traduce verbo y camino en una acción del dominio.
     */
    private function accionDe(Request $peticion): AccionNota
    {
        $verbo = $peticion->method();
        $ruta = $peticion->path();

        if ($verbo === 'POST' && str_ends_with($ruta, '/cerrar')) {
            return AccionNota::CerrarNota;
        }

        if ($verbo === 'POST' && str_ends_with($ruta, '/correcciones')) {
            return AccionNota::CorregirNota;
        }

        if (str_ends_with($ruta, '/revisiones')) {
            return AccionNota::VerRevisiones;
        }

        return match ($verbo) {
            'POST' => AccionNota::CrearNota,
            'PUT', 'PATCH' => AccionNota::EditarNota,
            default => AccionNota::VerNota,
        };
    }

    /**
     * El 404 que no confirma nada.
     *
     * El cuerpo dice "No encontrado." y nada más. Un cuerpo que explicara el
     * motivo volvería a confirmar que el recurso existe.
     */
    private function noEncontrado(): Response
    {
        return response()->json([
            'mensaje' => 'No encontrado.',
        ], 404);
    }
}