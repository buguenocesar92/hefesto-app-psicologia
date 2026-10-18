<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una corrección registrada sobre una nota cerrada.
 *
 * SOLO SE AGREGA
 * --------------
 * No hay ruta que la modifique y no hay ruta que la borre, y hay disparadores
 * en la base que rechazan las dos operaciones. Este modelo no tiene `update`
 * ni `delete` con sentido: si se llega a una fila de esta tabla, es para
 * leerla o para insertar una nueva.
 *
 * `contenido_anterior` duplica el dato sensible dentro de la misma tabla
 * sensible, y es a propósito. Sin el texto anterior, una corrección sería
 * indistinguible de una edición en silencio. Ver docs/06.
 */
class RevisionNota extends Model
{
    use HasFactory;
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'revisiones_nota';

    /** @var list<string> */
    protected $fillable = [
        'nota_id',
        'profesional_id',
        'version_anterior',
        'version_nueva',
        'contenido_anterior',
        'contenido_nuevo',
        'motivo',
    ];

    /**
     * Los dos textos quedan fuera del `toArray()` por la misma razón que en
     * `NotaSesion`: un listado de revisiones no debería llevar el contenido
     * completo de cada versión.
     *
     * @var list<string>
     */
    protected $hidden = ['contenido_anterior', 'contenido_nuevo'];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_anterior' => 'integer',
            'version_nueva' => 'integer',
            'registrado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<NotaSesion, $this> */
    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaSesion::class);
    }

    /** @return BelongsTo<Profesional, $this> */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class);
    }
}