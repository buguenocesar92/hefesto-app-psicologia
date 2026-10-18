<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\ConsolidarAuditoriaDiaria;
use App\Models\Acceso;
use App\Models\AplicacionInstrumento;
use App\Models\Boleta;
use App\Models\Cita;
use App\Models\Consentimiento;
use App\Models\Derivacion;
use App\Models\Instrumento;
use App\Models\NotaSesion;
use App\Models\Pago;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\ResumenAuditoria;
use App\Models\Tarifa;
use App\Models\TipoAtencion;
use App\Models\Usuario;
use App\Policies\AccesoPolicy;
use App\Policies\AplicacionInstrumentoPolicy;
use App\Policies\CatalogoPolicy;
use App\Policies\CitaPolicy;
use App\Policies\DerivacionPolicy;
use App\Policies\InstrumentoPolicy;
use App\Policies\NotaSesionPolicy;
use App\Policies\PacientePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Enlaza las Policies con sus modelos.
 *
 * POR QUÉ NO EN `routes/web.php`
 * -----------------------------
 * Porque un permiso que se declara junto a las rutas se pierde el día que
 * alguien agrega un archivo de rutas y no lo copia. Aquí se declara una vez, y
 * el nombre del método es el que usa `$this->authorize(...)`.
 *
 * `CatalogoPolicy` cubre cuatro recursos que comparten la misma regla
 * (tipos de atención, tarifas, profesionales y usuarios), así que los cuatro
 * modelos apuntan a la misma Policy. Ver `CatalogoPolicy`.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Sin servicios que registrar aparte a mano: los de autenticación y
        // permisos se resuelven por el contenedor.
    }

    public function boot(): void
    {
        $this->registrarPolicies();

        $this->programarTareas();
    }

    /**
     * Los permisos, modelo por modelo.
     */
    private function registrarPolicies(): void
    {
        Gate::policy(NotaSesion::class, NotaSesionPolicy::class);
        Gate::policy(Paciente::class, PacientePolicy::class);
        Gate::policy(Cita::class, CitaPolicy::class);
        Gate::policy(Derivacion::class, DerivacionPolicy::class);
        Gate::policy(Instrumento::class, InstrumentoPolicy::class);
        Gate::policy(AplicacionInstrumento::class, AplicacionInstrumentoPolicy::class);
        Gate::policy(Acceso::class, AccesoPolicy::class);

        // Catálogos de administración.
        Gate::policy(TipoAtencion::class, CatalogoPolicy::class);
        Gate::policy(Tarifa::class, CatalogoPolicy::class);
        Gate::policy(Profesional::class, CatalogoPolicy::class);
        Gate::policy(Usuario::class, CatalogoPolicy::class);

        Gate::policy(ResumenAuditoria::class, AccesoPolicy::class);
        Gate::policy(Consentimiento::class, PacientePolicy::class);
        Gate::policy(Boleta::class, PacientePolicy::class);
        Gate::policy(Pago::class, PacientePolicy::class);
    }

    /**
     * Lo que corre solo.
     *
     * UNA SOLO TAREA
     * --------------
     * La consolidación de la bitácora. Y va en la cola, no en el proceso del
     * planificador, porque cuenta sobre la tabla que más crece y bloquearía
     * el ingreso de un profesional en medio de una consulta.
     *
     * LO QUE NO HAY
     * -------------
     * Ninguna tarea que borre historia clínica. Los plazos de conservación
     * están en `null` por omisión, y sin plazo configurado no hay purga.
     * Borrar notas de sesión porque pasó un número inventado sería peor que
     * no borrar nunca. Ver docs/06.
     */
    private function programarTareas(): void
    {
        $this->app->booted(function (): void {
            // Cada noche, la bitácora del día anterior.
            Schedule::job(
                new ConsolidarAuditoriaDiaria(now()->subDay()->toDateString())
            )->dailyAt('02:30')
              ->withoutOverlapping()
              ->onOneServer();
        });
    }
}