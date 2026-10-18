<template>
    <div>
        <p v-if="cargando" class="aviso">
            Cargando la nota...
        </p>

        <!--
            EL 404 DE LA API LLEGA AQUÍ
            ---------------------------
            La API responde 404 tanto si la nota no existe como si el permiso la
            rechazó. La interfaz no puede distinguirlas y no debe intentarlo:
            inventar una distinción sería volver a confirmar que la nota existe.

            Por eso el mensaje es genérico y la explicación del rol ya está en
            el perfil de la sesión. Ver docs/03.
        -->
        <div v-else-if="error" class="restringido">
            <p class="restringido__titulo">
                No encontrado
            </p>
            <p>{{ error }}</p>
            <p class="aviso">
                Si su rol le permite abrir notas de sesión, es posible que la nota no exista o que
                sea de otro profesional. Consulte con la dirección del consultorio.
            </p>
        </div>

        <template v-else-if="nota">
            <h1>Nota de sesión</h1>

            <div class="tarjeta">
                <p class="aviso">
                    Esta apertura quedó anotada en la bitácora de accesos, con su fecha y su hora.
                </p>
            </div>

            <!--
                NOTA CERRADA
                -------------
                El bloque más importante de la pantalla. Explica que la nota no
                se edita y que la salida es la corrección con motivo, y que la
                corrección deja escrito el texto anterior. Ver docs/06.
            -->
            <div v-if="!nota.admite_edicion" class="alerta">
                <p class="restringido__titulo" style="color: inherit; margin-top: 0">
                    Esta nota está cerrada
                </p>
                <p>
                    Una nota cerrada no se puede editar. Para cambiar lo que dice, hay que registrar
                    una corrección con su motivo: eso deja constancia de que el registro cambió, del
                    texto que había y del nuevo.
                </p>
                <p v-if="nota.cerrada_en" class="aviso" style="color: inherit">
                    Cerrada el {{ formatearFecha(nota.cerrada_en) }}
                    <template v-if="nota.motivo_cierre">
                        &mdash; {{ nota.motivo_cierre }}
                    </template>
                </p>
            </div>

            <div class="tarjeta">
                <label class="campo">
                    <span class="campo__etiqueta">
                        Contenido de la sesión
                    </span>

                    <textarea
                        v-model="contenido"
                        :readonly="!nota.admite_edicion"
                        :rows="18"
                    />
                </label>

                <div class="fila-botones">
                    <template v-if="nota.admite_edicion">
                        <button
                            type="button"
                            class="boton"
                            :disabled="guardando"
                            @click="guardar"
                        >
                            Guardar
                        </button>

                        <button
                            type="button"
                            class="boton boton--neutro"
                            :disabled="sinContenido || guardando"
                            @click="cerrando = true"
                        >
                            Cerrar la nota
                        </button>

                        <span class="aviso">
                            Mientras esté abierta se puede editar. Al cerrarla ya no.
                        </span>
                    </template>

                    <template v-else>
                        <button
                            type="button"
                            class="boton"
                            @click="corrigiendo = true"
                        >
                            Registrar una corrección
                        </button>
                    </template>
                </div>
            </div>

            <div v-if="nota.tiene_correcciones" class="tarjeta">
                <h2 class="tarjeta__titulo">
                    Historial de correcciones
                </h2>

                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Versión</th>
                            <th>Motivo</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="revision in revisiones" :key="revision.id">
                            <td>{{ revision.version_anterior }} &rarr; {{ revision.version_nueva }}</td>
                            <td>{{ revision.motivo }}</td>
                            <td>{{ formatearFecha(revision.registrado_en) }}</td>
                        </tr>
                    </tbody>
                </table>

                <p class="aviso">
                    El texto de cada versión anterior queda guardado. No se borra.
                </p>
            </div>
        </template>

        <!--
            EL DIÁLOGO DE CORRECCIÓN
            ------------------------
            El motivo no es un campo opcional: el mínimo lo comprueba la API y
            la razón de que exista es que una corrección sin motivo escrito no
            explica por qué el registro de una sesión cambió.
        -->
        <div v-if="corrigiendo" class="tarjeta">
            <h2 class="tarjeta__titulo">
                Registrar una corrección
            </h2>

            <p class="aviso">
                El texto que está ahora quedará guardado como versión anterior. Esto no es una
                edición: queda constancia del cambio y del motivo.
            </p>

            <label class="campo">
                <span class="campo__etiqueta">
                    Motivo de la corrección
                </span>
                <textarea
                    v-model="motivo"
                    rows="3"
                    placeholder="Explique por qué cambia el registro. Quien lo lea en el futuro lo necesita."
                />
                <span class="campo__ayuda">
                    Al menos 15 caracteres.
                </span>
            </label>

            <div class="fila-botones">
                <button
                    type="button"
                    class="boton"
                    :disabled="enviando"
                    @click="corregir"
                >
                    Registrar la corrección
                </button>

                <button
                    type="button"
                    class="boton boton--neutro"
                    @click="corrigiendo = false"
                >
                    Cancelar
                </button>
            </div>
        </div>

        <div v-if="cerrando" class="tarjeta">
            <h2 class="tarjeta__titulo">
                Cerrar la nota
            </h2>

            <p>
                Al cerrarla, la nota deja de poder editarse. Después solo se puede corregir, con su
                motivo y dejando escrito el texto anterior.
            </p>

            <label class="campo">
                <span class="campo__etiqueta">
                    Motivo del cierre (opcional)
                </span>
                <textarea v-model="motivoCierre" rows="2" />
            </label>

            <div class="fila-botones">
                <button
                    type="button"
                    class="boton"
                    :disabled="enviando"
                    @click="cerrar"
                >
                    Cerrar la nota
                </button>

                <button
                    type="button"
                    class="boton boton--neutro"
                    @click="cerrando = false"
                >
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * La nota de sesión.
 *
 * ESTA ES LA PANTALLA MÁS SENSIBLE DE LA APLICACIÓN
 * ------------------------------------------------
 * Y por eso no hace nada que no pueda explicar: por qué una nota cerrada no se
 * edita, por qué corregir no es editar, y por qué queda escrito el texto
 * anterior.
 *
 * LO QUE NO HACE
 * --------------
 * No tiene botón de borrar. No hay ruta que lo llame y hay un disparador en
 * la base que lo rechaza, pero sobre todo: una nota de sesión no es un
 * registro que se pueda sacar de en medio.
 *
 * Ver docs/06.
 */

