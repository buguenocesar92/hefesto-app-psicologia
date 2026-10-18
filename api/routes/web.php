<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Rutas de la API
|------------------------------------------------------------------------------
|
| Todo cuelga de `api/v1` y del grupo `web` de Laravel. El grupo `web` es el
| que trae la sesión con cookie y el token CSRF: la interfaz Nuxt reenvía la
| cookie y la cabecera X-XSRF-TOKEN, y no hay ningún token en el navegador.
| Ver docs/01.
|
| EL CÓDIGO QUE SE DEVUELVE CUANDO NO HAY PERMISO
| -------------------------------------------------
| Es 404, no 403, en todo el dominio. Un 403 confirmaría que el recurso
| existe, y en el contenido de una sesión eso le diría a quien preguntó
| "sí, hay una nota de esa persona, y no te la puedo mostrar". El 404 no
| confirma nada. Ver docs/03.
|
| LO QUE NO HAY EN ESTE ARCHIVO
| -----------------------------
| No hay ruta DELETE para notas de sesión, ni para derivaciones, ni para
| pacientes. No es una omisión: la ausencia de la ruta es la primera barrera,
| y debajo hay un disparador en la base que rechaza el borrado aunque alguien
| entre por consola. Ver docs/02.
|
| Las rutas se mantienen IMPLICITAS (Route::apiResource y closures) a
| propósito: una ruta que no se nombra no se puede deshabilitar por error, y
| no hay lista de rutas que mantener a mano en `docs/09`.
|
*/

use App\Http\Controllers\Api\Administracion\AuditoriaController;
use App\Http\Controllers\Api\Administracion\ProfesionalController;
use App\Http\Controllers\Api\Administracion\TarifaController;
use App\Http\Controllers\Api\Administracion\TipoAtencionController;
use App\Http\Controllers\Api\Administracion\UsuarioController;
use App\Http\Controllers\Api\Agenda\CitaController;
use App\Http\Controllers\Api\Agenda\ConsultaAgendaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Derivaciones\DerivacionController;
use App\Http\Controllers\Api\Instrumentos\AplicacionController;
use App\Http\Controllers\Api\Instrumentos\InstrumentoController;
use App\Http\Controllers\Api\Notas\NotaSesionController;
use App\Http\Controllers\Api\Pacientes\ConsentimientoController;
use App\Http\Controllers\Api\Pacientes\PacienteController;
use App\Http\Controllers\Api\Panel\PanelController;
use App\Http\Controllers\Api\Pagos\BoletaController;
use App\Http\Controllers\Api\Pagos\PagoController;
use App\Http\Controllers\Publico\ConsultoriosPublicosController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Público
|------------------------------------------------------------------------------
|
| El listado de consultorios sale de la vista `vw_consultorios_publicas`, que
| expone SOLO tres columnas: identificador, nombre y slug. Es lo que se puede
| mostrar en la pantalla de ingreso sin filtrar nada.
|
| Esa vista se USA DE VERDAD acá. En otras aplicaciones de Hefesto se creó la
| vista y nunca se consultó, mientras la documentación afirmaba que sí; el
| punto de crearla es que el login la use. Ver docs/03.
|
*/
Route::prefix('v1')->group(function (): void {
    Route::get('salud', fn () => response()->json([
        'estado' => 'ok',
        'servicio' => 'psicologia-api',
    ]));

    Route::get('consultorios-publicos', ConsultoriosPublicosController::class);
});

