<template>
    <div>
        <h1>Pacientes</h1>

        <label class="campo campo-busca">
            <span class="campo__etiqueta">Buscar</span>
            <input
                v-model="busqueda"
                type="search"
                placeholder="Nombre, apellido, RUT o código de ficha"
            >
        </label>

        <p v-if="cargando" class="aviso">
            Cargando...
        </p>

        <p v-else-if="pacientes.length === 0" class="tarjeta">
            No hay pacientes que coincidan.
        </p>

        <table v-else class="tabla">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>RUT</th>
                    <th>Teléfono</th>
                    <th>Motivo de consulta</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="paciente in pacientes" :key="paciente.id">
                    <td>
                        <NuxtLink :to="`/pacientes/${paciente.id}`">
                            {{ paciente.nombre_completo }}
                        </NuxtLink>
                    </td>
                    <td>{{ paciente.rut ?? 'sin RUT' }}</td>
                    <td>{{ paciente.telefono ?? 'sin teléfono' }}</td>
                    <td>{{ paciente.motivo_consulta ?? 'sin motivo anotado' }}</td>
                    <td>
                        <span v-if="paciente.activo" class="etiqueta-estado etiqueta-estado--abierta">
                            Activo
                        </span>
                        <span v-else class="etiqueta-estado etiqueta-estado--cerrada">
                            Desactivado
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="aviso" style="margin-top: 0.8rem">
            La ficha de un paciente tiene sus datos de identificación. El contenido de las sesiones
            no está acá.
        </p>
    </div>
</template>

<script setup lang="ts">
/**
 * El listado de pacientes.
 *
 * LO QUE ESTA PANTALLA ES
 * -----------------------
 * El lado público del paciente dentro del consultorio: quién es, cómo se lo
 * llama y por qué pidió hora.
 *
 * Todos los roles lo ven, porque sin eso el administrativo no puede atender el
 * teléfono. Lo que cambia por rol es el contenido de las sesiones, y eso no
 * está en esta pantalla. Ver docs/03.
 */

import type { Paciente } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const busqueda = ref('')
const cargando = ref(true)
const pacientes = ref<Paciente[]>([])

const cargar = async () => {
    cargando.value = true

    try {
        const respuesta = await $fetch<{ datos: Paciente[] }>('/api/pacientes', {
            query: busqueda.value ? { buscar: busqueda.value } : {},
        })

        pacientes.value = respuesta.datos
    } finally {
        cargando.value = false
    }
}

let temporizador: ReturnType<typeof setTimeout> | undefined

watch(busqueda, () => {
    // Se espera a que la persona termine de escribir. Es una búsqueda, no un
    // formulario, y no tiene sentido pedir en cada tecla.
    if (temporizador !== undefined) {
        clearTimeout(temporizador)
    }

    temporizador = setTimeout(cargar, 350)
})

onMounted(cargar)
</script>