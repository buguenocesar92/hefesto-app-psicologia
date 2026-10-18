<template>
    <div>
        <h1>Derivaciones</h1>

        <div v-if="esTratante" class="tarjeta">
            <h2 class="tarjeta__titulo">
                Registrar una derivación
            </h2>

            <p class="aviso">
                Una derivación no se puede borrar. Si se registra, queda registrada, aunque después
                se atienda o se cierre. Eso no es una limitación de la pantalla: hay una restricción
                en la base que rechaza el borrado.
            </p>

            <label class="campo">
                <span class="campo__etiqueta">
                    Paciente
                </span>
                <input v-model="formulario.paciente_id" placeholder="Identificador del paciente">
                <span class="campo__ayuda">
                    Elija el paciente desde la agenda o desde la ficha del paciente.
                </span>
            </label>

            <label class="campo">
                <span class="campo__etiqueta">
                    A dónde se deriva
                </span>
                <select v-model="formulario.naturaleza">
                    <option value="urgencia">Urgencia</option>
                    <option value="atencion_primaria">Atención primaria</option>
                    <option value="especialidad">Especialidad</option>
                    <option value="red_salud_mental">Red de salud mental</option>
                </select>
                <span class="campo__ayuda">
                    El sistema no deduce un nivel de riesgo a partir de un puntaje. Esa decisión es de
                    quien deriva, y queda escrita en el motivo.
                </span>
            </label>

            <label class="campo">
                <span class="campo__etiqueta">
                    Destino
                </span>
                <input v-model="formulario.destino" placeholder="A qué unidad o servicio se deriva">
            </label>

            <label class="campo">
                <span class="campo__etiqueta">
                    Motivo
                </span>
                <textarea
                    v-model="formulario.motivo"
                    rows="4"
                    placeholder="Por qué se decide derivar a esta persona."
                />
                <span class="campo__ayuda">
                    Al menos 15 caracteres. Sin un motivo escrito, la derivación no deja constancia
                    de por qué se decidió.
                </span>
            </label>

            <div class="fila-botones">
                <button
                    type="button"
                    class="boton"
                    :disabled="enviando || formulario.motivo.trim().length < 15"
                    @click="registrar"
                >
                    Registrar la derivación
                </button>
            </div>
        </div>

        <p v-if="cargando" class="aviso">
            Cargando...
        </p>

        <table v-else-if="derivaciones.length > 0" class="tabla">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Naturaleza</th>
                    <th>Destino</th>
                    <th>Estado</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="derivacion in derivaciones" :key="derivacion.id">
                    <td>{{ derivacion.paciente }}</td>
                    <td>
                        {{ derivacion.naturaleza_etiqueta }}
                        <span v-if="derivacion.exige_contacto_inmediato" class="alerta" style="padding: 0.1rem 0.4rem">
                            mismo día
                        </span>
                    </td>
                    <td>{{ derivacion.destino }}</td>
                    <td>
                        <span class="etiqueta-estado">
                            {{ derivacion.estado_etiqueta }}
                        </span>
                    </td>
                    <td>
                        <!--
                            EL MOTIVO, O SU AUSENCIA EXPLICADA
                            ------------------------------------
                            Para quien atiende, el motivo. Para el resto, una
                            explicación de por qué no lo ve. Nunca un vacío: un
                            vacío hace pensar que no hay motivo.
                        -->
                        <template v-if="derivacion.ve_motivo">
                            {{ derivacion.motivo }}
                        </template>

                        <span v-else class="aviso">
                            No lo ve: es contenido clínico.
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <p v-else class="tarjeta">
            No hay derivaciones registradas.
        </p>

        <div v-if="!esTratante" class="restringido">
            <p class="restringido__titulo">
                Por qué aquí no ve el motivo de las derivaciones
            </p>
            <p>{{ accesoAlContenido() }}</p>
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * Las derivaciones por crisis.
 *
 * LA SEPARACIÓN QUE SE VE EN LA PANTALLA
 * --------------------------------------
 * El motivo de una derivación es la razón clínica por la que se decidió
 * derivar. Es contenido de sesión tanto como lo es la nota de una sesión.
 *
 * Entonces un administrativo ve que hay una derivación, cuándo, de qué tipo y
 * si exige contacto el mismo día, porque eso es lo que tiene que coordinar. Y
 * el motivo aparece en su columna con la explicación de por qué no lo ve.
 *
 * Ver docs/03 y docs/06.
 */

import type { Derivacion } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const cargando = ref(true)
const enviando = ref(false)
const derivaciones = ref<Derivacion[]>([])

const formulario = reactive({
    paciente_id: '',
    naturaleza: 'urgencia',
    destino: '',
    motivo: '',
})

const { esTratante, accesoAlContenido } = useSesion()

const registrar = async () => {
    enviando.value = true

    try {
        await $fetch('/api/derivaciones', {
            method: 'POST',
            body: { ...formulario },
        })

        formulario.paciente_id = ''
        formulario.destino = ''
        formulario.motivo = ''

        await cargar()
    } finally {
        enviando.value = false
    }
}

const cargar = async () => {
    cargando.value = true

    try {
        const respuesta = await $fetch<{ datos: Derivacion[] }>('/api/derivaciones')

        derivaciones.value = respuesta.datos
    } finally {
        cargando.value = false
    }
}

onMounted(cargar)
</script>