<template>
    <div>
        <header v-if="usuario" class="barra-superior">
            <div class="barra-superior__contenido">
                <NuxtLink to="/" class="barra-superior__marca">
                    Psicología
                </NuxtLink>

                <span v-if="usuario.consultorio" class="barra-superior__consultorio">
                    {{ usuario.consultorio.nombre }}
                </span>

                <div class="barra-superior__sesion">
                    <span>
                        {{ usuario.nombre }}
                        <template v-if="usuario.rol_etiqueta">
                            &mdash; {{ usuario.rol_etiqueta }}
                        </template>
                    </span>

                    <button type="button" class="boton boton--neutro" @click="salir">
                        Salir
                    </button>
                </div>
            </div>

            <nav class="menu" aria-label="Secciones">
                <NuxtLink
                    v-for="enlace in enlaces"
                    :key="enlace.ruta"
                    :to="enlace.ruta"
                    class="menu__enlace"
                    active-class="menu__enlace--activo"
                >
                    {{ enlace.etiqueta }}
                </NuxtLink>
            </nav>
        </header>

        <main class="contenedor">
            <slot />
        </main>
    </div>
</template>

<script setup lang="ts">
/**
 * La estructura de la aplicación.
 *
 * POR QUE EL MENÚ SE ARMA CON EL PERFIL Y NO FIJO
 * ----------------------------------------------
 * Porque hay una sección entera que no todos pueden ver: las notas de sesión.
 * Mostrarla en el menú y que dé 404 al abrirla es una forma de hacer que la
 * persona dude de si el sistema funciona.
 *
 * Ocultarla no es seguridad: quien sepa escribir la URL la escribe, y lo que
 * encuentra es un 404 que no confirma nada. Ver docs/03.
 *
 * POR QUE EL ROL APARECE EN LA BARRA
 * ---------------------------------
 * Porque en esta aplicación qué ve la persona depende de quién es, y eso no
 * puede quedar en la cabeza de cada uno. Ver docs/06.
 */

const { usuario, salir } = useSesion()

/**
 * Las secciones, según el rol.
 *
 * `notas` solo aparece si la persona atiende. Para todos los demás, la
 * explicación de por qué no está está en `aclaracionDeAlcance`.
 */
const enlaces = computed(() => {
    const base = [
        { ruta: '/', etiqueta: 'Inicio' },
        { ruta: '/agenda', etiqueta: 'Agenda' },
        { ruta: '/pacientes', etiqueta: 'Pacientes' },
        { ruta: '/instrumentos', etiqueta: 'Instrumentos' },
        { ruta: '/derivaciones', etiqueta: 'Derivaciones' },
        { ruta: '/pagos', etiqueta: 'Pagos' },
    ]

    if (veNotas()) {
        base.push({ ruta: '/notas', etiqueta: 'Notas de sesión' })
    }

    if (usuario.value?.rol === 'administrador') {
        base.push({ ruta: '/auditoria', etiqueta: 'Accesos' })
    }

    return base
})

const veNotas = (): boolean => usuario.value?.ve_notas ?? false
</script>