<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoCita;
use App\Models\Cita;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cita>
 */
class CitaFactory extends Factory
{
    protected $model = Cita::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = now()->addDays(3)->setTime(10, 0);

        return [
            'paciente_id' => null,
            'profesional_id' => null,
            'tipo_atencion_id' => null,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $inicio->copy()->addMinutes(50),
            'duracion_minutos' => 50,
            'estado' => EstadoCita::Agendada->value,
            'motivo_consultacion' => null,
            'nota_agenda' => null,
            'cita_reagendada_desde' => null,
            'registrada_por' => null,
            'registrada_en' => now(),
        ];
    }

    public function realizada(): static
    {
        return $this->state([
            'estado' => EstadoCita::Realizada->value,
            'estado_cambiado_en' => now(),
        ]);
    }

    public function para(DateTimeInterface $fecha): static
    {
        return $this->state(fn (): array => [
            'fecha_inicio' => $fecha,
            'fecha_fin' => (clone $fecha)->modify('+50 minutes'),
        ]);
    }
}