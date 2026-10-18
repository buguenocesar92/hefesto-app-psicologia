<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoConsentimiento;
use App\Models\Consentimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consentimiento>
 */
class ConsentimientoFactory extends Factory
{
    protected $model = Consentimiento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paciente_id' => null,
            'tipo' => TipoConsentimiento::Tratamiento->value,
            // La versión es obligatoria porque sin ella la constancia no
            // prueba nada. Ver el modelo.
            'version_documento' => 'v1',
            'modalidad' => 'escrita',
            'constancia' => null,
            'firmado_en' => now()->toDateString(),
            'registrado_por' => null,
            'registrado_en' => now(),
            'anulado' => false,
            'anulado_en' => null,
            'motivo_anulacion' => null,
        ];
    }

    public function anulado(): static
    {
        return $this->state([
            'anulado' => true,
            'anulado_en' => now(),
            'motivo_anulacion' => 'Motivo de prueba, con largo suficiente.',
        ]);
    }
}