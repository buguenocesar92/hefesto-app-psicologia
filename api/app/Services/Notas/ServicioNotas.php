<?php

declare(strict_types=1);

namespace App\Services\Notas;

use App\Enums\AccionNota;
use App\Enums\EstadoNota;
use App\Models\NotaSesion;
use App\Models\Profesional;
use App\Models\RevisionNota;
use App\Models\Usuario;
use DomainException;

/**
 * Las reglas de la nota de sesión.
 *
 * LAS CUATRO REGLAS
 * -----------------
 *   1. Una nota se guarda mientras está ABIERTA.
 *   2. Una nota CERRADA no se edita.
 *   3. Una nota cerrada se CORRIGE, con motivo, y la corrección queda escrita.
 *   4. Una nota no se borra nunca.
 *
 * La segunda y la cuarta también están en la base, con disparadores. Acá están
 * para poder devolver un mensaje que la persona entienda. El código explica y
 * la base impide: por eso los dos tienen que decir lo mismo.
 *
 * POR QUÉ ESTO ES UN SERVICIO Y NO UN CONTROLADOR
 * ----------------------------------------------
 * Porque la misma regla se aplica desde la interfaz, desde un comando de
 * consola y desde una tarea de la cola. Si la regla viviera en el controlador,
 * los otros dos caminos la pasarían por alto.
 *
 * Ver docs/06.
 */
class ServicioNotas
{
    /**
     * Guarda el contenido de una nota abierta.
     *
     * @throws DomainException si la nota está cerrada
     */
    public function guardarContenido(NotaSesion $nota, Usuario $usuario, string $contenido): NotaSesion
    {
        if (! $nota->estado->admiteEdicion()) {
            throw new DomainException(
                'La nota está cerrada y no se puede editar. Para cambiar lo que dice, registre una '
                . 'corrección con su motivo: eso deja constancia de que cambió y por qué.'
            );
        }

        $limite = (int) config('psicologia.notas.contenido_maximo_caracteres');

        if (mb_strlen($contenido) > $limite) {
            throw new DomainException("El contenido excede el máximo de {$limite} caracteres.");
        }

        $nota->forceFill([
            'contenido' => $contenido,
        ])->save();

        return $nota->refresh();
    }

    /**
     * Cierra la nota.
     *
     * Cerrar no es reversible. Es la decisión que hace que la corrección
     * auditada sea necesaria, y por eso lleva su propio motivo.
     */
    public function cerrar(NotaSesion $nota, Usuario $usuario, ?string $motivo = null): NotaSesion
    {
        if ($nota->estado === EstadoNota::Cerrada) {
            throw new DomainException('La nota ya está cerrada.');
        }

        // Cerrar una nota vacía es casi siempre un error de la interfaz: nadie
        // cierra un texto que no escribió.
        if ($nota->contenido === null || trim($nota->contenido) === '') {
            throw new DomainException(
                'No se puede cerrar una nota vacía. Si la sesión no tuvo contenido, marque la cita '
                . 'como no realizada o deje la nota abierta.'
            );
        }

        $nota->forceFill([
            'estado' => EstadoNota::Cerrada,
            'cerrada_en' => now(),
            'cerrada_por' => $usuario->profesional_id,
            'motivo_cierre' => $motivo,
        ])->save();

        return $nota->refresh();
    }

    /**
     * Corrige una nota cerrada.
     *
     * ESTO NO ES UNA EDICIÓN, Y POR ESO TIENE OTRO NOMBRE Y OTRA RUTA.
     *
     * Escribe el texto que había, el texto que queda y por qué. Después sube
     * la versión. Lo que cambió queda a la vista, y eso es exactamente lo que
     * faltaría si esto fuera un `UPDATE` común.
     *
     * El texto anterior se guarda a propósito. Duplica el dato sensible dentro
     * de la misma tabla sensible, y es el precio de que una corrección no
     * pueda usarse para reescribir una sesión sin dejar rastro. Ver docs/06.
     *
     * @throws DomainException si la nota está abierta o el motivo es corto
     */
    public function corregir(
        NotaSesion $nota,
        Usuario $usuario,
        string $contenidoNuevo,
        string $motivo,
    ): RevisionNota {
        if (! $nota->estado->admiteCorreccion()) {
            throw new DomainException(
                'Esta nota está abierta. Si está abierta se edita normalmente; la corrección con '
                . 'motivo es solo para notas cerradas.'
            );
        }

        $minimo = (int) config('psicologia.notas.motivo_correccion_minimo');

        if (mb_strlen(trim($motivo)) < $minimo) {
            throw new DomainException(
                "El motivo de la corrección necesita al menos {$minimo} caracteres. Sin un motivo "
                . 'escrito, la corrección no explica por qué el registro cambió.'
            );
        }

        $limite = (int) config('psicologia.notas.contenido_maximo_caracteres');

        if (mb_strlen($contenidoNuevo) > $limite) {
            throw new DomainException("El contenido excede el máximo de {$limite} caracteres.");
        }

        $versionAnterior = (int) $nota->version;

        $revision = RevisionNota::query()->create([
            'nota_id' => $nota->id,
            'profesional_id' => $usuario->profesional_id,
            'version_anterior' => $versionAnterior,
            'version_nueva' => $versionAnterior + 1,
            'contenido_anterior' => $nota->contenido,
            'contenido_nuevo' => $contenidoNuevo,
            'motivo' => trim($motivo),
        ]);

        // El contenido de una nota cerrada no se puede cambiar con un
        // `UPDATE` normal: hay un disparador que lo rechaza, salvo que la fila
        // de historial de esta corrección ya exista. Por eso la revisión se
        // inscribe ANTES, en la misma transacción, y después se escribe el
        // contenido. Ver la función en
        // `2026_10_18_000032_bloquear_edicion_notas_cerradas`.
        $this->escribirComoCorreccionAuditada($nota, $contenidoNuevo, $versionAnterior + 1);

        return $revision->refresh();
    }

    /**
     * El historial de correcciones de una nota, de la más reciente a la más
     * antigua.
     *
     * @return \Illuminate\Support\Collection<int, RevisionNota>
     */
    public function historial(NotaSesion $nota)
    {
        return $nota->revisiones()->get()->reverse()->values();
    }

    /**
     * Escribe el contenido corregido.
     *
     * Es el ÚNICO camino por el que el contenido de una nota cerrada cambia, y
     * funciona porque la fila de historial ya está escrita. Si alguien
     * escribiera el contenido sin esa fila, el disparador de la base lo
     * rechaza.
     */
    private function escribirComoCorreccionAuditada(
        NotaSesion $nota,
        string $contenido,
        int $version,
    ): void {
        $conexion = $nota->getConnection();

        // Se escribe primero el contenido y después se sube la versión, en la
        // misma sentencia, para que no quede una nota con contenido nuevo y
        // versión vieja.
        $conexion->table('notas_sesion')
            ->where('id', $nota->id)
            ->update([
                'contenido' => $contenido,
                'version' => $version,
                'actualizado_en' => now(),
            ]);

        $nota->refresh();
    }
}