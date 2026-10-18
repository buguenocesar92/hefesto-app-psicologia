<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccionNota;
use App\Enums\RolClinico;
use App\Models\Concerns\PerteneceAlConsultorio;
use App\Services\Permisos\CapacidadesDeUsuario;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuario: la cuenta con la que se entra al sistema.
 *
 * NO HAY CONTRASEÑA EN ESTE MODELO
 * -------------------------------
 * Ni en la tabla, ni en el modelo, ni en la aplicación. Quien entra llega desde
 * Authentik y esta fila guarda el resultado de ese ingreso.
 *
 * `rol` es una COPIA del rol que tenía la persona en Authentik al momento de
 * entrar. Se relee en cada ingreso y se vuelve a escribir acá. Es lo que hace
 * que quitarle un equipo a alguien en Authentik surta efecto en la sesión
 * siguiente, y no en un plazo de días.
 *
 * `profesional_id` en `null` significa que la persona no atiende. Y es la
 * razón de que un administrativo no pueda abrir el contenido de ninguna
 * sesión: la regla dice "un profesional ve las notas de SUS pacientes", y un
 * administrativo no tiene "sus pacientes" porque no tiene ficha de
 * profesional. Ver docs/03.
 */
class Usuario extends Authenticatable
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'usuarios';

    /** @var list<string> */
    protected $fillable = [
        'sub',
        'email',
        'nombre',
        'rol',
        'profesional_id',
        'activo',
        'ultimo_ingreso_en',
        'rol_actualizado_en',
    ];

    /**
     * En Laravel, `$hidden` decide qué no se serializa a un arreglo. Acá se
     * esconde `sub`: es el identificador en Authentik y la interfaz no lo
     * necesita.
     *
     * @var list<string>
     */
    protected $hidden = ['sub'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rol' => RolClinico::class,
            'activo' => 'boolean',
            'ultimo_ingreso_en' => 'datetime',
            'rol_actualizado_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /**
     * Si esta persona administra el consultorio.
     */
    public function esAdministrador(): bool
    {
        return $this->rol === RolClinico::Administrador;
    }

    /**
     * Si esta persona atiende.
     *
     * Es la pregunta que decide el acceso al contenido de las sesiones, y la
     * respuesta corta es que no se deduce del rol: se deduce de que exista una
     * ficha de profesional asociada.
     */
    public function esTratante(): bool
    {
        return $this->profesional_id !== null;
    }

    /**
     * Si esta persona puede hacer esta acción sobre esta nota.
     *
     * La regla vive en `CapacidadesDeUsuario`. Acá se delega para que el
     * middleware y las Policies hablen con el modelo y no tengan que recibir
     * el servicio uno por uno. Ver docs/03.
     */
    public function puedeSobreNota(?NotaSesion $nota, AccionNota $accion): bool
    {
        return app(CapacidadesDeUsuario::class)->puedeSobreNota($this, $nota, $accion);
    }

    /**
     * Si esta persona puede operar sobre notas sin tener una nota en la mano.
     *
     * Es el caso del listado y de la creación.
     */
    public function noPuedeOperarSobreNotas(AccionNota $accion): bool
    {
        return ! app(CapacidadesDeUsuario::class)->puedeSobreNota($this, null, $accion);
    }

    /**
     * El motivo por el que esta persona no puede abrir el contenido de una
     * sesión, si no puede.
     *
     * Se devuelve para que la interfaz lo muestre. Una pantalla que dice "no
     * tiene permiso" sin decir por qué obliga a la persona a preguntar, y en
     * este caso la pregunta es siempre la misma.
     */
    public function motivoDeFaltaDeAcceso(?NotaSesion $nota, AccionNota $accion): string
    {
        return app(CapacidadesDeUsuario::class)->motivo($this, $nota, $accion);
    }

    /**
     * Su ficha de profesional, si tiene.
     *
     * @return BelongsTo<Profesional, $this>
     */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    /** @return BelongsTo<Consultorio, $this> */
    public function consultorio(): BelongsTo
    {
        return $this->belongsTo(Consultorio::class, 'tenant_id');
    }

    /** @return HasMany<Acceso, $this> */
    public function accesos(): HasMany
    {
        return $this->hasMany(Acceso::class);
    }

    /**
     * El motivo por el que esta persona no puede abrir el contenido de una
     * sesión, si no puede.
     *
     * Se devuelve para que la interfaz lo muestre. Una pantalla que dice
     * "no tiene permiso" sin decir por qué obliga a la persona a preguntar,
     * y en este caso la pregunta es siempre la misma.
     */
    public function motivoDeFaltaDeAcceso(): ?string
    {
        if (! $this->esTratante()) {
            return $this->rol?->accesoAlContenidoDeSesion();
        }

        return null;
    }
}