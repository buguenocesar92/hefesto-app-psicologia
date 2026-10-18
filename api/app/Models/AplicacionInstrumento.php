<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aplicación de un instrumento a un paciente.
 *
 * `puntaje` lo escribe el profesional, no el sistema. Calcularlo requiere la
 * clave de corrección del instrumento, que es parte del manual publicado, y
 * esta aplicación no trae ningún instrumento. Ver la migración.
 *
 * `comparacionConCorte` es la única comparación que el sistema hace, y solo si
 * el corte del instrumento está VERIFICADO. Es una resta entre dos números que
 * la base garantiza que tienen fuente. No es un diagnóstico, y la interfaz no
 * lo escribe como tal.
 */
class AplicacionInstrumento extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'aplicaciones_instrumento';

    /** @var list<string> */
    protected $fillable = [
        'paciente_id',
        'instrumento_id',
        'profesional_id',
        'cita_id',
        'fecha_aplicacion',
        'puntaje',
        'respuestas',
        'interpretacion',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_aplicacion' => 'date',
            'puntaje' => 'decimal:2',
            'respuestas' => 'array',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** @return BelongsTo<Instrumento, $this> */
    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(Instrumento::class);
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

    /**
     * Compara el puntaje con el corte, SOLO si el corte está verificado.
     *
     * @return array{corte: float|null, direccion: string|null, resultado: string}
     */
    public function comparacionConCorte(): array
    {
        $instrumento = $this->instrumento;

        if ($instrumento === null || ! $instrumento->tieneCorteVerificado() || $this->puntaje === null) {
            return [
                'corte' => null,
                'direccion' => $instrumento?->direccion_puntaje,
                'resultado' => 'Este instrumento no tiene un corte verificado, así que no hay comparación que hacer.',
            ];
        }

        $corte = (float) $instrumento->puntaje_corte;
        $puntaje = (float) $this->puntaje;

        // La dirección la declara el consultorio y es texto libre. Si no la
        // declaró, el sistema no adivina: dice que no sabe en qué sentido se
        // lee, que es la respuesta honesta.
        if ($instrumento->direccion_puntaje === null || trim($instrumento->direccion_puntaje) === '') {
            return [
                'corte' => $corte,
                'direccion' => null,
                'resultado' => 'El puntaje está sobre el corte, pero el consultorio no ha declarado en qué sentido se lee el puntaje de este instrumento.',
            ];
        }

        $mayorEsPeor = str_contains(
            mb_strtolower($instrumento->direccion_puntaje),
            'peor',
        );

        $resultado = $mayorEsPeor
            ? ($puntaje >= $corte
                ? 'El puntaje está por sobre el corte de este instrumento.'
                : 'El puntaje está bajo el corte de este instrumento.')
            : ($puntaje <= $corte
                ? 'El puntaje está por sobre el corte de este instrumento.'
                : 'El puntaje está bajo el corte de este instrumento.');

        return [
            'corte' => $corte,
            'direccion' => $instrumento->direccion_puntaje,
            'resultado' => $resultado,
        ];
    }
}