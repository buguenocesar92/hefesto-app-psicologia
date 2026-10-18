<template>
    <div>
        <h1>Inicio</h1>

        <p v-if="cargando" class="aviso">
            Cargando...
        </p>

        <template v-else-if="panel">
            <!--
                LA EXPLICACIÓN DEL ROL, ARRIBA DE TODO
                ------------------------------------------
                Es lo más importante de esta pantalla. Una persona que entra y
                ve menos de lo que esperaba necesita saber si es que rompió algo
                o es que su rol no alcanza. La frase sale de la API, escrita por
                el dominio. Ver docs/03.
            -->
            <div class="tarjeta">
                <p class="tarjeta__titulo">
                    {{ panel.rol_etiqueta }}
                </p>

                <p>{{ panel.explicacion }}</p>

                <div
                    v-if="!panel.es_tratante"
                    class="restringido"
                    style="margin-top: 0.8rem"
                >
                    <p class="restringido__titulo">
                        Sobre el contenido de las sesiones
                    </p>
                    <p>{{ panel.acceso_al_contenido }}</p>
                </div>
            </div>

            <div class="rejilla">
                <div class="tarjeta">
                    <p class="aviso">
                        Citas de hoy
                    </p>
                    <p class="cifra">
                        {{ panel.agenda.citas_hoy }}
                    </p>
                </div>

                <div class="tarjeta">
                    <p class="aviso">
                        Derivaciones abiertas
                    </p>
                    <p class="cifra">
                        {{ panel.derivaciones.abiertas }}
                    </p>
                    <p
                        v-if="panel.derivaciones.exigen_contacto_inmediato > 0"
                        class="alerta"
                        style="padding: 0.4rem 0.6rem; margin-top: 0.5rem"
                    >
                        {{ panel.derivaciones.exigen_contacto_inmediato }} exigen contacto el mismo día.
                    </p>
                </div>

                <div class="tarjeta">
                    <p class="aviso">
                        Recaudado hoy
                    </p>
                    <p class="cifra">
                        {{ formatearPesos(panel.pagos.total_clp_hoy) }}
                    </p>
                </div>
            </div>

            <!--
                LAS NOTAS SOLO APARECEN SI LA PERSONA ATIENDE
                ------------------------------------------------
                No es un bloque con ceros cuando no puede verlas: es que el
                bloque no está. Mostrar "0 notas" a un administrativo sería
                afirmar una cosa que no tiene derecho a saber.
            -->
            <div v-if="panel.notas" class="tarjeta">
                <p class="tarjeta__titulo">
                    Mis notas de sesión
                </p>

                <div class="rejilla">
                    <div>
                        <p class="aviso">
                            Abiertas
                        </p>
                        <p class="cifra">
                            {{ panel.notas.abiertas }}
                        </p>
                    </div>

                    <div>
                        <p class="aviso">
                            Cerradas
                        </p>
                        <p class="cifra">
                            {{ panel.notas.cerradas_por_mi }}
                        </p>
                    </div>

                    <div>
                        <p class="aviso">
                            Con correcciones
                        </p>
                        <p class="cifra">
                            {{ panel.notas.con_correcciones }}
                        </p>
                    </div>

                    <div>
                        <p class="aviso">
                            Sesiones sin nota
                        </p>
                        <p class="cifra">
                            {{ panel.notas.sesiones_sin_nota }}
                        </p>
                    </div>
                </div>
            </div>

            <div v-else-if="aclaracionDeAlcance" class="restringido">
                <p class="restringido__titulo">
                    Por qué aquí no hay notas de sesión
                </p>
                <p>{{ aclaracionDeAlcance }}</p>
            </div>
        </template>

        <p v-if="error" class="alerta">
            {{ error }}
        </p>
    </div>
</template>

<script setup lang="ts">
/**
 * La pantalla de inicio.
 *
 * QUE TRAE
 * --------
 * `GET /api/v1/panel`, que devuelve lo que el rol PUEDE ver. Si a un
 * administrativo no le corresponde ninguna nota, la respuesta no trae la llave
 * `notas`: no es que haya cero, es que no es suyo.
 *
 * LA MONEDA
 * ---------
 * El panel trae pesos enteros, porque los pesos chilenos no tienen parte
 * decimal en la práctica. El formato se pone acá, no en la API: la API manda
 * números y quien los muestra decide cómo.
 */

import type { Panel } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const cargando = ref(true)
const panel = ref<Panel | null>(null)
const error = ref<string | null>(null)

const { usuario, accesoAlContenido } = useSesion()

/**
 * La frase que explica por qué no hay notas.
 *
 * Viene del perfil, que la API escribió. Acá solo se muestra.
 */
const aclaracionDeAlcance = computed(() => {
    if (usuario.value?.ve_notas) {
        return null
    }

    return accesoAlContenido()
})

const formatearPesos = (monto: number): string => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        maximumFractionDigits: 0,
    }).format(monto)
}

onMounted(async () => {
    try {
        const respuesta = await $fetch<{ datos: Panel }>('/api/panel')

        panel.value = respuesta.datos
    } catch (e: unknown) {
        error.value = 'No se pudo cargar el resumen del día. Recargue la pantalla.'
    } finally {
        cargando.value = false
    }
})
</script>