<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->name();

        return [
            // El identificador del sujeto en Authentik. En las pruebas es un
            // valor cualquiera: la aplicación nunca inventa una identidad.
            'sub' => (string) Str::uuid(),
            'email' => $this->faker->unique()->safeEmail(),
            'nombre' => $nombre,
            // Sin rol ni ficha de profesional: eso los elige cada prueba.
            'rol' => \App\Enums\RolClinico::Administrativo->value,
            'profesional_id' => null,
            'activo' => true,
            'ultimo_ingreso_en' => null,
            'rol_actualizado_en' => null,
        ];
    }

    public function profesional(): static
    {
        return $this->state([
            'rol' => \App\Enums\RolClinico::Profesional->value,
        ]);
    }

    public function administrador(): static
    {
        return $this->state([
            'rol' => \App\Enums\RolClinico::Administrador->value,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}