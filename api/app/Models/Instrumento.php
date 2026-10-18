<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instrumento de evaluación: una escala o un cuestionario con puntaje.
 *
 * LA PARTE IMPORTANTE DE ESTE MODELO SON LAS CUATRO PREGUNTAS
 * -----------------------------------------------------------
 * Un puntaje de corte es un dato clínico, y esta aplicación no inventa datos
 * clínicos. Entonces:
 *
 *   - `puntaje_corte` NACE EN NULL. Sin valor por omisión, sin "el habitual".
 *   - `corte_verificado` NACE EN FALSE.
 *   - Para ponerlo en verdadero hace falta `fuente_del_corte` y
 *     `referencia_verificacion`, y lo exige un disparador de la base.
 *   - `puntaje_corte_pendiente` es un número que el consultorio conoce pero no
 *     puede respaldar. Se muestra marcado, y el sistema no lo usa para
 *     comparar nada.
 *
 * Los métodos de más abajo son los que la interfaz usa para explicar la
 * situación, y son la razón de que la interfaz pueda hacerlo.
 */
class Instrumento extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'instrumentos';

    /** @var list<string> */
    protected $fillable = [
        'codigo',
        'nombre',
        'version_instrumento',
        'descripcion',
        'cantidad_items',
        'puntaje_maximo',
        'puntaje_corte',
        'corte_verificado',
        'fuente_del_corte',
        'referencia_verificacion',
        'corte_verificado_por',
        'corte_verificado_en',
        'puntaje_corte_pendiente',
        'direccion_puntaje',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_items' => 'integer',
            'puntaje_maximo' => 'integer',
            'puntaje_corte' => 'decimal:2',
            'corte_verificado' => 'boolean',
            'corte_verificado_en' => 'datetime',
            'puntaje_corte_pendiente' => 'decimal:2',
            'activo' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** @return HasMany<AplicacionInstrumento, $this> */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(AplicacionInstrumento::class);
    }

    /**
     * Si este instrumento tiene un corte que se puede usar.
     *
     * Solo cuando el corte está cargado Y verificado. Un corte cargado y sin
     * verificar no cuenta: se puede anotar, se puede ver, y no sirve para
     * comparar nada.
     */
    public function tieneCorteVerificado(): bool
    {
        return $this->corte_verificado && $this->puntaje_corte !== null;
    }

    /**
     * Si hay un número anotado esperando respaldo.
     *
     * La interfaz lo muestra con una advertencia distinta a la del corte
     * verificado: uno se puede usar y el otro no.
     */
    public function tieneCortePendiente(): bool
    {
        return ! $this->corte_verificado && $this->puntaje_corte_pendiente !== null;
    }

    /**
     * Cómo explicar el estado del corte, en palabras.
     *
     * La interfaz muestra esto al lado del número, para que nadie crea que un
     * corte sin fuente es lo mismo que un corte con fuente.
     */
    public function estadoDelCorte(): string
    {
        if ($this->tieneCorteVerificado()) {
            return 'Corte verificado contra la fuente declarada.';
        }

        if ($this->puntaje_corte !== null) {
            return 'Hay un corte cargado que NO está verificado. El sistema no lo usa para comparar.';
        }

        if ($this->tieneCortePendiente()) {
            return 'Corte anotado y pendiente de respaldo. El sistema no lo usa para comparar.';
        }

        return 'Este instrumento no tiene puntaje de corte cargado.';
    }
}