<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profesional del consultorio.
 *
 * `registro_profesional_verificado` nace en `false`. Verificar el registro de
 * un profesional es un acto del consultorio, con su colegio profesional, y no
 * algo que el software pueda hacer. Ver docs/06.
 */
class Profesional extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'profesionales';

    /** @var list<string> */
    protected $fillable = [
        'nombres',
        'apellidos',
        'apellido_materno',
        'nombre_agenda',
        'rut',
        'registro_profesional',
        'registro_profesional_verificado',
        'registro_verificado_en',
        'registro_verificado_por',
        'especialidad',
        'email',
        'telefono',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registro_profesional_verificado' => 'boolean',
            'registro_verificado_en' => 'datetime',
            'activo' => 'boolean',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /**
     * El nombre con el que se lo ve en la agenda.
     */
    public function nombreCompleto(): string
    {
        return trim($this->nombres . ' ' . $this->apellidos);
    }

    /** @return BelongsTo<Consultorio, $this> */
    public function consultorio(): BelongsTo
    {
        return $this->belongsTo(Consultorio::class, 'tenant_id');
    }

    /** @return HasMany<Usuario, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    /** @return HasMany<Cita, $this> */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** @return HasMany<NotaSesion, $this> */
    public function notas(): HasMany
    {
        return $this->hasMany(NotaSesion::class);
    }

    /** @return HasMany<Derivacion, $this> */
    public function derivaciones(): HasMany
    {
        return $this->hasMany(Derivacion::class);
    }
}