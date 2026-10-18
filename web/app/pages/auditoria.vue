<template>
    <div>
        <h1>Bitácora de accesos</h1>

        <div class="restringido">
            <p class="restringido__titulo">
                Qué es esto
            </p>
            <p>
                Cada vez que alguien abre el contenido de una sesión queda anotado acá: quién, cuándo,
                qué nota y con qué resultado. También quedan anotados los intentos que el sistema
                rechazó, que son los que más importan: un intento denegado de abrir una sesión
                que no le corresponde es información de seguridad.
            </p>
        </div>

        <div class="tarjeta">
            <div class="rejilla">
                <label class="campo">
                    <span class="campo__etiqueta">
                        Desde
                    </span>
                    <input v-model="desde" type="date" @change="cargar">
                </label>

                <label class="campo">
                    <span class="campo__etiqueta">
                        Resultado
                    </span>
                    <select v-model="resultado" @change="cargar">
                        <option value="">Todos</option>
                        <option value="denegado">Solo denegados</option>
                        <option value="permitido">Solo permitidos</option>
                    </select>
                </label>

                <label class="campo">
                    <span class="campo__etiqueta">
                        Solo notas de sesión
                    </span>
                    <select v-model="soloContenido" @change="cargar">
                        <option value="no">No</option>
                        <option value="si">Sí</option>
                    </select>
                </label>
            </div>
        </div>

        <p v-if="cargando" class="aviso">
            Cargando...
        </p>

        <p v-else-if="accesos.length === 0" class="tarjeta">
            No hay accesos registrados en el período.
        </p>

        <table v-else class="tabla">
            <thead>
                <tr>
                    <th>Cuándo</th>
                    <th>Rol</th>
                    <th>Acción</th>
                    <th>Resultado</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="acceso in accesos" :key="acceso.id">
                    <td>{{ formatearFecha(acceso.ocurrido_en) }}</td>
                    <td>{{ acceso.rol ?? 'sin rol' }}</td>
                    <td>{{ acceso.recurso }} &mdash; {{ acceso.accion }}</td>
                    <td>
                        <span
                            class="etiqueta-estado"
                            :class="acceso.resultado === 'denegado'
                                ? 'etiqueta-estado--cerrada'
                                : 'etiqueta-estado--abierta'"
                        >
                            {{ acceso.resultado_etiqueta }}
                        </span>
                    </td>
                    <td>{{ acceso.motivo ?? '' }}</td>
                </tr>
            </tbody>
        </table>

        <p class="aviso">
            Esta pantalla es solo de consulta. No hay exportación: un volcado de la bitácora es un
            volcado de quién leyó qué, y eso es un dato que sale del consultorio.
        </p>
    </div>
</template>

<script setup lang="ts">
/**
 * La bitácora de accesos.
 *
 * SOLO DIRECCIÓN
 * -------------
 * Verla completa revela la estructura del consultorio: cuántos profesionales
 * hay, qué roles existen y qué actividad hay. Eso no lo necesita cualquiera.
 *
 * LO QUE ESTA PANTALLA DICE Y NO
 * -----------------------------
 * Dice el motivo de cada denegación, porque la API lo escribió con las
 * palabras del dominio. Un "denegado" a secas obliga a adivinar después, y lo
 * que hay que reconstruir no son los accesos correctos sino los rechazados.
 *
 * Ver docs/06.
 */

import type { Acceso } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const cargando = ref(true)
const accesos = ref<Acceso[]>([])

const desde = ref(new Date(Date.now() - 7 * 24 * 3600 * 1000).toISOString().slice(0, 10))
const resultado = ref('')
const soloContenido = ref('no')

const cargar = async () => {
    cargando.value = true

    try {
        const respuesta = await $fetch<{ datos: Acceso[] }>('/api/auditoria/accesos', {
            query: {
                desde: desde.value,
                resultado: resultado.value || undefined,
                solo_contenido: soloContenido.value === 'si' ? 1 : undefined,
            },
        })

        accesos.value = respuesta.datos
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