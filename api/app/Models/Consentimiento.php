<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoConsentimiento;
use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constancia de consentimiento.
 *
 * `version_documento` es obligatoria porque sin versión la constancia no
 * prueba nada: "consentí" y "consentí esto" son cosas distintas cuando alguien
 * reclama.
 *
 * El TEXTO del consentimiento no está acá. Lo redacta el consultorio con su
 * asesoría legal, y esta aplicación no trae ninguno: un texto que el software
 * da por bueno es un texto que el software no revisó con un abogado. Ver
 * docs/06.
 */
class Consentimiento extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'consentimientos';

    /** @var list<string> */
    protected $fillable = [
        'paciente_id',
        'tipo',
        'version_documento',
        'modalidad',
        'constancia',
        'firmado_en',
        'registrado_por',
        'registrado_en',
        'anulado',
        'anulado_en',
        'motivo_anulacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoConsentimiento::class,
            'firmado_en' => 'date',
            'registrado_en' => 'datetime',
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

    /**
     * Si este consentimiento está vigente ahora.
     *
     * Vigente quiere decir: no anulado. No hay fecha de vencimiento, porque
     * el momento en que un consentimiento deja de valer es un decisión
     * jurídica del consultorio, y esta aplicación no la inventa.
     */
    public function estaVigente(): bool
    {
        return ! $this->anulado;
    }
}