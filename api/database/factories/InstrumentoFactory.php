<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Instrumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instrumento>
 *
 * ESTA FÁBRICA NO PONE PUNTAJES DE CORTE, Y ES LO MÁS IMPORTANTE DE ELLA
 * ---------------------------------------------------------------------
 * `puntaje_corte` nace en `null` y `corte_verificado` en `false`, siempre.
 *
 * No hay un estado por omisión que ponga un corte, ni un método `verificado()`
 * con un número adentro. Si lo hubiera, bastaría con que una prueba usara ese
 * estado y el número inventado estaría en las pruebas, en la documentación y
 * en la cabeza de alguien.
 *
 * Un corte se carga con `ServicioInstrumentos::verificarCorte`, que exige una
 * fuente, y la base lo rechaza si no la hay.
 */
class InstrumentoFactory extends Factory
{
    protected $model = Instrumento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'INS-' . $this->faker->unique()->numberBetween(1000, 9999),
            'nombre' => 'Instrumento de prueba',
            'version_instrumento' => null,
            'descripcion' => null,
            'cantidad_items' => null,
            'puntaje_maximo' => null,

            'puntaje_corte' => null,
            'corte_verificado' => false,
            'fuente_del_corte' => null,
            'referencia_verificacion' => null,
            'corte_verificado_por' => null,
            'corte_verificado_en' => null,
            'puntaje_corte_pendiente' => null,

            'direccion_puntaje' => null,
            'activo' => true,
        ];
    }

    /**
     * Un instrumento con un corte pendiente.
     *
     * Sirve para probar que un número sin respaldo se muestra marcado y no se
     * usa para comparar nada. El número lo pasa quien llama, no la fábrica.
     */
    public function conCortePendiente(float $puntaje): static
    {
        return $this->state([
            'puntaje_corte_pendiente' => $puntaje,
            'corte_verificado' => false,
        ]);
    }

    /**
     * Un instrumento con un corte verificado.
     *
     * El número y la fuente los pasa quien llama. La fábrica no propone
     * ninguno: poner un "valor típico" acá sería exactamente el dato inventado
     * que este proyecto no permite.
     */
    public function conCorteVerificado(float $puntaje, string $fuente, string $referencia): static
    {
        return $this->state([
            'puntaje_corte' => $puntaje,
            'corte_verificado' => true,
            'fuente_del_corte' => $fuente,
            'referencia_verificacion' => $referencia,
            'corte_verificado_en' => now(),
        ]);
    }
}