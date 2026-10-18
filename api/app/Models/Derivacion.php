<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDerivacion;
use App\Enums\NaturalezaDerivacion;
use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derivación por crisis.
 *
 * ESTE MODELO NO SE BORRA
 * -----------------------
 * No hay ruta de borrado, y hay un disparador de la base que rechaza el
 * `DELETE` y el `TRUNCATE`. No hay estado "anulada" y no hay bandera de
 * borrado.
 *
 * Si el profesional decide que una persona necesita ser derivada, esa decisión
 * queda. Después se puede atender y se puede resolver, con su fecha y su
 * motivo, pero la fila de que se decidió derivar no se va nunca. Ver docs/06.
 *
 * `motivo` y `destino` son obligatorios, y el servicio comprueba que el motivo
 * tenga largo mínimo: una derivación sin motivo escrito no sostiene la
 * decisión que tomó.
 */
class Derivacion extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'derivaciones';

    /** @var list<string> */
    protected $fillable = [
        'paciente_id',
        'profesional_id',
        'cita_id',
        'nota_id',
        'naturaleza',
        'destino',
        'motivo',
        'estado',
        'exige_contacto_inmediato',
        'registrada_en',
        'atendida_en',
        'atendida_por',
        'motivo_atencion',
        'resuelta_en',
        'resuelta_por',
        'motivo_resolucion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'naturaleza' => NaturalezaDerivacion::class,
            'estado' => EstadoDerivacion::class,
            'exige_contacto_inmediato' => 'boolean',
            'registrada_en' => 'datetime',
            'atendida_en' => 'datetime',
            'resuelta_en' => 'datetime',
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

    /** @return BelongsTo<Cita, $this> */
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    /** @return BelongsTo<NotaSesion, $this> */
    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaSesion::class);
    }

    /**
     * Si esta derivación sigue abierta.
     */
    public function sigueAbierta(): bool
    {
        return $this->estado->sigueAbierta();
    }
}