/*
|------------------------------------------------------------------------------
| Ingreso y salida
|------------------------------------------------------------------------------
|
| El ingreso no es un formulario con usuario y contraseña: es una
| redirección al proveedor de identidad. Ver docs/03.
|
*/
Route::prefix('v1')->group(function (): void {
    Route::get('auth/entrar', [AuthController::class, 'entrar'])->name('auth.entrar');
    Route::get('auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
    Route::post('auth/salir', [AuthController::class, 'salir'])->name('auth.salir');
});

/*
|------------------------------------------------------------------------------
| Con sesión
|------------------------------------------------------------------------------
|
| `auth:web` es lo que obliga a que haya una sesión válida antes de que el
| middleware `ResolverTenant` resuelva el consultorio y lo fije en la
| conexión.
|
| A partir de acá cada grupo vuelve a preguntar a su Policy. El permiso no
| se hereda del grupo anterior: cada ruta lo comprueba por su cuenta, porque
| un permiso heredado es un permiso que alguien olvidó al reescribir.
|
*/
Route::prefix('v1')->middleware('auth:web')->group(function (): void {

    Route::get('auth/yo', [AuthController::class, 'yo'])->name('auth.yo');

    /*
    |------------------------------------------------------------------
    | PACIENTES
    |------------------------------------------------------------------
    |
    | Los datos de identificación. Lo que se ve acá NO es contenido clínico:
    | un administrativo puede ver quién es el paciente y cuándo tiene cita,
    | y no qué le pasó.
    |
    | Sin DELETE: un paciente se desactiva, no se borra. La ficha de una
    | persona que estuvo en terapia no es un registro que se pueda eliminar
    | porque se crispe un día.
    |
    */
    Route::get('pacientes', [PacienteController::class, 'indice']);
    Route::post('pacientes', [PacienteController::class, 'guardar']);
    Route::get('pacientes/{paciente}', [PacienteController::class, 'mostrar'])->name('pacientes.mostrar');
    Route::patch('pacientes/{paciente}', [PacienteController::class, 'actualizar']);
    Route::post('pacientes/{paciente}/desactivar', [PacienteController::class, 'desactivar']);

    /*
    |------------------------------------------------------------------
    | CONSENTIMIENTOS
    |------------------------------------------------------------------
    |
    | Un paciente sin consentimiento vigente no puede tener sesiones
    | registradas. El bloqueo está en el servicio de agenda, no acá: el
    | servicio es el que se llama desde todos lados, y un control que
    | depende de que cada pantalla se acuerde de hacerlo, no es un
    | control.
    |
    */
    Route::get('pacientes/{paciente}/consentimientos', [ConsentimientoController::class, 'indice']);
    Route::post('pacientes/{paciente}/consentimientos', [ConsentimientoController::class, 'registrar']);
    Route::post('consentimientos/{consentimiento}/anular', [ConsentimientoController::class, 'anular']);

    /*
    |------------------------------------------------------------------
    | AGENDA
    |------------------------------------------------------------------
    |
    | Acá entra todo el consultorio: la agenda no es el dato sensible. Un
    | administrativo la ve completa porque sin ella no puede atender el
    | teléfono ni cobrar.
    |
    */
    Route::get('agenda', ConsultaAgendaController::class)->name('agenda');
    Route::get('agenda/disponibilidad', ConsultaAgendaController::class . '@disponibilidad');
    Route::post('citas', [CitaController::class, 'guardar']);
    Route::get('citas/{cita}', [CitaController::class, 'mostrar'])->name('citas.mostrar');
    Route::patch('citas/{cita}', [CitaController::class, 'actualizar']);
    Route::post('citas/{cita}/reagendar', [CitaController::class, 'reagendar']);

    /*
    |------------------------------------------------------------------
    | NOTAS DE SESIÓN: EL CONTENIDO
    |------------------------------------------------------------------
    |
    | Estas rutas llevan el middleware `nota.auditada`. Anota en la bitácora
    | CADA intento de abrir el contenido de una sesión, y corta con 404 a
    | quien no tiene permiso.
    |
    | Un administrativo entra acá, la sesión existe, y lo que se le devuelve es
    | que no la ve. No es una pantalla vacía: es un 404 que no distingue "no
    | existe" de "no te corresponde". Ver docs/03 y docs/06.
    |
    | `PUT notas/{nota}` guarda. `POST notas/{nota}/cerrar` bloquea. Y
    | `POST notas/{nota}/correcciones` es OTRA cosa: una corrección sobre una
    | nota cerrada, que escribe el texto anterior y el motivo, y sube la
    | versión. Nunca es una edición silenciosa. Ver docs/06.
    |
    */
    Route::middleware('nota.auditada')->group(function (): void {
        Route::get('notas', [NotaSesionController::class, 'indice']);
        Route::post('notas', [NotaSesionController::class, 'guardar']);
        Route::get('notas/{nota}', [NotaSesionController::class, 'mostrar'])->name('notas.mostrar');
        Route::put('notas/{nota}', [NotaSesionController::class, 'actualizar']);
        Route::post('notas/{nota}/cerrar', [NotaSesionController::class, 'cerrar']);
        Route::post('notas/{nota}/correcciones', [NotaSesionController::class, 'corregir']);

        Route::get('notas/{nota}/revisiones', [NotaSesionController::class, 'revisiones']);
    });

    /*
    |------------------------------------------------------------------
    | INSTRUMENTOS DE EVALUACIÓN
    |------------------------------------------------------------------
    |
    | El catálogo de instrumentos es administrativo. Las aplicaciones a un
    | paciente las ve el tratante.
    |
    | `POST instrumentos/{id}/verificar-corte` es la única forma de pasar
    | `corte_verificado` a verdadero, y exige una fuente y una referencia. Sin
    | eso, el puntaje de corte sigue en `null` y la interfaz lo dice. Un
    | corte sin fuente no se verifica: se anota. Ver docs/06.
    |
    */
    Route::get('instrumentos', [InstrumentoController::class, 'indice']);
    Route::post('instrumentos', [InstrumentoController::class, 'guardar']);
    Route::get('instrumentos/{instrumento}', [InstrumentoController::class, 'mostrar'])->name('instrumentos.mostrar');
    Route::patch('instrumentos/{instrumento}', [InstrumentoController::class, 'actualizar']);
    Route::post('instrumentos/{instrumento}/verificar-corte', [InstrumentoController::class, 'verificarCorte']);
    Route::get('instrumentos/{instrumento}/aplicaciones', [AplicacionController::class, 'indiceDelInstrumento']);

    Route::get('pacientes/{paciente}/instrumentos', [AplicacionController::class, 'indiceDelPaciente']);
    Route::post('pacientes/{paciente}/instrumentos', [AplicacionController::class, 'registrar']);
    Route::get('aplicaciones/{aplicacion}', [AplicacionController::class, 'mostrar'])->name('aplicaciones.mostrar');
    Route::patch('aplicaciones/{aplicacion}', [AplicacionController::class, 'actualizar']);

    /*
    |------------------------------------------------------------------
    | DERIVACIONES POR CRISIS
    |------------------------------------------------------------------
    |
    | NO HAY RUTA DE BORRADO. Y no es solo que falte: la base tiene un
    | disparador que rechaza el DELETE, así que tampoco se puede borrar
    | desde la consola con una llave de la base.
    |
    | Lo que sí se puede es cambiar el estado (vigente, atendida, resuelta)
    | y dejar por qué. El registro de que alguien decidió derivar no se
    | desaparece nunca. Ver docs/06.
    |
    */
    Route::get('derivaciones', [DerivacionController::class, 'indice']);
    Route::post('derivaciones', [DerivacionController::class, 'registrar']);
    Route::get('derivaciones/{derivacion}', [DerivacionController::class, 'mostrar'])->name('derivaciones.mostrar');
    Route::patch('derivaciones/{derivacion}', [DerivacionController::class, 'cambiarEstado']);
    Route::get('derivaciones/{derivacion}/historial', [DerivacionController::class, 'historial']);

    /*
    |------------------------------------------------------------------
    | PAGOS Y BOLETAS
    |------------------------------------------------------------------
    |
    | Un administrativo ve cuánto se pagó; no ve en qué se empleó la sesión.
    |
    | La aplicación NO emite boletas electrónicas. Guarda el número y el folio
    | que emitió el sistema que use el consultorio, si usa alguno. Ver
    | docs/06, "lo que no está hecho".
    |
    */
    Route::get('pagos', [PagoController::class, 'indice']);
    Route::post('pagos', [PagoController::class, 'registrar']);
    Route::get('pagos/{pago}', [PagoController::class, 'mostrar'])->name('pagos.mostrar');
    Route::post('pagos/{pago}/anular', [PagoController::class, 'anular']);

    Route::get('boletas', [BoletaController::class, 'indice']);
    Route::post('boletas', [BoletaController::class, 'registrar']);
    Route::get('boletas/{boleta}', [BoletaController::class, 'mostrar'])->name('boletas.mostrar');
    Route::post('boletas/{boleta}/anular', [BoletaController::class, 'anular']);

    /*
    |------------------------------------------------------------------
    | BITÁCORA DE ACCESOS
    |------------------------------------------------------------------
    |
    | Solo dirección. Un profesional no puede leer la bitácora completa.
    | Ver docs/06.
    |
    */
    Route::get('auditoria/accesos', [AuditoriaController::class, 'accesos']);
    Route::get('auditoria/resumenes', [AuditoriaController::class, 'resumenes']);

    /*
    |------------------------------------------------------------------
    | ADMINISTRACIÓN DEL CONSULTORIO
    |------------------------------------------------------------------
    |
    | El prefijo `admin` exige ser administrador del consultorio. Cada
    | recurso vuelve a preguntar a su Policy igual.
    |
    */
    Route::middleware('admin')->prefix('administracion')->group(function (): void {
        Route::get('profesionales', [ProfesionalController::class, 'indice']);
        Route::post('profesionales', [ProfesionalController::class, 'guardar']);
        Route::get('profesionales/{profesional}', [ProfesionalController::class, 'mostrar']);
        Route::patch('profesionales/{profesional}', [ProfesionalController::class, 'actualizar']);

        Route::get('tipos-atencion', [TipoAtencionController::class, 'indice']);
        Route::post('tipos-atencion', [TipoAtencionController::class, 'guardar']);
        Route::patch('tipos-atencion/{tipoAtencion}', [TipoAtencionController::class, 'actualizar']);

        Route::get('tarifas', [TarifaController::class, 'indice']);
        Route::post('tarifas', [TarifaController::class, 'guardar']);
        Route::patch('tarifas/{tarifa}', [TarifaController::class, 'actualizar']);

        Route::get('usuarios', [UsuarioController::class, 'indice']);
        Route::patch('usuarios/{usuario}', [UsuarioController::class, 'actualizar']);
    });

    /*
    |------------------------------------------------------------------
    | PANEL
    |------------------------------------------------------------------
    |
    | Lo primero que carga la pantalla de inicio. Trae lo que el rol
    | PUEDE ver: si a un administrativo no le corresponde ninguna nota, la
    | respuesta no trae el conteo de notas. Ver docs/09.
    |
    */
    Route::get('panel', PanelController::class)->name('panel');
});