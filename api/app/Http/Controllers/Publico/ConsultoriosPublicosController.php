<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Consultorio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los consultorios que la pantalla de ingreso puede mostrar.
 *
 * ESTA RUTA ES LA QUE HACE ÚTIL A LA VISTA PÚBLICA
 * -------------------------------------------------
 * Consulta `vw_consultorios_publicas`, que expone tres columnas: identificador,
 * nombre y slug.
 *
 * No consulta la tabla `consultorios`. La tabla tiene RUT, correo, teléfono,
 * dirección, y no hay ningún motivo para que la pantalla de ingreso los
 * muestre.
 *
 * ESTA ES LA RUTA QUE LA DOCUMENTACIÓN NO DEBE PROMETER EN VANO
 * ------------------------------------------------------------
 * En otras aplicaciones de Hefesto se creó la vista `vw_..._publicas` y nunca
 * se consultó, mientras los documentos decían que el ingreso la usaba. Eso es
 * prometer una protección que no existe.
 *
 * Acá la ruta existe, consulta la vista, y hay una prueba en
 * `tests/Feature/Aislamiento/VistaPublicaSeUsaTest.php` que la comprueba.
 *
 * Ver docs/03.
 */
class ConsultoriosPublicosController extends Controller
{
    public function __invoke(Request $peticion): JsonResponse
    {
        $consultorios = Consultorio::publicos();

        return response()->json([
            'datos' => $consultorios->map(fn (object $fila): array => [
                'id' => $fila->id,
                'nombre' => $fila->nombre,
                'slug' => $fila->slug,
            ])->values(),
        ]);
    }
}