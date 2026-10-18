<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Constancias de consentimiento del paciente.
 *
 * QUÉ ES ESTA TABLA Y QUÉ NO ES
 * -----------------------------
 * Es la CONSTANCIA de que hubo un consentimiento, con su fecha y la versión del
 * documento que se firmó. No es el documento.
 *
 * El texto del consentimiento lo redacta y lo revisa el consultorio con su
 * asesoría legal. Esta aplicación no trae el texto, porque un texto de
 * consentimiento que el software da por bueno es un texto que el software no
 * revisó con un abogado. Ver docs/06.
 *
 * Lo que sí hace es guardar `version_documento`. Sin eso, dos años después no
 * hay forma de demostrar cuál era el texto que la persona firmó: "consentí"
 * y "consentí esto" son cosas distintas cuando alguien reclama.
 *
 * `constancia` dice dónde está el documento firmado en papel. Es texto libre
 * porque cada consultorio lo archiva distinto.
 *
 * Un paciente sin consentimiento de tratamiento vigente no puede tener
 * sesiones. Eso lo comprueba `ServicioAgenda`, no esta tabla: un control que
 * depende de que cada pantalla se acuerde no es un control.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->foreign('tenant_id')->references('id')->on('consultorios')->cascadeOnDelete();

            $tabla->uuid('paciente_id');
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->restrictOnDelete();

            $tabla->string('tipo', 32);

            // Versión del documento firmado. "v1", "2026-01", lo que el
            // consultorio use. Es obligatoria: sin versión, la constancia no
            // prueba nada.
            $tabla->string('version_documento', 32);

            // Modalidad: escrita o verbal. La ley chilena admite ambas para
            // ciertos tratamientos, y el sistema registra la que el consultorio
            // usó, sin calificar si esa cumple. La calificación legal la hace
            // la asesoría del consultorio. Ver docs/06.
            $tabla->string('modalidad', 16);

            // Dónde está el papel. Texto libre.
            $tabla->text('constancia')->nullable();

            // Quién registró la constancia y cuándo. Es distinto de la fecha
            // en que el paciente firmó: se puede cargar después.
            $tabla->date('firmado_en')->nullable();
            $tabla->uuid('registrado_por')->nullable();
            $tabla->timestampTz('registrado_en')->useCurrent();

            // Un consentimiento se anula, no se borra. Queda el registro de que
            // existió, y el motivo por el que dejó de estar vigente.
            $tabla->boolean('anulado')->default(false);
            $tabla->timestampTz('anulado_en')->nullable();
            $tabla->text('motivo_anulacion')->nullable();

            $tabla->timestampTz('creado_en')->useCurrent();
            $tabla->timestampTz('actualizado_en')->useCurrent();

            $tabla->index(['tenant_id', 'paciente_id']);
            $tabla->index(['tenant_id', 'tipo', 'anulado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos');
    }
};