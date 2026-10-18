<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Consultorio.
 *
 * EL MODELO QUE NO USA EL AISLAMIENTO
 * -----------------------------------
 * Este es el único modelo sin `PerteneceAlConsultorio`. La razón es la misma
 * por la que la tabla no tiene Row Level Security: en el ingreso todavía no hay
 * consultorio, y sin consultorio no hay nada que filtrar.
 *
 * Lo que el ingreso consulta NO es esta tabla sino la vista
 * `vw_consultorios_publicas`, que expone tres columnas. Ver
 * `ConsultoriosPublicosController` y docs/03.
 */
class Consultorio extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'consultorios';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'slug',
        'rut',
        'email_contacto',
        'telefono_contacto',
        'direccion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** @return HasMany<Usuario, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    /** @return HasMany<Profesional, $this> */
    public function profesionales(): HasMany
    {
        return $this->hasMany(Profesional::class);
    }

    /** @return HasMany<Paciente, $this> */
    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class);
    }

    /**
     * Los consultorios que el ingreso puede mostrar.
     *
     * Esta consulta es A LA VISTA, no a la tabla. La interfaz de ingreso lista
     * lo que sale de acá, y lo que sale son solo tres columnas: identificador,
     * nombre y slug. El constructor de consultas se usa directo porque el
     * modelo no aplica el alcance de consultorio a propósito: esta consulta
     * corre antes de que exista un consultorio.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function publicos()
    {
        return DB::table('vw_consultorios_publicas')
            ->select(['id', 'nombre', 'slug'])
            ->orderBy('nombre')
            ->get();
    }
}