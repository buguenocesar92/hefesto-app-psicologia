<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TipoAtencion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoAtencion>
 */
class TipoAtencionFactory extends Factory
{
    protected $model = TipoAtencion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'T-' . $this->faker->unique()->numberBetween(1000, 9999),
            'nombre' => $this->faker->randomElement(['Individual', 'Pareja', 'Familiar', 'Primera entrevista']),
            'descripcion' => null,
            'duracion_minutos' => 50,
            'grupal' => false,
            'activo' => true,
        ];
    }

    /**
     * Con una duración concreta.
     */
    public function deMinutos(int $minutos): static
    {
        return $this->state(['duracion_minutos' => $minutos]);
    }
}