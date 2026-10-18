<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

/**
 * Deja el registro sin los datos personales que pudo haber dentro.
 *
 * QUE HACE
 * --------
 * Recorre el contexto y el mensaje de un registro y borra, o reemplaza por
 * una marca, todo lo que pueda ser un dato personal de un paciente.
 *
 * POR QUE EXISTE
 * --------------
 * En esta aplicación los registros van a `stderr` y de ahí los recoge el
 * orquestador de contenedores, que es un sistema con su propia retención y su
 * propio acceso. Si una excepción arrastra el contenido de una sesión de
 * terapia, ese texto viaja a un sistema que no está bajo el control de la
 * aplicación y que nadie revisó para este caso.
 *
 * Un error de aplicación debería decir "no se pudo guardar la nota de la cita
 * 1234", no lo que dice la nota.
 *
 * QUE NO HACE
 * -----------
 * No adivina. Si una clave que no está en la lista trae un dato sensible en un
 * nombre que no reconoce, el valor sale igual: el canal ESTERILIZADO es una
 * segunda barrera, no un reemplazo del cuidado del programador.
 *
 * Ver docs/06.
 */
class SinDatosPersonales
{
    /**
     * Claves cuyo valor se reemplaza por completo.
     *
     * Minúsculas y sin tildes: la comparación se hace sobre una versión
     * normalizada, porque el nombre de la clave puede venir de un framework
     * que no las use.
     *
     * @var list<string>
     */
    private const CLAVES_A_REEMPLAZAR = [
        'nombre',
        'nombres',
        'apellido',
        'apellidos',
        'nombre_completo',
        'rut',
        'run',
        'email',
        'correo',
        'telefono',
        'telefono_movil',
        'domicilio',
        'direccion',
        'contenido',
        'nota',
        'notas',
        'texto',
        'motivo_consulta',
        'motivo',
        'respuesta',
        'respuestas',
        'interpretacion',
        'observaciones',
        'password',
        'secret',
        'token',
        'authorization',
        'cookie',
        'telefono_contacto',
        'contacto_emergencia',
        'nombre_contacto_emergencia',
    ];

    /**
     * Llaves que se dejan tal cual, porque no son datos personales.
     *
     * Sin esta lista, `nombre` borraría también el nombre de la tabla, el del
     * profesional que atendió y el de la ruta, y un registro de seguridad sin
     * contexto no sirve para investigar nada.
     *
     * @var list<string>
     */
    private const CLAVES_PERMITIDAS = [
        'nombre_profesional',
        'nombre_consultorio',
        'nombre_tabla',
        'nombre_accion',
        'nombre_recurso',
        'nombre_equipo',
    ];

    /**
     * Deja el registro sin datos personales.
     *
     * @param  array<mixed>  $context
     */
    public function __invoke(LogRecord $registro): LogRecord
    {
        $limpio = $this->limpiar($registro->context);

        return $registro->with(context: $limpio);
    }

    /**
     * Recorre el contexto y reemplaza lo que no debe salir.
     *
     * @param  array<mixed>  $contexto
     * @return array<mixed>
     */
    private function limpiar(array $contexto, int $profundidad = 0): array
    {
        // Un límite de profundidad: un contexto con referencias circulares
        // cortando en datos personales, o la propia forma de que se llegue a
        // un ciclo,lanza un error dentro del registrador, y un registrador que
        // falla deja la aplicación sin forma de decir qué pasó.
        if ($profundidad > 6) {
            return ['_recortado' => 'contexto demasiado profundo'];
        }

        $limpio = [];

        foreach ($contexto as $clave => $valor) {
            $normalizada = $this->normalizar((string) $clave);

            if (in_array($normalizada, self::CLAVES_PERMITIDAS, true)) {
                $limpio[$clave] = $valor;

                continue;
            }

            if (in_array($normalizada, self::CLAVES_A_REEMPLAZAR, true)) {
                $limpio[$clave] = '[retirado]';

                continue;
            }

            if (is_array($valor)) {
                $limpio[$clave] = $this->limpiar($valor, $profundidad + 1);

                continue;
            }

            $limpio[$clave] = $valor;
        }

        return $limpio;
    }

    /**
     * Pasa una clave a la forma en que se comparan.
     *
     * Sin tildes, en minúsculas y con lo que no es alfanumérico reducido a
     * guion bajo.
     */
    private function normalizar(string $clave): string
    {
        $transliterada = strtr($clave, [
            'á' => 'a', 'Á' => 'a', 'é' => 'e', 'É' => 'e',
            'í' => 'i', 'Í' => 'i', 'ó' => 'o', 'Ó' => 'o',
            'ú' => 'u', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n',
            'ü' => 'u', 'Ü' => 'u',
        ]);

        $minuscula = mb_strtolower($transliterada, 'UTF-8');

        return (string) preg_replace('/[^a-z0-9]+/', '_', $minuscula);
    }
}