<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Boleta de atención.
 *
 * ESTA APLICACIÓN NO EMITE BOLETAS
 * -------------------------------
 * No pide firma al Servicio de Impuestos Internos, no genera folio, no genera
 * documento electrónico. Guarda lo que emitió el sistema que use el consultorio,
 * si usa alguno.
 *
 * `numero` y `folio` admiten `null` porque hay consultorios que emiten en
 * papel. `rut_emisor` admite `null` y NO se valida el dígito verificador:
 * hacerlo bien requiere revisar la norma vigente, y no se verificó en el
 * entorno donde se escribió esta aplicación. Ver docs/06.
 *
 * Una boleta se anula con motivo, no se borra.
 */
class Boleta extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'boletas';

    /** @var list<string> */
    protected $fillable = [
        'paciente_id',
        'numero',
        'folio',
        'rut_emisor',
        'fecha_emision',
        'monto_total_clp',
        'detalle',
        'estado',
        'anulada',
        'anulada_en',
        'motivo_anulacion',
        'registrada_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'monto_total_clp' => 'integer',
            'detalle' => 'array',
            'anulada' => 'boolean',
            'anulada_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** @return BelongsTo<Pago, $this> */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }
}