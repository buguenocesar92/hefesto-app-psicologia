<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Administracion;

use App\Enums\AccionNota;
use App\Http\Controllers\Controller;
use App\Models\Acceso;
use App\Models\ResumenAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La bitácora de accesos.
 *
 * PARA QUÉ SIRVE
 * --------------
 * Para responder "quién abrió qué". Y en salud mental la pregunta más
 * importante no es "quién vio", sino "quién INTENTÓ ver y no pudo": eso es lo
 * que detecta un acceso indebido, y es lo que se pierde si los rechazos no se
 * anotan.
 *
 * SOLO DIRECCIÓN
 * -------------
 * La bitácora muestra, para cada acceso, el rol de la persona, qué recurso
 * intentó abrir y con qué resultado. Verla completa revela la estructura del
 * consultorio: cuántos profesionales hay, qué roles existen y qué actividad
 * hay. Eso no lo necesita cualquiera.
 *
| POR QUÉ LOS RECHAZOS SE ESCRIBEN EN OTRA CONEXIÓN
| ------------------------------------------------
| Una petición denegada termina revertida. Si el registro del intento se
| escribiera en la misma transacción, desaparecería justo en el único caso que
| importa. Ver `RegistroDeAccesos` y docs/06.
*/
class AuditoriaController extends Controller
{
    /**
     * El registro de accesos.
     *
     * Trae los denegados primero: son los que nadie mira y los que importan.
     */
    public function accesos(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Acceso::class);

        $accesos = Acceso::query()
            ->when($peticion->filled('desde'), fn ($q) => $q->where('ocurrido_en', '>=', $peticion->date('desde')))
            ->when($peticion->filled('hasta'), fn ($q) => $q->where('ocurrido_en', '<=', $peticion->date('hasta')))
            ->when($peticion->filled('resultado'), fn ($q) => $q->where('resultado', $peticion->input('resultado')))
            ->when($peticion->filled('recurso'), fn ($q) => $q->where('recurso', $peticion->input('recurso')))
            ->when($peticion->filled('usuario_id'), fn ($q) => $q->where('usuario_id', $peticion->input('usuario_id')))
            ->when(
                $peticion->boolean('solo_contenido'),
                fn ($q) => $q->where('recurso', 'nota_sesion')
            )
            ->orderByDesc('ocurrido_en')
            ->limit(500)
            ->get();

        return response()->json([
            'datos' => $accesos->map(fn (Acceso $acceso): array => [
                'id' => $acceso->id,
                'usuario_id' => $acceso->usuario_id,
                'rol' => $acceso->rol,
                'recurso' => $acceso->recurso,
                'recurso_id' => $acceso->recurso_id,
                'accion' => $acceso->accion,
                'resultado' => $acceso->resultado->value,
                'resultado_etiqueta' => $acceso->resultado->etiqueta(),
                'motivo' => $acceso->motivo,
                'ruta' => $acceso->ruta,
                'direccion_ip' => $acceso->direccion_ip,
                'ocurrido_en' => $acceso->ocurrido_en?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * El resumen diario.
     *
     * Viene de `resumenes_auditoria`, que arma `ConsolidarAuditoriaDiaria`. Es
     * solo un conteo: dice cuántos accesos hubo por acción y resultado, sin
     * decir quién.
     */
    public function resumenes(Request $peticion): JsonResponse
    {
        $this->authorize('verResumenes', Acceso::class);

        $desde = $peticion->date('desde') ?? now()->subDays(30)->toDateString();

        $resumenes = ResumenAuditoria::query()
            ->where('fecha', '>=', $desde)
            ->orderByDesc('fecha')
            ->orderBy('accion')
            ->get();

        return response()->json([
            'datos' => $resumenes->map(fn (ResumenAuditoria $r): array => [
                'fecha' => $r->fecha?->toDateString(),
                'accion' => $r->accion,
                'accion_etiqueta' => AccionNota::tryFrom($r->accion)?->etiqueta() ?? $r->accion,
                'resultado' => $r->resultado->value,
                'total' => $r->total,
                'consolidado_en' => $r->consolidado_en?->toIso8601String(),
            ])->values(),
        ]);
    }
}