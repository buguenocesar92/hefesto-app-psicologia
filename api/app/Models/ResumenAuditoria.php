<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResultadoAcceso;
use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Resumen diario de la bitácora.
 *
 * Es lo que hace que la pantalla de dirección abra rápido: cuenta por día,
 * acción y resultado, en lugar de recorrer millones de filas de `accesos`.
 *
 * NO REEMPLAZA A LA BITÁCORA. `accesos` no se borra, y este resumen es solo
 * una cuenta agregada. Si un día hay que investigar un acceso concreto, se va a
 * `accesos`, que es la fuente.
 *
 * La unicidad por día, acción y resultado es lo que permite que la tarea de
 * consolidación se pueda volver a correr sin duplicar: vuelve a contar y
 * sobrescribe, en lugar de sumar dos veces.
 */
class ResumenAuditoria extends Model
{
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'resumenes_auditoria';

    /** @var list<string> */
    protected $fillable = [
        'fecha',
        'accion',
        'resultado',
        'total',
        'consolidado_en',
    ];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'resultado' => ResultadoAcceso::class,
            'total' => 'integer',
            'consolidado_en' => 'datetime',
        ];
    }
}