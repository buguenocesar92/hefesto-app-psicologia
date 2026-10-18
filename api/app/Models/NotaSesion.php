<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoNota;
use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nota de sesión: EL CONTENIDO.
 *
 * QUÉ ES Y QUÉ NO ES ESTE MODELO
 * -----------------------------
 * Es el texto que un profesional escribió sobre lo que pasó en una sesión. Es
 * lo más sensible que hay en esta aplicación.
 *
 * Cargar el modelo no es tener permiso para leerlo. El permiso lo decide
 * `NotaSesionPolicy`, y hay tres reglas:
 *
 *   1. Solo un profesional la abre, y solo si es suya.
 *   2. Un administrativo nunca la abre, aunque sea del mismo consultorio.
 *   3. Un intento denegado se anota en la bitácora.
 *
 * LA REGLA DE LA EDICIÓN ESTÁ EN LA BASE, NO SOLO AQUÍ
 * ---------------------------------------------------
 * Que una nota cerrada no se pueda editar lo impone el disparador de
 * `2026_10_18_000032_bloquear_edicion_notas_cerradas`. `EstadoNota::admiteEdicion`
 * dice lo mismo desde el dominio, y los dos tienen que decir lo mismo: el
 * código da un mensaje bueno y la base impide el cambio.
 *
 * `contenido` está en `$hidden`. Eso NO es una protección: el atributo sigue
 * accesible desde PHP, y quien tenga permiso lo va a ver. Lo que hace es que
 * un `toArray()` descuidado no arrastre el contenido de todas las notas de un
 * listado a un registro o a una respuesta que no lo necesitaba. Ver docs/06.
 */
class NotaSesion extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'notas_sesion';

    /** @var list<string> */
    protected $fillable = [
        'cita_id',
        'paciente_id',
        'profesional_id',
        'estado',
        'contenido',
        'version',
        'cerrada_en',
        'cerrada_por',
        'motivo_cierre',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['contenido'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoNota::class,
            'version' => 'integer',
            'cerrada_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Cita, $this> */
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** @return BelongsTo<Profesional, $this> */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }

    /**
     * El historial de correcciones.
     *
     * Solo crece: no se modifica ni se borra, y hay disparadores que lo
     * garantizan. Ver `2026_10_18_000032`.
     *
     * @return HasMany<RevisionNota, $this>
     */
    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionNota::class)->orderBy('registrado_en');
    }

    /**
     * Si esta nota admite edición directa.
     */
    public function admiteEdicion(): bool
    {
        return $this->estado->admiteEdicion();
    }
}