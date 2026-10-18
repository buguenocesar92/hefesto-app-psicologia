<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EstadoCita;
use App\Enums\EstadoDerivacion;
use App\Enums\EstadoNota;
use App\Enums\NaturalezaDerivacion;
use App\Enums\RolClinico;
use PHPUnit\Framework\TestCase;

/**
 * Las tablas de estado del dominio.
 *
 * POR QUÉ UNA PRUEBA QUE NO NECESITA LA BASE
 * ------------------------------------------
 * Porque la tabla de transiciones vive en el enum y no en un `if` dentro de un
 * controlador. Eso permite comprobarla entera sin levantar PostgreSQL, sin
 * crear un consultorio y sin una fila de paciente.
 *
 * Y tiene una ventaja que importa más: si alguien mueve una transición, esta
 * prueba falla en un segundo y con un mensaje claro, en vez de fallar en el
 * recorrido completo.
 *
 * Ver docs/02 y docs/07.
 */
class TransicionesDeEstadoTest extends TestCase
{
    /**
     * Una cita agendada puede llegar a todas las salidas posibles.
     */
    public function test_una_cita_agendada_puede_llegar_a_cada_salida(): void
    {
        $this->assertContains(EstadoCita::Agendada, EstadoCita::Realizada->estadosOrigen());
        $this->assertContains(EstadoCita::Agendada, EstadoCita::NoAsistio->estadosOrigen());
        $this->assertContains(EstadoCita::Agendada, EstadoCita::Cancelada->estadosOrigen());
        $this->assertContains(EstadoCita::Agendada, EstadoCita::Reagendada->estadosOrigen());
    }

    /**
     * Una cita que ya ocurrió no vuelve a agendada.
     *
     * Es la parte del histórico que no se reescribe: una sesión que se dio no
     * se puede convertir en una cita que todavía no pasó.
     */
    public function test_una_cita_realizada_no_vuelve_a_agendada(): void
    {
        $this->assertNotContains(EstadoCita::Agendada, EstadoCita::Realizada->estadosOrigen());
        $this->assertNotContains(EstadoCita::Agendada, EstadoCita::NoAsistio->estadosOrigen());
        $this->assertNotContains(EstadoCita::Agendada, EstadoCita::Cancelada->estadosOrigen());
    }

    /**
     * Una cita cerrada no admite cambio, y una agendada sí.
     */
    public function test_cuando_una_cita_admite_cambio(): void
    {
        $this->assertTrue(EstadoCita::Agendada->admiteCambio());
        $this->assertFalse(EstadoCita::Realizada->admiteCambio());
        $this->assertFalse(EstadoCita::NoAsistio->admiteCambio());
        $this->assertFalse(EstadoCita::Cancelada->admiteCambio());
        $this->assertFalse(EstadoCita::Reagendada->admiteCambio());

        $this->assertTrue(EstadoCita::Realizada->esCerrado());
        $this->assertFalse(EstadoCita::Agendada->esCerrado());
    }

    /**
     * Solo una cita realizada admite nota.
     */
    public function test_solo_una_cita_realizada_admite_nota(): void
    {
        $this->assertTrue(EstadoCita::Realizada->admiteNotaDeSesion());
        $this->assertFalse(EstadoCita::Agendada->admiteNotaDeSesion());
        $this->assertFalse(EstadoCita::NoAsistio->admiteNotaDeSesion());
        $this->assertFalse(EstadoCita::Cancelada->admiteNotaDeSesion());
    }

    /**
     * Una nota abierta se edita; una cerrada, no.
     */
    public function test_cuando_una_nota_admite_edicion(): void
    {
        $this->assertTrue(EstadoNota::Abierta->admiteEdicion());
        $this->assertFalse(EstadoNota::Cerrada->admiteEdicion());
    }

