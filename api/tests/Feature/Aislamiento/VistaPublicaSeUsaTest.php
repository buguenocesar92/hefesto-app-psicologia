<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La vista pública se usa de verdad en la pantalla de ingreso.
 *
 * POR QUÉ ESTA PRUEBA EXISTE
 * --------------------------
 * En siete aplicaciones anteriores de Hefesto se creó la vista
 * `vw_<entidad>s_publicas`, se documentó que el ingreso la consultaba, y nunca
 * se consultó. La documentación afirmaba una protección que no existía.
 *
 * Esta prueba cierra ese hueco en esta aplicación: comprueba que la vista
 * existe, que expone tres columnas y no más, y que la ruta del ingreso la
 * usa de verdad.
 *
 * Ver docs/03.
 */
class VistaPublicaSeUsaTest extends TestCase
{
    /**
     * La vista existe.
     */
    public function test_la_vista_publica_existe(): void
    {
        $existe = DB::selectOne("SELECT 1 FROM pg_views WHERE viewname = 'vw_consultorios_publicas'");

        $this->assertNotNull(
            $existe,
            'La vista vw_consultorios_publicas no existe. El ingreso la necesita.',
        );
    }

    /**
     * Expone tres columnas, y solo tres.
     *
     * Si mañana alguien le agrega una columna a la vista, esta prueba falla. Y
     * tiene que fallar: una vista que expone el RUT o el correo del
     * consultorio en la pantalla de ingreso no es una vista pública.
     */
    public function test_la_vista_expone_solo_tres_columnas(): void
    {
        $columnas = DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = 'vw_consultorios_publicas'
             ORDER BY ordinal_position",
        );

        $nombres = array_map(fn ($fila): string => $fila->column_name, $columnas);

        $this->assertSame(
            ['id', 'nombre', 'slug'],
            $nombres,
            'La vista pública debería exponer exactamente tres columnas.',
        );
    }

    /**
     * Un consultorio inactivo no aparece.
     */
    public function test_un_consultorio_inactivo_no_aparece_en_la_vista(): void
    {
        $activo = $this->crearConsultorio('Consultorio activo');
        $inactivo = $this->crearConsultorio('Consultorio inactivo');
        $inactivo->forceFill(['activo' => false])->save();

        $visibles = DB::table('vw_consultorios_publicas')->pluck('slug');

        $this->assertTrue($visibles->contains($activo->slug));
        $this->assertFalse(
            $visibles->contains($inactivo->slug),
            'Un consultorio dado de baja no debería aparecer en la pantalla de ingreso.',
        );
    }

    /**
     * La ruta del ingreso usa la vista, no la tabla.
     *
     * Esta es la prueba que evita el defecto de las aplicaciones anteriores.
     * Si alguien cambia `ConsultoriosPublicosController` para leer la tabla,
     * el contenido de la respuesta cambia y esta prueba lo nota.
     */
    public function test_la_ruta_del_ingreso_devuelve_solo_las_tres_columnas(): void
    {
        $this->crearConsultorio('Consultorio visible');

        $respuesta = $this->getJson('/api/v1/consultorios-publicos');

        $respuesta->assertOk();

        $datos = $respuesta->json('datos');

        $this->assertNotEmpty($datos, 'El ingreso debería poder listar al menos un consultorio.');

        foreach ($datos as $fila) {
            $this->assertSame(
                ['id', 'nombre', 'slug'],
                array_keys($fila),
                'La respuesta del ingreso no debería traer más que identificador, nombre y slug.',
            );
        }
    }

    /**
     * La ruta del ingreso no pide sesión.
     *
     * Tiene que ser así: en el ingreso todavía no hay sesión, y si la ruta la
     * pidiera, la pantalla de ingreso no tendría a dónde ir.
     */
    public function test_la_ruta_del_ingreso_no_exige_sesion(): void
    {
        $respuesta = $this->getJson('/api/v1/consultorios-publicos');

        $this->assertNotSame(401, $respuesta->status());
        $this->assertNotSame(403, $respuesta->status());
    }
}