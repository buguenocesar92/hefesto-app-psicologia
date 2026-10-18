<template>
    <div>
        <p v-if="cargando" class="aviso">
            Cargando la ficha...
        </p>

        <template v-else-if="paciente">
            <h1>{{ paciente.nombre_completo }}</h1>

            <div class="tarjeta">
                <div class="rejilla">
                    <div>
                        <p class="aviso">
                            RUT
                        </p>
                        <p>{{ paciente.rut ?? 'sin RUT' }}</p>
                    </div>
                    <div>
                        <p class="aviso">
                            Fecha de nacimiento
                        </p>
                        <p>{{ paciente.fecha_nacimiento ?? 'sin anotar' }}</p>
                    </div>
                    <div>
                        <p class="aviso">
                            Teléfono
                        </p>
                        <p>{{ paciente.telefono ?? 'sin anotar' }}</p>
                    </div>
                    <div>
                        <p class="aviso">
                            Previsión
                        </p>
                        <p>
                            {{ paciente.prevision ?? 'sin anotar' }}
                            <template v-if="paciente.plan_prevision">
                                ({{ paciente.plan_prevision }})
                            </template>
                        </p>
                    </div>
                </div>

                <p style="margin-top: 1rem">
                    <span class="aviso">Motivo de consulta:</span>
                    {{ paciente.motivo_consulta ?? 'sin motivo anotado' }}
                </p>

                <p v-if="paciente.contacto_emergencia.nombre" style="margin-top: 0.6rem">
                    <span class="aviso">Contacto de emergencia:</span>
                    {{ paciente.contacto_emergencia.nombre }}
                    <template v-if="paciente.contacto_emergencia.parentesco">
                        ({{ paciente.contacto_emergencia.parentesco }})
                    </template>
                    &mdash; {{ paciente.contacto_emergencia.telefono }}
                </p>
            </div>

            <!--
                POR QUÉ AQUÍ DICE QUE NO HAY NOTAS
                ----------------------------------
                Un administrativo que abre una ficha y no encuentra las
                sesiones, sin más explicación, va a pensar que la aplicación no
                las muestra. Con la explicación, sabe que es su rol.

                Y la explicación no la escribe esta pantalla: la escribió el
                dominio, y llega en el perfil de la sesión. Ver docs/03.
            -->
            <div v-if="!veNotas()" class="restringido">
                <p class="restringido__titulo">
                    El contenido de las sesiones no aparece en esta pantalla
                </p>
                <p>{{ accesoAlContenido() }}</p>
            </div>

            <div class="tarjeta">
                <h2 class="tarjeta__titulo">
                    Consentimientos
                </h2>

                <p v-if="consentimientos.length === 0" class="alerta">
                    Este paciente no tiene consentimiento registrado. Sin consentimiento de
                    tratamiento vigente no se pueden agendar sesiones.
                </p>

                <table v-else class="tabla">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Versión del documento</th>
                            <th>Modalidad</th>
                            <th>Firmado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="consentimiento in consentimientos" :key="consentimiento.id">
                            <td>{{ consentimiento.tipo_etiqueta }}</td>
                            <td>{{ consentimiento.version_documento }}</td>
                            <td>{{ consentimiento.modalidad }}</td>
                            <td>{{ consentimiento.firmado_en ?? 'sin fecha' }}</td>
                            <td>
                                <span
                                    v-if="!consentimiento.anulado"
                                    class="etiqueta-estado etiqueta-estado--abierta"
                                >
                                    Vigente
                                </span>
                                <span v-else class="etiqueta-estado etiqueta-estado--cerrada">
                                    Anulado
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="aviso">
                Los textos de consentimiento los redacta el consultorio con su asesoría legal. La
                aplicación guarda la constancia: fecha, versión del documento y dónde está firmado.
            </p>
        </template>

        <div v-else class="alerta">
            {{ error ?? 'No se encontró la ficha.' }}
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * La ficha de un paciente.
 *
 * LO QUE ESTA PANTALLA DELIBERADAMENTE NO TIENE
 * ---------------------------------------------
 * Un listado de notas. Ni un contador. Ni un botón que diga "ver sesiones".
 *
 * Para un administrativo, esta pantalla tiene que verse como lo que es: una
 * ficha de identificación. Con un bloque que explica por qué, porque si no la
 * persona cree que faltan datos.
 *
 * Ver docs/03 y docs/06.
 */

import type { Consentimiento, Paciente } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const ruta = useRoute()

const cargando = ref(true)
const paciente = ref<Paciente | null>(null)
const consentimientos = ref<Consentimiento[]>([])
const error = ref<string | null>(null)

const { veNotas, accesoAlContenido } = useSesion()

onMounted(async () => {
    try {
        const respuesta = await $fetch<{ datos: Paciente }>(`/api/pacientes/${ruta.params.id}`)

        paciente.value = respuesta.datos

        const lista = await $fetch<{ datos: Consentimiento[] }>(
            `/api/pacientes/${ruta.params.id}/consentimientos`,
        )

        consentimientos.value = lista.datos
    } catch {
        error.value = 'No se encontró la ficha del paciente.'
    } finally {
        cargando.value = false
    }
})
</script>