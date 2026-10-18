<template>
    <div>
        <h1>Agenda</h1>

        <div class="tarjeta">
            <label class="campo" style="max-width: 14rem">
                <span class="campo__etiqueta">Fecha</span>
                <input v-model="fecha" type="date" @change="cargar">
            </label>
        </div>

        <p v-if="cargando" class="aviso">
            Cargando la agenda...
        </p>

        <div v-else-if="agenda.citas.length === 0" class="tarjeta">
            <p>
                No hay citas para el {{ formatearFecha(agenda.fecha) }}.
            </p>
        </div>

        <div v-else>
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Profesional</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Nota</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="cita in agenda.citas" :key="cita.id">
                        <td>{{ cita.hora }}&ndash;{{ cita.hora_fin }}</td>
                        <td>
                            <NuxtLink :to="`/pacientes/${cita.paciente_id}`">
                                {{ cita.paciente }}
                            </NuxtLink>
                        </td>
                        <td>{{ cita.profesional }}</td>
                        <td>{{ cita.tipo_atencion }}</td>
                        <td>
                            <span class="etiqueta-estado">
                                {{ cita.estado_etiqueta }}
                            </span>
                        </td>
                        <td>
                            <!--
                                AQUÍ SOLO APARECE UN SÍ O UN NO
                                ---------------------------------
                                Que exista nota o no es información operativa: el
                                profesional tiene que saber si le falta escribirla.

                                El contenido no pasa por esta pantalla. Va en
                                /notas/{id}, que tiene su propia auditoría. Ver
                                docs/03.
                            -->
                            <span v-if="cita.tiene_nota" class="etiqueta-estado etiqueta-estado--cerrada">
                                {{ cita.nota_abierta ? 'Abierta' : 'Cerrada' }}
                            </span>

                            <span v-else-if="cita.admite_nota" class="aviso">
                                Falta escribir
                            </span>

                            <span v-else class="aviso">
                                &mdash;
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="esTratante" class="aviso" style="margin-top: 0.6rem">
                Para abrir el contenido de una sesión, entre a
                <NuxtLink to="/notas">Notas de sesión</NuxtLink>. Ahí queda registrado quién abrió
                qué.
            </p>
        </div>

        <div v-if="!esTratante" class="restringido">
            <p class="restringido__titulo">
                Por qué aquí no aparece el contenido de las sesiones
            </p>
            <p>{{ accesoAlContenido() }}</p>
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * La agenda del día.
 *
 * POR QUE NO HAY UN CALENDARIO RICO
 * --------------------------------
 * Porque la agenda tiene que abrir rápido y no puede fallar por una biblioteca
 * que no está auditada. Una tabla de un día es menos vistosa y hace lo que hay
 * que hacer.
 *
 * LO QUE LA AGENDA MUESTRA Y LO QUE NO
 * -----------------------------------
 * Muestra quién, cuándo, con qué profesional y si la sesión ocurrió. No muestra
 * el contenido de la sesión, ni siquiera un adelanto. Eso vive en otra
 * pantalla, con su propia auditoría de acceso. Ver docs/03.
 */

import type { Agenda } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const hoy = new Date().toISOString().slice(0, 10)

const fecha = ref(hoy)
const cargando = ref(true)
const agenda = ref<Agenda>({ fecha: hoy, citas: [], estados_disponibles: [] })

const { esTratante, accesoAlContenido } = useSesion()

const cargar = async () => {
    cargando.value = true

    try {
        const respuesta = await $fetch<{ datos: Agenda }>('/api/agenda', {
            query: { fecha: fecha.value },
        })

        agenda.value = respuesta.datos
    } finally {
        cargando.value = false
    }
}

const formatearFecha = (valor: string): string => {
    return new Intl.DateTimeFormat('es-CL', { dateStyle: 'long' }).format(new Date(valor))
}

onMounted(cargar)
</script>