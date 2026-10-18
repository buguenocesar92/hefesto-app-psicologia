<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Reglas de Psicología
|------------------------------------------------------------------------------
|
| Lo que la aplicación necesita saber del consultorio y que no cabe en el
| código. Cada clave está justificada al lado. Ver docs/05.
|
*/

$enteroONulo = static function (?string $valor): ?int {
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);

    return $valor === '' ? null : (int) $valor;
};

return [

    /*
    |----------------------------------------------------------------------
    | Agenda
    |----------------------------------------------------------------------
    |
    | La duración de una sesión NO es una constante de la aplicación: sale de
    | `tipos_atencion.duracion_minutos`, que es lo que el consultorio declara
    | para cada tipo de atención. Estos dos límites son la red de seguridad
    | para cuando el catálogo tiene un dato raro.
    |
    */
    'agenda' => [
        'duracion_maxima_minutos' => (int) env('PSICOLOGIA_DURACION_MAXIMA_MINUTOS', 180),
        'anticipacion_maxima_dias' => (int) env('PSICOLOGIA_ANTICIPACION_MAXIMA_DIAS', 180),
    ],

    /*
    |----------------------------------------------------------------------
    | INSTRUMENTOS: PUNTAJES DE CORTE
    |----------------------------------------------------------------------
    |
    | ESTA ES LA PARTE MÁS DELICADA DE ESTA CONFIGURACIÓN. LÉALA ANTES DE
    | CAMBIAR NADA.
    |
    | Un puntaje de corte es un dato CLÍNICO. Si el sistema propone "28 puntos
    | o más es probable depresión", está afirmando algo sobre una persona, y
    | un corte equivocado cambia un diagnóstico o, peor, tranquiliza a quien
    | debería ser derivado.
    |
    | NO hay ningún valor por omisión. Ni "el habitual", ni "el de la norma
    | chilena", ni "el del manual". Un número escrito sin fuente es un número
    | inventado, y esta aplicación no inventa datos clínicos.
    |
    | El corte de cada instrumento vive en la fila del instrumento, en
    | `instrumentos.puntaje_corte`, y NACEn en `null`. Para que pase a
    | `corte_verificado = true` la base exige dos cosas: una fuente escrita y
    | una referencia de verificación. Eso lo impone un disparador, no una
    | convención. Ver la migración `bloquear_corte_sin_fuente`.
    |
    | Si el consultorio necesita un corte por omisión para sus propias
    | tablas, se configura acá y el sistema lo declara como "corte interno del
    | consultorio, sin fuente externa", que es una categoría distinta de
    | "corte verificado contra la norma".
    |
    | Ver docs/06.
    |
    */
    'instrumentos' => [
        // Días que se recuerda al profesional que hay una aplicación de
        // instrumento sin puntaje cargado. NO es un plazo clínico: es una
        // ayuda de trabajo. En null, no se recuerda nada.
        'dias_recordatorio_sin_puntaje' => $enteroONulo(env('PSICOLOGIA_DIAS_RECORDATORIO_INSTRUMENTO')),

        // Máximo de caracteres del texto de interpretación que escribe el
        // profesional. Es una ayuda de captura, no un criterio clínico.
        'interpretacion_maximo_caracteres' => 4000,
    ],

    /*
    |----------------------------------------------------------------------
    | NOTAS DE SESIÓN
    |----------------------------------------------------------------------
    |
    | La nota es editable mientras está abierta y se bloquea al cerrarse. No
    | hay ninguna excepción configurable: una nota cerrada que se pudiera
    | editar sería tan reescribible como una libreta.
    |
    | La corrección de una nota cerrada NO es una edición: es una corrección
    | auditada, que escribe el texto anterior, el nuevo y el motivo, y sube la
    | versión. Lo que se corrige queda a la vista. Ver docs/06.
    |
    */
    'notas' => [
        // Caracteres máximos del contenido de una nota. Es un límite técnico de
        // captura, no un criterio clínico.
        'contenido_maximo_caracteres' => 20000,

        // Caracteres mínimos del motivo de una corrección. Nadie escribe "error"
        // y se entiende por qué el registro cambió; sin un motivo mínimo, la
        // corrección sirve para borrar sin dejar rastro.
        'motivo_correccion_minimo' => 15,
    ],

    /*
    |----------------------------------------------------------------------
    | DERIVACIONES POR CRISIS
    |----------------------------------------------------------------------
    |
    | Una derivación NO SE BORRA. Ni desde la interfaz, ni desde la consola, ni
    | con una llave de la base: hay un disparador que rechaza el DELETE.
    |
    | Lo que sí se puede es cambiar su estado (vigente, atendida, resuelta) y
    | dejar constancia de por qué. El registro original permanece.
    |
    | Ver docs/06: esto es una obligación profesional, no una función opcional
    | del producto.
    |
    */
    'derivaciones' => [
        // Motivo obligatorio, con un mínimo de caracteres. Es una ayuda para
        // que quede escrito por qué se derivó, que es lo que después sostiene
        // la decisión.
        'motivo_minimo_caracteres' => 15,

        // El destino se escribe a mano, no se elige de una lista cerrada: los
        // dispositivos de derivación son locales y cambian. Este es el mínimo
        // de caracteres del texto.
        'destino_minimo_caracteres' => 5,
    ],

    /*
    |----------------------------------------------------------------------
    | CONSERVACIÓN: DECISIÓN DEL CLIENTE, NO DEL SOFTWARE
    |----------------------------------------------------------------------
    |
    | ESTA ES LA SEGUNDA PARTE MÁS DELICADA DE ESTA CONFIGURACIÓN.
    |
    | La Ley 19.628 obliga a que la conservación de datos personales tenga una
    | finalidad declarada, pero NO se pudo verificar en el entorno donde se
    | escribió esta aplicación el plazo que corresponde a una ficha de salud
    | mental. Peor aún: aunque se verificara, no hay un plazo único que sirva
    | para todos los datos de un consultorio. Ver docs/06.
    |
    | Por eso el valor por omisión es `null`, y `null` significa exactamente:
    |
    |     "no hay plazo configurado".
    |
    | Que es lo que dice el campo, y no un número inventado con buena intención.
    | Si el consultorio carga notas de sesión y su asesoría legal revisa el
    | caso, el plazo se configura por instalación y queda escrito en el
    | documento de tratamiento de datos de ESE consultorio.
    |
    | La consecuencia de dejar `null` es deliberada: la aplicación NO purga
    | nada por su cuenta. Borrar historia clínica de salud mental porque pasó
    | un número inventado sería peor que no borrar nunca.
    |
    */
    'conservacion' => [
        // Días que se conservan las notas de sesión. En null, no se purga nada
        // y queda como decisión pendiente del cliente.
        'notas_sesion_dias' => $enteroONulo(env('PSICOLOGIA_RETENCION_NOTAS_DIAS')),

        // Días que se conservan los registros de acceso. En null, no se purga
        // nada.
        'accesos_dias' => $enteroONulo(env('PSICOLOGIA_RETENCION_ACCESOS_DIAS')),

        // Días que se conservan las derivaciones. En null, no se purga nada.
        // Recuérdese que igual no se pueden borrar: el borrado manual está
        // prohibido en todo caso.
        'derivaciones_dias' => $enteroONulo(env('PSICOLOGIA_RETENCION_DERIVACIONES_DIAS')),

        // Si la aplicación avisa al cliente que hay algo por purgar. En false,
        // no avisa: el sistema nunca borra historia clínica por su cuenta.
        'avisar_antes_de_purgar' => (bool) env('PSICOLOGIA_AVISAR_ANTES_DE_PURGAR', false),
    ],

    /*
    |----------------------------------------------------------------------
    | Alertas
    |----------------------------------------------------------------------
    |
    | Umbrales para avisar por volumen de intentos denegados. Los umbrales
    | existen en la configuración; el AVISO AUTOMÁTICO NO ESTÁ IMPLEMENTADO
    | (no hay correo saliente). Ver docs/10.
    |
    */
    'alertas' => [
        'denegados_por_hora' => (int) env('PSICOLOGIA_ALERTA_DENEGADOS_POR_HORA', 20),
        'denegados_por_usuario' => (int) env('PSICOLOGIA_ALERTA_DENEGADOS_POR_USUARIO', 5),
    ],

    /*
    |----------------------------------------------------------------------
    | Zona horaria por omisión
    |----------------------------------------------------------------------
    |
    | `consultorios` NO tiene zona horaria: es un dato de infraestructura y
    | todas las instalaciones de Hefesto corren en America/Santiago. Si alguna
    | vez hay una instalación fuera, es una migración, no una variable.
    |
    */
    'zona_horaria' => env('APP_TIMEZONE', 'America/Santiago'),

];