<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Consultorio;
use Illuminate\Database\Seeder;

/**
 * El primer consultorio.
 *
 * LO QUE ESTE SEEDER NO HACE
 * --------------------------
 * No crea usuarios con contraseñas, porque esta aplicación no tiene
 * contraseñas.
 *
 * No crea instrumentos con puntajes de corte. Ni de ejemplo, ni "de prueba", ni
 * "típicos". Un número de corte en un seeder es un número que alguien va a
 * leer como si fuera real, y alguien lo va a copiar a la instalación sin mirar
 * la fuente.
 * No crea pacientes. Un paciente de ejemplo en una base que se despliega es un
 * paciente que aparece en una pantalla real.
 *
 * Lo único que crea es lo mínimo para que la instalación tenga a dónde apuntar
 * el equipo de Authentik. Todo lo demás lo carga el consultorio. Ver docs/07.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Preparando el primer consultorio de Psicologia.');

        $consultorio = Consultorio::query()->firstOrCreate(
            ['slug' => 'consultorio-demo'],
            [
                'nombre' => 'Consultorio de ejemplo',
                'activo' => true,
            ],
        );

        $this->command?->line("  Consultorio: {$consultorio->nombre} ({$consultorio->slug})");

        $this->command?->line('');
        $this->command?->line('En Authentik, cree estos equipos:');
        $this->command?->line('');
        $this->command?->line("  consultorio-{$consultorio->slug}");
        $this->command?->line('  Profesionales de ' . config('authentik.grupo_por_consultorio'));
        $this->command?->line('  Administrativos de ' . config('authentik.grupo_por_consultorio'));
        $this->command?->line('  Administradores de ' . config('authentik.grupo_por_consultorio'));
        $this->command?->line('');
        $this->command?->line('Sin el equipo consultorio-<slug> no hay ingreso.');
        $this->command?->line('');
        $this->command?->warn('NO se crearon instrumentos con puntaje de corte.');
        $this->command?->line('Los carga el consultorio, con su fuente y su referencia de verificacion.');
        $this->command?->line('Para ver cuales faltan: php artisan psicologia:verificar-cortes');
        $this->command?->line('');
        $this->command?->line('Ver docs/07.');
    }
}