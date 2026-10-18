<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paciente: los datos de identificación.
 *
 * LO QUE ESTE MODELO NO ES
 * -----------------------
 * No es la ficha clínica. Acá hay nombre, RUT, contacto y el motivo por el que
 * pidió hora, que es lo que dijo al llamar por teléfono.
 *
 * Lo que se dijo en las sesiones está en `notas_sesion`, que es una tabla
 * distinta y con permisos distintos. Un administrativo abre esta y no puede ni
 * acercarse a la otra. Esa separación es el producto. Ver docs/03.
 *
 * `motivo_consulta` tiene 500 caracteres en la base. No es un detalle: si este
 * campo creciera hasta ser un relato de vida, estaríamos guardando historia
 * clínica en el lado que el administrativo puede leer.
 *
 * Un paciente se DESACTIVA, no se borra. Su historia de terapia no desaparece
 * porque terminó, y menos porque un día el consultorio decida que le conviene
 * menos tenerla.
 */
class Paciente extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'pacientes';

    /** @var list<string> */
    protected $fillable = [
        'nombres',
        'apellidos',
        'apellido_materno',
        'rut',
        'fecha_nacimiento',
        'sexo',
        'telefono',
        'email',
        'domicilio',
        'prevision',
        'plan_prevision',
        'motivo_consulta',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'contacto_emergencia_parentesco',
        'activo',
        'desactivado_en',
        'motivo_desactivacion',
        'codigo_ficha',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
            'desactivado_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /**
     * El nombre con el que aparece en la agenda.
     */
    public function nombreCompleto(): string
    {
        $nombre = trim($this->nombres . ' ' . $this->apellidos);

        if ($this->apellido_materno !== null && trim($this->apellido_materno) !== '') {
            $nombre .= ' ' . trim($this->apellido_materno);
        }

        return $nombre;
    }

    /**
     * Un nombre corto para la agenda: el que se alcance a leer en una fila.
     */
    public function nombreCorto(): string
    {
        return $this->nombres . ' ' . $this->apellidos;
    }

    /** @return HasMany<Cita, $this> */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** @return HasMany<Consentimiento, $this> */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class);
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

    /** @return HasMany<AplicacionInstrumento, $this> */
    public function aplicacionesInstrumento(): HasMany
    {
        return $this->hasMany(AplicacionInstrumento::class);
    }

    /** @return HasMany<Pago, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }
}