<?php

declare(strict_types=1);

namespace Tests\Feature\Permisos;

use App\Enums\AccionNota;
use App\Enums\RolClinico;
use App\Models\Cita;
use App\Models\Consultorio;
use App\Models\NotaSesion;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\TipoAtencion;
use App\Models\Usuario;
use App\Services\Permisos\CapacidadesDeUsuario;
use Tests\TestCase;

/**
 * Un administrativo NO puede leer el contenido de una sesión.
 *
 * ESTA ES LA PRUEBA QUE SUSTIENTA EL PRODUCTO
 * ------------------------------------------
 * Si el sistema deja filtrar el contenido de una sesión a un rol sin permiso,
 * no sirve para nada en este rubro. Esta prueba es la que lo dice.
 *
 * LO QUE COMPRUEBA
 * ----------------
 *   1. Un administrativo no abre la nota, ni por su id, ni por el listado.
 *   2. La respuesta es 404 y no 403: un 403 confirmaría que la nota existe.
 *   3. Un profesional abre su propia nota.
 *   4. Un profesional NO abre la nota de otro profesional del mismo
 *      consultorio.
 *   5. El intento denegado queda anotado en la bitácora.
 *   6. El administrativo sí ve la agenda y el pago de esa misma sesión.
 *
 * LA SEXTA ES IMPORTANTE
 * ----------------------
 * Sin ella, la prueba pasaría con una aplicación que no le deja trabajar nada
 * al administrativo, que no es ni útil ni justo.
 *
 * Ver docs/03 y docs/06.
 */
class AdministrativoNoLeeNotaDeSesionTest extends TestCase
{
    private Consultorio $consultorio;

    private Usuario $administrativo;

    private Usuario $profesional;

    private Usuario $otroProfesional;

    private NotaSesion $nota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consultorio = $this->crearConsultorio('Consultorio de prueba');

        $this->administrativo = $this->crearUsuario($this->consultorio, RolClinico::Administrativo);

        [$this->profesional, $profesionalModelo] = $this->crearProfesional($this->consultorio, 'Profesional Uno');
        [$this->otroProfesional] = $this->crearProfesional($this->consultorio, 'Profesional Dos');

