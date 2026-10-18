<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paciente>
 *
 * OJO CON LO QUE NO GENERA
 * ------------------------
 * No genera `motivo_consulta` con texto de relleno largo, porque ese campo tiene
 * 500 caracteres a propósito y es del lado que el administrativo puede leer. Si
 * la fábrica metiera un relato, las pruebas estarían probando un caso que la
 * aplicación no permite.
 *
 * Tampoco genera RUT válido: el dígito verificador no se valida en esta
 * aplicación, y una fábrica que inventa un RUT "válido" estaría afirmando que
 * la validación existe.
 */
class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombres' => $this->faker->firstName(),
            'apellidos' => $this->faker->lastName(),
            'apellido_materno' => $this->faker->lastName(),
            'rut' => null,
            'fecha_nacimiento' => null,
            'sexo' => null,
            'telefono' => $this->faker->numerify('+56 9 #### ####'),
            'email' => $this->faker->safeEmail(),
            'domicilio' => null,
            'prevision' => null,
            'plan_prevision' => null,
            'motivo_consulta' => null,
            'contacto_emergencia_nombre' => null,
            'contacto_emergencia_telefono' => null,
            'contacto_emergencia_parentesco' => null,
            'activo' => true,
            'codigo_ficha' => null,
        ];
    }

    /**
     * Con el motivo de consulta corto, como el que da una persona por
     * teléfono.
     */
    public function conMotivo(string $motivo): static
    {
        return $this->state(['motivo_consulta' => $motivo]);
    }
}