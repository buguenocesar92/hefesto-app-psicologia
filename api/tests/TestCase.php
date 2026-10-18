<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\RolClinico;
use App\Enums\TipoConsentimiento;
use App\Models\Consentimiento;
use App\Models\Consultorio;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\TipoAtencion;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Base de las pruebas de Psicología.
 *
 * POR QUÉ LAS PRUEBAS LEVANTAN EL AISLAMAMIENTO DE VERDAD
 * ------------------------------------------------------
 * Las pruebas se corren contra PostgreSQL con las migraciones aplicadas, con la
 * Row Level Security activada y con los disparadores puestos.
 *
 * En SQLite estas pruebas pasarían sin comprobar nada: un sistema sin
 * aislamiento alguno también daría verde, porque SQLite no tiene Row Level
 * Security que lo niegue. Ver `phpunit.xml`.
 *
 * POR QUÉ `DatabaseTransactions` Y NO UN `rollback` A MANO
 * ------------------------------------------------------
 * Porque las fábricas crean filas que la prueba necesita después. Si el
 * `rollback` estuviera dentro del ayudante, los modelos devueltos apuntarían a
 * filas que ya no existen.
 *
 * El aislador de cada prueba es la transacción de la suite, que se revierte al
 * terminar. Adentro, `dentroDe` solo fija el contexto de consultorio.
 *
 * EL PATRÓN `dentroDe`
 * -------------------
 * Toda prueba que toque datos va dentro de `dentroDe`, que fija el consultorio
 * en la conexión, como lo hace `ResolverTenant`.
 *
 * LA RESERVA
 * ----------
 * Esta simulación NO comprueba que `ResolverTenant` funcione: comprueba el
 * dominio, con el contexto puesto a mano. El middleware en sí no se prueba en
 * esta suite, y eso está anotado como pendiente en docs/10.
 */
abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    /**
     * Corre un bloque como una persona, con su consultorio.
     *
     * @template T
     *
     * @param  callable(): T  $bloque
     * @return T
     */
    protected function dentroDe(Usuario $usuario, callable $bloque): mixed
    {
        return $this->conContexto($usuario->tenant_id, $bloque);
    }

    /**
     * Corre un bloque con el consultorio fijo, sin usuario.
     *
     * Lo usan las fábricas, que no necesitan una sesión.
     *
     * @template T
     *
     * @param  callable(): T  $bloque
     * @return T
     */
    protected function conContexto(string $consultorioId, callable $bloque): mixed
    {
        DB::connection('pgsql')->statement('SELECT set_config(?, ?, true)', [
            'app.tenant_id',
            $consultorioId,
        ]);

        DB::connection('pgsql_auditoria')->statement('SELECT set_config(?, ?, true)', [
            'app.tenant_id',
            $consultorioId,
        ]);

        $resultado = $bloque();

        // El valor en la base se va con la transacción de la prueba. El estado
        // de PHP no se usa acá a propósito: las pruebas fijan el contexto
        // siempre antes de tocar datos, y no dependen de que alguien se acuerde
        // de limpiar.
        ContextoTenant::olvidar();

        return $resultado;
    }

    /**
     * Un consultorio nuevo.
     */
    protected function crearConsultorio(string $nombre = 'Consultorio de prueba'): Consultorio
    {
        return Consultorio::factory()->create(['nombre' => $nombre]);
    }

    /**
     * Una persona con su cuenta.
     *
     * Un administrativo tiene `profesional_id` en `null`, y por eso no puede
     * abrir el contenido de ninguna sesión. Eso no es una consecuencia de la
     * prueba: es el diseño. Ver docs/03.
     */
    protected function crearUsuario(
        Consultorio $consultorio,
        RolClinico $rol,
        ?Profesional $profesional = null,
    ): Usuario {
        return $this->conContexto($consultorio->id, fn (): Usuario => Usuario::factory()->create([
            'tenant_id' => $consultorio->id,
            'rol' => $rol->value,
            'profesional_id' => $profesional?->id,
        ]));
    }

    /**
     * Un profesional con su cuenta de tratante.
     *
     * @return array{0: Usuario, 1: Profesional}
     */
    protected function crearProfesional(Consultorio $consultorio, string $nombreAgenda = 'Profesional de prueba'): array
    {
        return $this->conContexto($consultorio->id, function () use ($consultorio, $nombreAgenda): array {
            $profesional = Profesional::factory()->create([
                'tenant_id' => $consultorio->id,
                'nombre_agenda' => $nombreAgenda,
            ]);

            $usuario = Usuario::factory()->create([
                'tenant_id' => $consultorio->id,
                'rol' => RolClinico::Profesional->value,
                'profesional_id' => $profesional->id,
            ]);

            return [$usuario, $profesional];
        });
    }

    /**
     * Un tipo de atención con su duración.
     */
    protected function crearTipoAtencion(
        Consultorio $consultorio,
        string $nombre = 'Individual',
        int $duracion = 50,
    ): TipoAtencion {
        return $this->conContexto($consultorio->id, fn (): TipoAtencion => TipoAtencion::factory()->create([
            'tenant_id' => $consultorio->id,
            'nombre' => $nombre,
            'codigo' => 'T-' . str($nombre)->upper()->slug('-')->value(),
            'duracion_minutos' => $duracion,
        ]));
    }

    /**
     * Un paciente con consentimiento de tratamiento vigente.
     *
     * Sin consentimiento, `ServicioAgenda` no deja agendar. Verificar eso en
     * la propia prueba, en vez de poner el consentimiento a mano, es lo que
     * mantiene el dato honesto.
     */
    protected function crearPacienteConConsentimiento(Consultorio $consultorio): Paciente
    {
        return $this->conContexto($consultorio->id, function () use ($consultorio): Paciente {
            $paciente = Paciente::factory()->create(['tenant_id' => $consultorio->id]);

            Consentimiento::factory()->create([
                'tenant_id' => $consultorio->id,
                'paciente_id' => $paciente->id,
                'tipo' => TipoConsentimiento::Tratamiento->value,
                'version_documento' => 'v1',
                'modalidad' => 'escrita',
            ]);

            return $paciente;
        });
    }
}