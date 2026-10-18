<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarifa del consultorio.
 *
 * `precio_clp` admite `null` y así NACE. Esta aplicación no trae ni un solo
 * precio: un valor por omisión sería una afirmación sobre lo que cuesta una
 * sesión, y esa afirmación cambia por tipo, por profesional, por región y por
 * año.
 *
 * La interfaz tiene que mostrar el hueco. Un precio en `null` que se muestre
 * como cero es peor que no mostrar nada: el consultorio cobraría la diferencia.
 */
class Tarifa extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'tarifas';

    /** @var list<string> */
    protected $fillable = [
        'tipo_atencion_id',
        'profesional_id',
        'nombre',
        'precio_clp',
        'vigente_desde',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_clp' => 'integer',
            'vigente_desde' => 'date',
            'activo' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<TipoAtencion, $this> */
    public function tipoAtencion(): BelongsTo
    {
        return $this->belongsTo(TipoAtencion::class);
    }

    /** @return BelongsTo<Profesional, $this> */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    /**
     * Si esta tarifa tiene un precio usable.
     */
    public function tienePrecio(): bool
    {
        return $this->precio_clp !== null;
    }
}