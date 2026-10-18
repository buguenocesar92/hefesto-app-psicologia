<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Consultorio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Consultorio>
 */
class ConsultorioFactory extends Factory
{
    protected $model = Consultorio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->company() . ' Psicologia';

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre, '-'),
            'rut' => null,
            'email_contacto' => $this->faker->safeEmail(),
            'telefono_contacto' => null,
            'direccion' => null,
            'activo' => true,
        ];
    }

    /**
     * Un consultorio dado de baja.
     */
    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}