<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResultadoAcceso;
use App\Models\Concerns\PerteneceAlConsultorio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un acceso a un recurso: permitido o denegado.
 *
 * SE ESCRIBE EN LA CONEXIÓN DE AUDITORÍA
 * -------------------------------------
 * Esta tabla se escribe sobre la conexión `pgsql_auditoria`, que es distinta de
 * la de trabajo. La razón es concreta: una petición a la que se le deniega el
 * permiso termina con la transacción revertida, y el registro del intento tiene
 * que sobrevivir a esa reversa. Si escribiera en la misma conexión, el registro
 * del intento denegado desaparecería justo en el caso en que más importa.
 *
 * `ResolverTenant` fija `app.tenant_id` en LAS DOS conexiones, dentro del mismo
 * alcance de transacción. Por eso la Row Level Security de `accesos` también
 * se cumple acá: sin eso, la conexión de auditoría no tendría contexto y la
 * tabla sería invisible para todos.
 *
 * `tenant_id` no está en el relleno a propósito. Lo escribe el contexto, no la
 * petición. Ver `PerteneceAlConsultorio`.
 *
 * SOLO SE AGREGAN FILAS
 * ---------------------
 * No hay ruta que modifique un acceso ni que lo borre, y no hay marcas de
 * tiempo de modificación: un registro de acceso que se pudiera editar dejaría
 * de ser un registro.
 */
class Acceso extends Model
{
    use HasUuids;
    use PerteneceAlConsultorio;

    protected $table = 'accesos';

    protected $connection = 'pgsql_auditoria';

    /** @var list<string> */
    protected $fillable = [
        'usuario_id',
        'rol',
        'recurso',
        'recurso_id',
        'accion',
        'resultado',
        'motivo',
        'ruta',
        'metodo_http',
        'direccion_ip',
        'agente_usuario',
        'ocurrido_en',
    ];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoAcceso::class,
            'ocurrido_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}