    /**
     * La corrección es para la nota cerrada, y solo para ella.
     *
     * Es la diferencia entre editar y corregir, y es el punto de toda la
     * máquina de estados de la nota.
     */
    public function test_cuando_una_nota_admite_correccion(): void
    {
        $this->assertTrue(EstadoNota::Cerrada->admiteCorreccion());
        $this->assertFalse(EstadoNota::Abierta->admiteCorreccion());
    }

    /**
     * Las dos salidas de una nota son excluyentes.
     */
    public function test_editar_y_corregir_no_se_solapan(): void
    {
        foreach (EstadoNota::cases() as $estado) {
            $this->assertFalse(
                $estado->admiteEdicion() && $estado->admiteCorreccion(),
                "El estado {$estado->value} no debería admitir edición y corrección a la vez.",
            );
        }
    }

    /**
     * Una derivación nace vigente.
     */
    public function test_una_derivacion_nace_vigente(): void
    {
        $this->assertSame([], EstadoDerivacion::Vigente->estadosOrigen());
    }

    /**
     * Se puede volver de resuelta a atendida, porque un caso puede reabrirse.
     */
    public function test_una_derivacion_resuelta_puede_reabrirse(): void
    {
        $this->assertContains(EstadoDerivacion::Resuelta, EstadoDerivacion::Atendida->estadosOrigen());
        $this->assertFalse(EstadoDerivacion::Resuelta->sigueAbierta());
        $this->assertTrue(EstadoDerivacion::Atendida->sigueAbierta());
        $this->assertTrue(EstadoDerivacion::Vigente->sigueAbierta());
    }

    /**
     * Salir de resuelto es lo único que no se puede repetir, porque sería
     * reescribir un cierre.
     */
    public function test_una_derivacion_resuelta_no_vuelve_a_resuelta(): void
    {
        $this->assertNotContains(EstadoDerivacion::Resuelta, EstadoDerivacion::Resuelta->estadosOrigen());
    }

    /**
     * La exigencia de contacto inmediato sale de la naturaleza.
     *
     * Es el único guion de urgencia del sistema, y no sale de ningún número.
     */
    public function test_cuando_una_derivacion_exige_contacto_inmediato(): void
    {
        $this->assertTrue(NaturalezaDerivacion::Urgencia->exigeContactoInmediato());

        foreach (NaturalezaDerivacion::cases() as $naturaleza) {
            if ($naturaleza !== NaturalezaDerivacion::Urgencia) {
                $this->assertFalse(
                    $naturaleza->exigeContactoInmediato(),
                    'Solo la urgencia exige contacto inmediato.',
                );
            }
        }
    }

    /**
     * Ningún rol, salvo el profesional, tiene acceso al contenido de las
     * sesiones.
     *
     * Es el resumen de la regla del producto, en una prueba que no necesita la
     * base.
     */
    public function test_ningun_rol_salvo_el_profesional_abre_el_contenido(): void
    {
        foreach (RolClinico::cases() as $rol) {
            $explicacion = $rol->accesoAlContenidoDeSesion();

            if ($rol === RolClinico::Profesional) {
                $this->assertStringContainsString('Puede abrir', $explicacion);

                continue;
            }

            $this->assertStringContainsString(
                'No puede abrir',
                $explicacion,
                "El rol {$rol->value} no debería abrir el contenido de las sesiones.",
            );
        }
    }

    /**
     * Todos los estados tienen etiqueta en español.
     *
     * La etiqueta es lo que aparece en la agenda y en la bitácora. Una
     * etiqueta en inglés o un valor crudo en pantalla es una fuga de detalle
     * que se nota.
     */
    public function test_todos_los_estados_tienen_etiqueta(): void
    {
        $enumeraciones = [
            EstadoCita::cases(),
            EstadoNota::cases(),
            EstadoDerivacion::cases(),
            NaturalezaDerivacion::cases(),
            RolClinico::cases(),
        ];

        foreach ($enumeraciones as $casos) {
            foreach ($casos as $caso) {
                $this->assertNotSame('', $caso->etiqueta());
            }
        }
    }
}