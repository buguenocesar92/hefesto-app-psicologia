<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCita;
use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Cita de la agenda.
 *
 * `duracion_minutos` se copió del tipo de atención al agendar. Si después
 * cambian los minutos del catálogo, esta cita sigue siendo, en el registro, la
 * cita que era.
 *
 * Esta tabla NO es contenido de sesión: la ven todos los roles del
 * consultorio. Lo que se dijo en la sesión está en `notas_sesion`, y esa sí
 * está detrás de los permisos de contenido. Ver docs/03.
 */
class Cita extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'citas';

    /** @var list<string> */
    protected $fillable = [
        'paciente_id',
        'profesional_id',
        'tipo_atencion_id',
        'fecha_inicio',
        'fecha_fin',
        'duracion_minutos',
        'estado',
        'motivo_consultacion',
        'nota_agenda',
        'cita_reagendada_desde',
        'registrada_por',
        'registrada_en',
        'estado_cambiado_en',
        'estado_cambiado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'duracion_minutos' => 'integer',
            'estado' => EstadoCita::class,
            'registrada_en' => 'datetime',
            'estado_cambiado_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** @return BelongsTo<Profesional, $this> */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    /** @return BelongsTo<TipoAtencion, $this> */
    public function tipoAtencion(): BelongsTo
    {
        return $this->belongsTo(TipoAtencion::class);
    }

    /** @return BelongsTo<Cita, $this> */
    public function reagendadaDesde(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_reagendada_desde');
    }

    /**
     * La nota de esta sesión, si la hay.
     *
     * La relación es de uno a uno porque hay una restricción de unicidad sobre
     * `notas_sesion.cita_id`: "la nota de esa sesión" es una sola cosa.
     *
     * @return HasOne<NotaSesion, $this>
     */
    public function nota(): HasOne
    {
        return $this->hasOne(NotaSesion::class);
    }

    /**
     * Si esta cita admite todavía un cambio de estado.
     */
    public function admiteCambio(): bool
    {
        return $this->estado->admiteCambio();
    }
}