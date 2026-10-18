<template>
    <div class="contenedor">
        <div class="tarjeta">
            <h1 class="tarjeta__titulo">
                Ingreso al consultorio
            </h1>

            <p v-if="cargando" class="aviso">
                Cargando...
            </p>

            <template v-else>
                <div v-if="entrarError" class="alerta">
                    {{ entrarError }}
                </div>

                <p class="aviso" style="margin-top: 0.5rem">
                    Elija su consultorio para entrar.
                </p>

                <p v-if="consultorios.length === 0" class="alerta">
                    No hay ningún consultorio disponible. Consulte con la dirección del estudio.
                </p>

                <ul v-else class="tarjeta">
                    <li v-for="consultorio in consultorios" :key="consultorio.id">
                        <button type="button" class="boton boton--neutro" @click="entrar(consultorio.slug)">
                            {{ consultorio.nombre }}
                        </button>
                    </li>
                </ul>
            </template>
        </div>

        <div class="restringido">
            <p class="restringido__titulo">
                Por qué esta pantalla no pide usuario y contraseña
            </p>
            <p>
                El ingreso lo resuelve el proveedor de identidad del estudio. La aplicación no guarda
                contraseñas, y por eso tampoco hay una casilla donde escribirlas. Si su sesión no abre,
                probablemente su cuenta no esté asociada a un consultorio.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * La pantalla de ingreso.
 *
 * DE DÓNDE VIENEN LOS CONSULTORIOS
 * --------------------------------
 * De `GET /api/v1/consultorios-publicos`, que consulta la vista
 * `vw_consultorios_publicas`. Esa vista expone tres columnas, y por eso esta
 * pantalla no puede mostrar ni el RUT ni el correo de un consultorio.
 *
 * QUE HACE LA SELECCIÓN
 * --------------------
 * Elija un consultorio lleva a la pantalla del proveedor de identidad. El
 * consultorio no es la credencial: es un dato que se guarda en el estado de la
 * pantalla para volver del ingreso y volver a pedir los datos.
 *
 * La seguridad no está acá. Está en que el proveedor de identidad verifica
 * quién es la persona y en qué equipos está, y la aplicación arma la sesión a
 * partir de eso. Ver docs/03.
 */

import type { Consultorio } from '~/tipos/api'

definePageMeta({ layout: false })

const cargando = ref(true)
const entrarError = ref<string | null>(null)
const consultorios = ref<Consultorio[]>([])
const consultorioElegido = ref<string | null>(null)

onMounted(async () => {
    try {
        const respuesta = await $fetch<{ datos: Consultorio[] }>('/api/consultorios-publicos')

        consultorios.value = respuesta.datos
    } catch {
        entrarError.value = 'No se pudieron cargar los consultorios. Intente de nuevo en un momento.'
    } finally {
        cargando.value = false
    }
})

/**
 * Pide al servidor la dirección del proveedor de identidad y va.
 *
 * El servidor es quien arma la URL: en el navegador no hay ningún secreto, y
 * el `client_id` no es un secreto tampoco, pero dejar el armado en el servidor
 * evita que la interfaz y la API se desincronicen en la forma del destino.
 */
const entrar = async (slug: string) => {
    try {
        const respuesta = await $fetch<{ url: string }>('/api/auth/entrar')

        consultorioElegido.value = slug

        // El consultorio elegido viaja en el estado de la sesión del
        // navegador, no en la URL: es un dato de la pantalla, no una credencial.
        sessionStorage.setItem('consultorio-elegido', slug)

        await navigateTo(respuesta.url, { external: true })
    } catch (error: unknown) {
        entrarError.value = mensajeDeEntrada(error)
    }
}

const mensajeDeEntrada = (error: unknown): string => {
    if (error && typeof error === 'object' && 'data' in error) {
        const cuerpo = (error as { data?: { mensaje?: string } }).data

        if (cuerpo?.mensaje) {
            return cuerpo.mensaje
        }
    }

    return 'No se pudo iniciar el ingreso. Intente de nuevo.'
}
</script>