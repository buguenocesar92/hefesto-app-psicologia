<?php

declare(strict_types=1);

namespace App\Services\Permisos;

use App\Enums\AccionNota;
use App\Enums\RolClinico;
use App\Models\NotaSesion;
use App\Models\Usuario;

/**
 * Qué puede hacer cada rol, y por qué no puede hacer lo demás.
 *
 * ESTA CLASE ES EL PRODUCTO
 * -------------------------
 * En una agenda con una caja de texto, los permisos son lo de menos. Acá el
 * contenido de una sesión de terapia es el dato más sensible que existe, y la
 * regla central es:
 *
 *     Un profesional abre el contenido de las sesiones de SUS pacientes.
 *     Un administrativo NUNCA lo abre.
 *
 * No "con menos permisos": nunca. El administrativo tiene su propio trabajo, que
 * es real —la agenda y los pagos—, y por eso no es un rol degradado.
 *
 * POR QUÉ ESTO NO ESTÁ EN LAS POLICIES SOLAS
 * ------------------------------------------
 * Porque la interfaz necesita el POR QUÉ, no solo el sí o el no. Una pantalla
 * que dice "no tiene permiso" obliga a la persona a preguntar; una que dice
 * "los administrativos ven la agenda y los pagos, no el contenido de las
 * sesiones" se explica sola. Por eso cada respuesta de este archivo trae un
 * texto, y ese texto es el que la pantalla muestra.
 *
 * Ver docs/03.
 */
class CapacidadesDeUsuario
{
    /**
     * Las tres razones por las que se puede negar el acceso a una nota.
     *
     * Se nombran para que el mensaje sea el correcto y no uno genérico.
     */
    private const RAZON_SIN_TRATANTE = 'sin_tratante';
    private const RAZON_OTRO_PROFESIONAL = 'otro_profesional';
    private const RAZON_NOTA_CERRADA = 'nota_cerrada';

    /**
     * Si esta persona puede abrir el contenido de las sesiones.
     *
     * La pregunta no es "¿es profesional?", es "¿atiende?". Un administrador
     * puede tener el rol `profesional` en algún lado y no tener ficha de
     * profesional acá, y lo que cuenta es la ficha.
     */
    public function puedeAbrirContenidoDeSesiones(Usuario $usuario): bool
    {
        return $usuario->esTratante();
    }

    /**
     * Si esta persona puede hacer esta acción sobre ESTA nota.
     */
    public function puedeSobreNota(Usuario $usuario, ?NotaSesion $nota, AccionNota $accion): bool
    {
        // Sin ficha de profesional, nada de contenido de sesión. Punto.
        if (! $usuario->esTratante()) {
            return false;
        }

        // Para el listado y la creación no hay nota todavía: basta con que
        // atienda.
        if ($nota === null) {
            return true;
        }

        // Un profesional abre SUS notas. Las de otro profesional del mismo
        // consultorio, no.
        //
        // Esto no se aparta: hay consultorios donde los profesionales se
        // cubren, y ahí lo que corresponde es que el administrador del
        // consultorio registre la derivación interna con su motivo. Acortar
        // esto "por comodidad" sería abrir la puerta a que cualquiera lea lo
        // que se dijo en una sesión.
        if ($nota->profesional_id !== $usuario->profesional_id) {
            return false;
        }

        // Editar y cerrar solo funcionan sobre una nota abierta. La corrección
        // de una nota cerrada es una acción aparte, y sí se permite.
        if (in_array($accion, [AccionNota::EditarNota, AccionNota::CerrarNota], true)) {
            return $nota->estado->admiteEdicion();
        }

        return true;
    }

    /**
     * Por qué se le niega, en palabras que la interfaz pueda mostrar.
     */
    public function motivo(Usuario $usuario, ?NotaSesion $nota, AccionNota $accion): string
    {
        if (! $usuario->esTratante()) {
            return $usuario->rol?->accesoAlContenidoDeSesion()
                ?? 'Esta cuenta no atiende, así que no abre el contenido de las sesiones.';
        }

        if ($nota !== null && $nota->profesional_id !== $usuario->profesional_id) {
            return 'Esta nota es de otro profesional. Un profesional abre el contenido de las sesiones de los pacientes que atiende, no las de los demás.';
        }

        if (
            $nota !== null
            && in_array($accion, [AccionNota::EditarNota, AccionNota::CerrarNota], true)
            && ! $nota->estado->admiteEdicion()
        ) {
            return 'La nota está cerrada y no se puede editar. Para cambiar lo que dice, hay que registrar una corrección con su motivo: eso deja constancia de que cambió y por qué.';
        }

        return 'Sin permiso para esta acción.';
    }

    /**
     * Un identificador corto de la razón, para el registro del sistema.
     */
    public function codigoDeMotivo(Usuario $usuario, ?NotaSesion $nota, AccionNota $accion): string
    {
        if (! $usuario->esTratante()) {
            return self::RAZON_SIN_TRATANTE;
        }

        if ($nota !== null && $nota->profesional_id !== $usuario->profesional_id) {
            return self::RAZON_OTRO_PROFESIONAL;
        }

        return self::RAZON_NOTA_CERRADA;
    }

    /**
     * Qué ve esta persona en la pantalla de inicio.
     *
     * La idea es que el panel diga lo que el rol PUEDE ver. Si a un
     * administrativo no le corresponde ninguna nota, la respuesta no trae el
     * conteo de notas: no es que haya cero, es que no es suyo.
     */
    public function panel(Usuario $usuario): array
    {
        $base = [
            'rol' => $usuario->rol?->value,
            'rol_etiqueta' => $usuario->rol?->etiqueta(),
            'es_tratante' => $usuario->esTratante(),
            've_agenda' => true,
            've_pagos' => true,
            've_notas' => $usuario->esTratante(),
            'explicacion' => $this->explicacionDelRol($usuario),
        ];

        return $base;
    }

    /**
     * La frase que la pantalla de inicio muestra sobre el rol.
     */
    public function explicacionDelRol(Usuario $usuario): string
    {
        return match ($usuario->rol) {
            RolClinico::Profesional => 'Usted atiende. Ve su agenda, sus pacientes, las notas de las sesiones que usted condujo y los pagos del consultorio.',
            RolClinico::Administrativo => 'Usted ve la agenda y los pagos. No abre el contenido de las sesiones: eso es del profesional que las condujo.',
            RolClinico::Administrador => 'Usted administra el consultorio: profesionales, tarifas, tipos de atención y la bitácora de accesos. El contenido de las sesiones no se abre por el cargo.',
            null => 'Su cuenta no tiene un rol asignado. Consulte con la dirección del consultorio.',
        };
    }
}