import type { NotaSesion, RevisionNota } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const ruta = useRoute()

const cargando = ref(true)
const guardando = ref(false)
const enviando = ref(false)

const nota = ref<NotaSesion | null>(null)
const revisiones = ref<RevisionNota[]>([])
const contenido = ref('')
const motivo = ref('')
const motivoCierre = ref('')
const errata = ref<string | null>(null)
const corrigiendo = ref(false)
const cerrando = ref(false)
const error = ref<string | null>(null)

const sinContenido = computed(() => contenido.value.trim() === '')

const cargar = async () => {
    cargando.value = true
    error.value = null

    try {
        const respuesta = await $fetch<{ datos: NotaSesion }>(`/api/notas/${ruta.params.id}`)

        nota.value = respuesta.datos
        contenido.value = respuesta.datos.contenido ?? ''

        if (respuesta.datos.tiene_correcciones) {
            const historial = await $fetch<{ datos: RevisionNota[] }>(
                `/api/notas/${ruta.params.id}/revisiones`,
            )

            revisiones.value = historial.datos
        }
    } catch {
        error.value = 'No se encontró la nota, o no le corresponde.'
    } finally {
        cargando.value = false
    }
}

const guardar = async () => {
    guardando.value = true
    errata.value = null

    try {
        await $fetch(`/api/notas/${ruta.params.id}`, {
            method: 'PUT',
            body: { contenido: contenido.value },
        })

        await cargar()
    } catch (e: unknown) {
        errata.value = mensajeDeLaApi(e, 'No se pudo guardar la nota.')
    } finally {
        guardando.value = false
    }
}

const cerrar = async () => {
    enviando.value = true
    errata.value = null

    try {
        await $fetch(`/api/notas/${ruta.params.id}/cerrar`, {
            method: 'POST',
            body: { motivo_cierre: motivoCierre.value || null },
        })

        cerrando.value = false
        await cargar()
    } catch (e: unknown) {
        errata.value = mensajeDeLaApi(e, 'No se pudo cerrar la nota.')
    } finally {
        enviando.value = false
    }
}

const corregir = async () => {
    enviando.value = true
    errata.value = null

    try {
        await $fetch(`/api/notas/${ruta.params.id}/correcciones`, {
            method: 'POST',
            body: { contenido: contenido.value, motivo: motivo.value },
        })

        corrigiendo.value = false
        motivo.value = ''
        await cargar()
    } catch (e: unknown) {
        errata.value = mensajeDeLaApi(e, 'No se pudo registrar la corrección.')
    } finally {
        enviando.value = false
    }
}

const mensajeDeLaApi = (e: unknown, porDefecto: string): string => {
    if (e && typeof e === 'object' && 'data' in e) {
        const cuerpo = (e as { data?: { mensaje?: string, errores?: Record<string, string[]> } }).data

        if (cuerpo?.mensaje) {
            return cuerpo.mensaje
        }

        if (cuerpo?.errores) {
            const primero = Object.values(cuerpo.errores)[0]

            if (primero?.[0]) {
                return primero[0]
            }
        }
    }

    return porDefecto
}

const formatearFecha = (valor: string | null): string => {
    if (valor === null) {
        return 'sin fecha'
    }

    return new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium', timeStyle: 'short' })
        .format(new Date(valor))
}

onMounted(cargar)
</script>