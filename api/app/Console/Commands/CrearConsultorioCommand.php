<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Consultorio;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea un consultorio.
 *
 * EL PRIMERO ES EL QUE NO TIENE AISLAMIENTO PUESTO TODAVÍA
 * ---------------------------------------------------------
 * El ingreso necesita la tabla `consultorios`, y esa tabla no tiene Row Level
 * Security a propósito: en el ingreso todavía no hay consultorio. Ver
 * `2026_10_18_000010`.
 *
 * Este comando se corre una vez, con las migraciones ya aplicadas y antes de
 * que haya usuarios.
 *
 * EL SLUG
 * -------
 * El slug es lo que aparece en la pantalla de ingreso y lo que va en el equipo
 * de Authentik (`consultorio-<slug>`). Se genera del nombre si no se pasa, y
 * se normaliza a minúsculas sin tildes para que el equipo de Authentik sea
 * fácil de escribir.
 */
class CrearConsultorioCommand extends Command
{
    protected $signature = 'psicologia:crear-consultorio
                            {nombre : Nombre del consultorio}
                            {--slug= : Identificador corto. Si no se pasa, se genera del nombre}
                            {--rut= : RUT del consultorio}
                            {--email= : Correo de contacto}';

    protected $description = 'Crea un consultorio';

    public function handle(): int
    {
        $nombre = (string) $this->argument('nombre');

        $slug = $this->opcion('slug') ?: $this->generarSlug($nombre);

        try {
            $validador = validator(
                [
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'rut' => $this->opcion('rut'),
                    'email_contacto' => $this->opcion('email'),
                ],
                [
                    'nombre' => ['required', 'string', 'max:160'],
                    'slug' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:consultorios,slug'],
                    'rut' => ['nullable', 'string', 'max:12'],
                    'email_contacto' => ['nullable', 'email', 'max:160'],
                ],
                [
                    'slug.unique' => 'Ya existe un consultorio con ese identificador.',
                ],
            );

            if ($validador->fails()) {
                $this->error('No se pudo crear el consultorio:');

                foreach ($validador->errors()->all() as $error) {
                    $this->line("  - {$error}");
                }

                return self::FAILURE;
            }
        } catch (ValidationException $e) {
            $this->error('No se pudo crear el consultorio: ' . $e->getMessage());

            return self::FAILURE;
        }

        $consultorio = Consultorio::query()->create([
            'nombre' => $nombre,
            'slug' => $slug,
            'rut' => $this->opcion('rut'),
            'email_contacto' => $this->opcion('email'),
            'activo' => true,
        ]);

        $this->info('Consultorio creado.');
        $this->line('');
        $this->line("  Identificador: {$consultorio->slug}");
        $this->line('');
        $this->line('En Authentik, cree un equipo con este nombre para cada persona que entre:');
        $this->line('');
        $this->line("  consultorio-{$consultorio->slug}          (a todas las personas del consultorio)");
        $this->line("  Profesionales de " . config('authentik.grupo_por_consultorio') . "     (rol profesional)");
        $this->line("  Administrativos de " . config('authentik.grupo_por_consultorio') . "    (rol administrativo)");
        $this->line("  Administradores de " . config('authentik.grupo_por_consultorio') . "   (rol administrador)");
        $this->line('');
        $this->line('Sin el equipo consultorio-<slug> no hay ingreso: sin consultorio no hay filas.');
        $this->line('Ver docs/03 y docs/07.');

        return self::SUCCESS;
    }

    /**
     * Un identificador a partir del nombre: en minúsculas, sin tildes, con
     * guiones en vez de espacios.
     */
    private function generarSlug(string $nombre): string
    {
        $sinTildes = Str::ascii($nombre);

        return (string) Str::of($sinTildes)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->limit(80, '')
            ->value();
    }
}