<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profesional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profesional>
 */
class ProfesionalFactory extends Factory
{
    protected $model = Profesional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombres = $this->faker->firstName();
        $apellidos = $this->faker->lastName();

        return [
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'apellido_materno' => $this->faker->lastName(),
            'nombre_agenda' => $nombres . ' ' . $this->faker->firstName($apellidos),
            'rut' => null,
            // Sin registro y sin verificar por omisión: la verificación la
            // hace la dirección del consultorio, no la fábrica.
            'registro_profesional' => null,
            'registro_profesional_verificado' => false,
            'registro_verificado_en' => null,
            'registro_verificado_por' => null,
            'especialidad' => $this->faker->randomElement(['Clinica', 'Infantil', 'Familiar', 'Clinica de la salud']),
            'email' => $this->faker->safeEmail(),
            'telefono' => null,
            'activo' => true,
        ];
    }

    /**
     * Con el registro profesional verificado.
     */
    public function conRegistroVerificado(): static
    {
        return $this->state([
            'registro_profesional' => 'REG-' . $this->faker->numberBetween(1000, 9999),
            'registro_profesional_verificado' => true,
            'registro_verificado_en' => now(),
        ]);
    }
}