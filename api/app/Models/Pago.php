<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de una atención.
 *
 * Un pago se anula con motivo, no se borra. La caja de un consultorio tiene que
 * poder cuadrar con lo que la base dice que pasó, y un pago que desaparece
 * deja un hueco en esa cuadra. Ver docs/08.
 *
 * `monto_clp` es un entero de pesos. Los pesos chilenos no tienen parte decimal
 * en la práctica, y mandar un flotante a una boleta termina en un error de
 * redondeo que alguien tiene que explicar al paciente.
 */
class Pago extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'pagos';

    /** @var list<string> */
    protected $fillable = [
        'paciente_id',
        'cita_id',
        'boleta_id',
        'monto_clp',
        'fecha_pago',
        'metodo',
        'pago_parcial',
        'monto_esperado_clp',
        'observacion',
        'anulado',
        'anulado_en',
        'motivo_anulacion',
        'registrado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_clp' => 'integer',
            'fecha_pago' => 'datetime',
            'pago_parcial' => 'boolean',
            'monto_esperado_clp' => 'integer',
            'anulado' => 'boolean',
            'anulado_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** @return BelongsTo<Cita, $this> */
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    /** @return BelongsTo<Boleta, $this> */
    public function boleta(): BelongsTo
    {
        return $this->belongsTo(Boleta::class);
    }
}