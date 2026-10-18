<template>
    <div>
        <h1>Notas de sesión</h1>

        <!--
            ESTA PANTALLA SOLO LA VE QUIEN ATIENDE
            ---------------------------------------
            No es que esté vacía para los demás: no existe para ellos. La API
            devuelve 404 antes de llegar acá, y eso es a propósito: un 404 no
            confirma que exista ninguna nota.

            El mensaje está para el caso de que alguien llegue por la URL. Ver
            docs/03.
        -->
        <div v-if="!veNotas()" class="restringido">
            <p class="restringido__titulo">
                No puede ver el contenido de las sesiones
            </p>
            <p>{{ accesoAlContenido() }}</p>
        </div>

        <template v-else>
            <p v-if="cargando" class="aviso">
                Cargando...
            </p>

            <p v-else-if="notas.length === 0" class="tarjeta">
                Todavía no hay notas de sesión.
            </p>

            <table v-else class="tabla">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Sesión</th>
                        <th>Estado</th>
                        <th>Versión</th>
                        <th>Actualizada</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="nota in notas" :key="nota.id">
                        <td>{{ nota.paciente }}</td>
                        <td>{{ formatearFecha(nota.fecha_sesion) }}</td>
                        <td>
                            <span
                                class="etiqueta-estado"
                                :class="nota.estado === 'abierta'
                                    ? 'etiqueta-estado--abierta'
                                    : 'etiqueta-estado--cerrada'"
                            >
                                {{ nota.estado === 'abierta' ? 'Abierta' : 'Cerrada' }}
                            </span>
                        </td>
                        <td>{{ nota.version }}</td>
                        <td>{{ formatearFecha(nota.actualizado_en) }}</td>
                        <td>
                            <NuxtLink :to="`/notas/${nota.id}`">
                                Abrir
                            </NuxtLink>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="alerta">
                <p style="margin: 0">
                    Cada apertura de una nota queda anotada en la bitácora de accesos, con su fecha,
                    su hora y quién la abrió. También quedan anotados los intentos que el sistema
                    rechaza.
                </p>
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
/**
 * El listado de notas de sesión.
 *
 * POR QUÉ NO MUESTRA EL CONTENIDO
 * ------------------------------
 * Porque el listado es un lugar donde un contenido sensible se copia por la
 * pantalla sin que nadie lo pida. El listado dice qué notas hay y en qué
 * estado; para leer una hay que abrirla, y abrirla queda registrado.
 *
 * Ver docs/06.
 */

definePageMeta({ middleware: 'auth' })

interface FilaNota {
    id: string
    estado: 'abierta' | 'cerrada'
    version: number
    paciente: string | null
    fecha_sesion: string | null
    tiene_contenido: boolean
    actualizado_en: string | null
}

const cargando = ref(true)
const notas = ref<FilaNota[]>([])

const { veNotas, accesoAlContenido } = useSesion()

const cargar = async () => {
    if (!veNotas()) {
        cargando.value = false

        return
    }

    cargando.value = true

    try {
        const respuesta = await $fetch<{ datos: FilaNota[] }>('/api/notas')

        notas.value = respuesta.datos
    } finally {
        cargando.value = false
    }
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