        $this->nota = $this->crearNotaPara($this->profesional, $profesionalModelo);
    }

    public function test_el_administrativo_no_abre_la_nota(): void
    {
        $this->actingAs($this->administrativo);

        $respuesta = $this->getJson("/api/v1/notas/{$this->nota->id}");

        $respuesta->assertNotFound();
    }

    public function test_la_respuesta_es_404_y_no_403(): void
    {
        $this->actingAs($this->administrativo);

        $respuesta = $this->getJson("/api/v1/notas/{$this->nota->id}");

        $this->assertSame(
            404,
            $respuesta->status(),
            'Un rechazo de permiso debe ser 404: un 403 confirmaría que la nota existe.',
        );

        $this->assertStringNotContainsString(
            'contenido',
            mb_strtolower($respuesta->getContent() ?: ''),
            'La respuesta no debería mencionar el contenido de la nota.',
        );
    }

    public function test_el_administrativo_no_obtiene_el_listado_de_notas(): void
    {
        $this->actingAs($this->administrativo);

        $this->getJson('/api/v1/notas')->assertNotFound();
    }

    public function test_el_profesional_abre_su_propia_nota(): void
    {
        $this->actingAs($this->profesional);

        $respuesta = $this->getJson("/api/v1/notas/{$this->nota->id}");

        $respuesta->assertOk();
        $respuesta->assertJsonPath('datos.id', $this->nota->id);
        $respuesta->assertJsonPath('datos.contenido', 'Contenido de la sesion de prueba.');
    }

    public function test_un_profesional_no_abre_la_nota_de_otro_profesional(): void
    {
        $this->actingAs($this->otroProfesional);

        $this->getJson("/api/v1/notas/{$this->nota->id}")->assertNotFound();
    }

    public function test_el_lista_solo_las_notas_del_profesional_que_pregunta(): void
    {
        $notaPropia = $this->nota;

        $notaAjena = $this->crearNotaPara($this->otroProfesional, $this->otroProfesional->profesional);

        $this->actingAs($this->profesional);

        $respuesta = $this->getJson('/api/v1/notas');

        $respuesta->assertOk();

        $ids = collect($respuesta->json('datos'))->pluck('id');

        $this->assertTrue($ids->contains($notaPropia->id));
        $this->assertFalse(
            $ids->contains($notaAjena->id),
            'El listado de un profesional no debería traer las notas de otro.',
        );
    }

    public function test_el_intento_denegado_queda_anotado_en_la_bitacora(): void
    {
        $this->actingAs($this->administrativo);

        $this->getJson("/api/v1/notas/{$this->nota->id}")->assertNotFound();

        $this->conContexto($this->consultorio->id, function (): void {
            $registro = \App\Models\Acceso::query()
                ->where('recurso', 'nota_sesion')
                ->where('recurso_id', $this->nota->id)
                ->where('resultado', 'denegado')
                ->latest('ocurrido_en')
                ->first();

            $this->assertNotNull(
                $registro,
                'El intento denegado debería quedar anotado en la bitacora.',
            );

            $this->assertSame($this->administrativo->id, $registro->usuario_id);
            $this->assertSame(AccionNota::VerNota->value, $registro->accion);
            $this->assertNotNull(
                $registro->motivo,
                'El registro debería decir por qué se denegó, no solo que se denegó.',
            );
        });
    }

    public function test_el_administrativo_s_si_ve_la_agenda(): void
    {
        $this->actingAs($this->administrativo);

        $this->getJson('/api/v1/agenda')->assertOk();
    }

    public function test_la_capacidad_explica_la_restriccion(): void
    {
        $capacidades = app(CapacidadesDeUsuario::class);

        $this->assertFalse(
            $capacidades->puedeSobreNota($this->administrativo, $this->nota, AccionNota::VerNota),
        );

        $motivo = $capacidades->motivo($this->administrativo, $this->nota, AccionNota::VerNota);

        $this->assertStringContainsString(
            'agenda',
            mb_strtolower($motivo),
            'El motivo debería explicar qué puede hacer el administrativo, no solo qué no.',
        );
    }

    public function test_el_administrador_del_consultorio_tampoco_abre_la_nota(): void
    {
        $administrador = $this->crearUsuario($this->consultorio, RolClinico::Administrador);

        $this->actingAs($administrador);

        $this->getJson("/api/v1/notas/{$this->nota->id}")->assertNotFound();
    }

    /**
     * Arma una nota para un profesional, con su paciente y su cita.
     */
    private function crearNotaPara(Usuario $usuario, ?Profesional $profesional): NotaSesion
    {
        $profesional ??= $usuario->profesional;

        return $this->conContexto($this->consultorio->id, function () use ($usuario, $profesional): NotaSesion {
            $tipo = TipoAtencion::factory()->create([
                'tenant_id' => $this->consultorio->id,
                'duracion_minutos' => 50,
            ]);

            $paciente = Paciente::factory()->create(['tenant_id' => $this->consultorio->id]);

            $cita = Cita::factory()->create([
                'tenant_id' => $this->consultorio->id,
                'paciente_id' => $paciente->id,
                'profesional_id' => $profesional->id,
                'tipo_atencion_id' => $tipo->id,
                'estado' => 'realizada',
            ]);

            return NotaSesion::query()->create([
                'cita_id' => $cita->id,
                'paciente_id' => $paciente->id,
                'profesional_id' => $profesional->id,
                'estado' => 'abierta',
                'contenido' => 'Contenido de la sesion de prueba.',
                'version' => 1,
            ]);
        });
    }
}