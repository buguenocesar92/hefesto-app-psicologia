<template>
    <div>
        <h1>Instrumentos de evaluación</h1>

        <p v-if="cargando" class="aviso">
            Cargando...
        </p>

        <template v-else>
            <!--
                EL AVISO QUE VA PRIMERO
                -------------------------
                Un instrumento sin puntaje de corte verificado no sirve para
                decidir nada. Decirlo arriba, antes de la tabla, es mejor que
                dejarlo para cuando alguien mire una fila.

                La columna `estado_del_corte` la escribe el dominio con una
                frase, para que esta pantalla no tenga que saber la diferencia
                entre las tres columnas. Ver docs/06.
            -->
            <div v-if="sinVerificar.length > 0" class="alerta">
                <p class="restringido__titulo" style="color: inherit; margin-top: 0">
                    {{ sinVerificar.length }} instrumento(s) sin puntaje de corte verificado
                </p>
                <p>
                    Un puntaje de corte es un dato clínico: dice, sobre una persona, a partir de acá
                    hay algo. La aplicación no pone ninguno por omisión. Si el consultorio conoce un
                    número, se carga con su fuente y su referencia de verificación; si todavía no
                    puede respaldarlo, se anota como pendiente y el sistema no lo usa para comparar
                    nada.
                </p>
            </div>

            <p v-if="instrumentos.length === 0" class="tarjeta">
                Todavía no hay instrumentos cargados.
            </p>

            <div v-for="instrumento in instrumentos" v-else :key="instrumento.id" class="tarjeta">
                <h2 class="tarjeta__titulo">
                    {{ instrumento.nombre }}
                    <span class="aviso">({{ instrumento.codigo }})</span>
                </h2>

                <p v-if="instrumento.version_instrumento" class="aviso">
                    Versión del instrumento: {{ instrumento.version_instrumento }}
                </p>

                <p class="aviso">
                    {{ instrumento.estado_del_corte }}
                </p>

                <table class="tabla">
                    <tbody>
                        <tr>
                            <th>Puntaje de corte</th>
                            <td>
                                <span v-if="instrumento.tiene_corte_verificado" class="verificado">
                                    {{ instrumento.puntaje_corte }}
                                    &mdash; verificado contra su fuente
                                </span>
                                <span v-else>
                                    sin corte verificado
                                </span>
                            </td>
                        </tr>

                        <tr v-if="instrumento.puntaje_corte_pendiente !== null && !instrumento.corte_verificado">
                            <th>Corte pendiente</th>
                            <td>
                                {{ instrumento.puntaje_corte_pendiente }}
                                <span class="aviso">&mdash; anotado y sin respaldo. No se usa para comparar.</span>
                            </td>
                        </tr>

                        <tr v-if="instrumento.fuente_del_corte">
                            <th>Fuente</th>
                            <td>{{ instrumento.fuente_del_corte }}</td>
                        </tr>

                        <tr v-if="instrumento.referencia_verificacion">
                            <th>Referencia de verificación</th>
                            <td>{{ instrumento.referencia_verificacion }}</td>
                        </tr>

                        <tr v-if="instrumento.direccion_puntaje">
                            <th>Dirección del puntaje</th>
                            <td>{{ instrumento.direccion_puntaje }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="aviso">
                Para ver cuáles faltan desde la consola: <code>php artisan psicologia:verificar-cortes</code>
            </p>
        </template>
    </div>
</template>

<script setup lang="ts">
/**
 * El catálogo de instrumentos.
 *
 * LO QUE ESTA PANTALLA PROMETE Y LO QUE NO
 * --------------------------------------
 * Promete que el sistema no inventa puntajes de corte, y lo cumple de tres
 * formas: el campo nace vacío, la base rechaza declarar verificado un corte
 * sin fuente, y un corte pendiente se muestra marcado como pendiente.
 *
 * La cuarta forma es esta pantalla: cuando un número no tiene respaldo, la
 * persona tiene que verlo escrito en la fila, no descubrirlo por un código que
 * después resulta ser un diagnóstico. Ver docs/06.
 */

import type { Instrumento } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const cargando = ref(true)
const instrumentos = ref<Instrumento[]>([])

const sinVerificar = computed(() => {
    return instrumentos.value.filter((instrumento) => !instrumento.tiene_corte_verificado)
})

onMounted(async () => {
    try {
        const respuesta = await $fetch<{ datos: Instrumento[] }>('/api/instrumentos')

        instrumentos.value = respuesta.datos
    } finally {
        cargando.value = false
    }
})
</script>