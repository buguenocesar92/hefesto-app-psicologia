<?php

declare(strict_types=1);

namespace App\Services\Auditoria;

use App\Enums\ResultadoAcceso;
use App\Models\Acceso;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;

/**
 * Escribe en la bitácora de accesos.
 *
 * POR QUÉ UNA CONEXIÓN APARTE
 * ---------------------------
 * Porque una petición a la que se le deniega el permiso termina revertida. Si
 * el registro del intento se escribiera en la conexión de trabajo, desaparecería
 * junto con la reversa, y el único intento que queda registrado sería el que
 * SÍ tuvo éxito.
 *
 * Eso convertiría la bitácora en un registro de los accesos correctos, que es
 * justamente lo contrario de lo que sirve para descubrir un acceso indebido.
 *
 * LO QUE ESTA CLASE NO HACE
 * -------------------------
 * No lanza excepciones. Si la bitácora falla, el acceso se concede igual y el
 * fallo queda en el registro del sistema. La razón es que un problema de
 * escritura en la bitácora no debe dejar a un profesional sin poder atender, y
 * al revés tampoco: el caso grave es el denegado, y en ese caso la conexión
 * está viva porque la petición sigue abierta.
 */
class RegistroDeAccesos
{
    /**
     * Anota un acceso permitido.
     */
    public function permitido(
        Usuario $usuario,
        string $recurso,
        string $accion,
        ?string $recursoId = null,
        ?string $motivo = null,
    ): void {
        $this->escribir($usuario, $recurso, $accion, $recursoId, ResultadoAcceso::Permitido, $motivo);
    }

    /**
     * Anota un acceso denegado.
     *
     * `$motivo` va en palabras del dominio, no en código. "Es administrativo y
     * los administrativos no abren el contenido de las sesiones" sirve para
     * entender un registro dos años después; "403" no.
     */
    public function denegado(
        Usuario $usuario,
        string $recurso,
        string $accion,
        ?string $recursoId = null,
        string $motivo = '',
    ): void {
        $this->escribir($usuario, $recurso, $accion, $recursoId, ResultadoAcceso::Denegado, $motivo);
    }

    /**
     * La escritura de verdad.
     *
     * Envuelve todo en un `try` porque, si el registro falla, lo que hay que
     * hacer es seguir y dejar constancia del fallo en el registro del sistema,
     * no cortar la atención de un paciente.
     */
    private function escribir(
        Usuario $usuario,
        string $recurso,
        string $accion,
        ?string $recursoId,
        ResultadoAcceso $resultado,
        ?string $motivo,
    ): void {
        try {
            Acceso::query()->create([
                'usuario_id' => $usuario->id,
                'rol' => $usuario->rol?->value,
                'recurso' => $recurso,
                'recurso_id' => $recursoId,
                'accion' => $accion,
                'resultado' => $resultado->value,
                'motivo' => $motivo,
                'ruta' => request()?->path(),
                'metodo_http' => request()?->method(),
                'direccion_ip' => request()?->ip(),
                'agente_usuario' => $this->recortar(request()?->userAgent(), 240),
                'ocurrido_en' => now(),
            ]);
        } catch (\Throwable $e) {
            // El registro del sistema no lleva datos personales: el canal por
            // omisión en producción es el esterilizado. Ver docs/06.
            Log::error('No se pudo escribir el registro de acceso.', [
                'recurso' => $recurso,
                'accion' => $accion,
                'resultado' => $resultado->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Corta un texto a un largo, sin partirlo en un carácter suelto.
     */
    private function recortar(?string $valor, int $largo): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (mb_strlen($valor) <= $largo) {
            return $valor;
        }

        return mb_substr($valor, 0, $largo - 1) . '~';
    }
}