<template>
    <div>
        <h1>Pagos y boletas</h1>

        <div class="tarjeta">
            <div class="rejilla">
                <div>
                    <p class="aviso">
                        Total del período
                    </p>
                    <p class="cifra">
                        {{ formatearPesos(total) }}
                    </p>
                    <p class="aviso">
                        No incluye pagos anulados.
                    </p>
                </div>
            </div>
        </div>

        <p v-if="cargando" class="aviso">
            Cargando...
        </p>

        <template v-else>
            <div class="tarjeta">
                <h2 class="tarjeta__titulo">
                    Registrar un pago
                </h2>

                <div class="rejilla">
                    <label class="campo">
                        <span class="campo__etiqueta">
                            Paciente
                        </span>
                        <input v-model="formulario.paciente_id" placeholder="Identificador del paciente">
                    </label>

                    <label class="campo">
                        <span class="campo__etiqueta">
                            Monto
                        </span>
                        <input v-model.number="formulario.monto_clp" type="number" min="1" step="1">
                        <span class="campo__ayuda">
                            Pesos enteros. La aplicación no trae precios: los carga el consultorio.
                        </span>
                    </label>

                    <label class="campo">
                        <span class="campo__etiqueta">
                            Forma de pago
                        </span>
                        <select v-model="formulario.metodo">
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="otro">Otro</option>
                        </select>
                    </label>

                    <label class="campo">
                        <span class="campo__etiqueta">
                            Fecha
                        </span>
                        <input v-model="formulario.fecha_pago" type="datetime-local">
                    </label>
                </div>

                <div class="fila-botones">
                    <button
                        type="button"
                        class="boton"
                        :disabled="enviando || formulario.paciente_id === ''"
                        @click="registrar"
                    >
                        Registrar el pago
                    </button>
                </div>
            </div>

            <p v-if="pagos.length === 0" class="tarjeta">
                No hay pagos en el período.
            </p>

            <table v-else class="tabla">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Monto</th>
                        <th>Forma</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="pago in pagos" :key="pago.id">
                        <td>{{ formatearFecha(pago.fecha_pago) }}</td>
                        <td>{{ pago.paciente }}</td>
                        <td>
                            {{ formatearPesos(pago.monto_clp) }}
                            <span v-if="pago.pago_parcial" class="aviso">(pago parcial)</span>
                        </td>
                        <td>{{ pago.metodo }}</td>
                        <td>
                            <span v-if="!pago.anulado" class="etiqueta-estado etiqueta-estado--abierta">
                                Registrado
                            </span>
                            <span v-else class="etiqueta-estado etiqueta-estado--cerrada">
                                Anulado
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="restringido">
                <p class="restringido__titulo">
                    Sobre las boletas electrónicas
                </p>
                <p>
                    Esta aplicación no emite boletas electrónicas. Guarda el número y el folio que
                    emitió el sistema que use el consultorio, si usa alguno. Emitir un documento
                    tributario sin haber verificado los requisitos vigentes sería afirmar que la
                    aplicación cumple algo que no se comprobó, así que no se hizo.
                </p>
            </div>

            <p class="aviso">
                Un pago se anula con su motivo, no se borra: la caja del consultorio tiene que poder
                cuadrar con lo que la base dice que pasó.
            </p>
        </template>
    </div>
</template>

<script setup lang="ts">
/**
 * Pagos y boletas.
 *
 * LO QUE ESTA PANTALLA DICE Y NO DICE
 * ----------------------------------
 * Dice cuánto se pagó, por quién, cuándo y cómo. No dice en qué se empleó la
 * sesión: eso vive en la agenda y en la nota, y quien tiene que verlo para
 * atender es el profesional que la condujo.
 *
 * LA MONEDA
 * ---------
 * Los montos son pesos enteros. La aplicación no trae precios: el consultorio
 * los carga en su catálogo de tarifas, y si no los ha fijado, el sistema dice
 * que no hay tarifa en vez de mostrar un cero. Ver docs/06.
 */

import type { Pago } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const cargando = ref(true)
const enviando = ref(false)
const pagos = ref<Pago[]>([])
const total = ref(0)

const formulario = reactive({
    paciente_id: '',
    monto_clp: 0,
    metodo: 'efectivo',
    fecha_pago: new Date().toISOString().slice(0, 16),
})

const registrar = async () => {
    enviando.value = true

    try {
        await $fetch('/api/pagos', {
            method: 'POST',
            body: { ...formulario },
        })

        await cargar()
    } finally {
        enviando.value = false
    }
}

const cargar = async () => {
    cargando.value = true

    try {
        const respuesta = await $fetch<{ datos: Pago[], total_clp: number }>('/api/pagos')

        pagos.value = respuesta.datos
        total.value = respuesta.total_clp
    } finally {
        cargando.value = false
    }
}

const formatearPesos = (monto: number): string => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        maximumFractionDigits: 0,
    }).format(monto)
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