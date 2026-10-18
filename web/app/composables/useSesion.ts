/**
 * La sesión: quién está adentro y qué puede ver.
 *
 * QUE HACE ESTE ARCHIVO
 * --------------------
 * Mantiene el perfil de la persona que está en la pantalla, que viene de
 * `GET /api/v1/auth/yo`.
 *
 * POR QUE EL PERFIL ES LO IMPORTANTE
 * ----------------------------------
 * Porque en esta aplicación hay pantallas que se ven completas para un rol y
 * vacías para otro, y una pantalla vacía sin explicación es la peor
 * experiencia posible: la persona no sabe si rompió algo o si es que no puede.
 *
 * Entonces el perfil trae `explicacion` y `acceso_al_contenido`, que son
 * frases escritas por el dominio para eso. La interfaz las muestra, no las
 * inventa.
 *
 * QUE NO ES UNA MEDIDA DE SEGURIDAD
 * --------------------------------
 * Ocultar un enlace en el menú no protege nada: el navegador es del lado del
 * cliente y quien sepa escribir la URL la escribe. El permiso de verdad lo
 * comprueba la API, y esa comprobación está en `NotaSesionPolicy` y en la Row
 * Level Security. Ver docs/03.
 *
 * ESTE ARCHIVO ES LA EXPERIENCIA, NO LA SEGURIDAD
 */

import type { PerfilUsuario } from '~/tipos/api'

export function useSesion() {
    const usuario = useState<PerfilUsuario | null>('perfil', () => null)
    const cargado = useState<boolean>('perfil-cargado', () => false)

    /**
     * Carga el perfil si no está cargado.
     *
     * No vuelve a pedirlo si ya se trajo: la pantalla de inicio, el menú y cada
     * página necesitan el mismo dato, y pedirlo tres veces sería tres
     * oportunidades de que una falle.
     */
    const cargar = async (forzar = false): Promise<PerfilUsuario | null> => {
        if (cargado.value && !forzar && usuario.value !== null) {
            return usuario.value
        }

        try {
            const respuesta = await $fetch<{ datos: PerfilUsuario }>('/api/auth/yo')

            usuario.value = respuesta.datos
            cargado.value = true

            return usuario.value
        } catch {
            // Sin sesión es lo normal antes de entrar, y no es un error que
            // haya que mostrarle a nadie.
            usuario.value = null
            cargado.value = true

            return null
        }
    }

    /**
     * Cierra la sesión.
     */
    const salir = async (): Promise<void> => {
        try {
            await $fetch('/api/auth/salir', { method: 'POST' })
        } finally {
            usuario.value = null
            cargado.value = false
        }

        await navigateTo('/entrar')
    }

    /**
     * Si esta persona abre el contenido de las sesiones.
     *
     * Se usa para mostrar o esconder la entrada del menú. Para saber si PUEDE,
     * hay que preguntarle a la API: este valor solo sirve para no ofrecer una
     * pantalla que va a dar 404.
     */
    const veNotas = (): boolean => usuario.value?.ve_notas ?? false

    /**
     * Si esta persona atiende.
     */
    const esTratante = (): boolean => usuario.value?.es_tratante ?? false

    /**
     * La frase que explica el alcance del rol.
     */
    const explicacion = (): string => {
        return usuario.value?.explicacion ?? 'No hay sesión abierta.'
    }

    /**
     * La frase que explica el acceso al contenido de las sesiones.
     */
    const accesoAlContenido = (): string => {
        return usuario.value?.acceso_al_contenido
            ?? 'Esta cuenta no abre el contenido de las sesiones.'
    }

    return {
        usuario,
        cargado,
        cargar,
        salir,
        veNotas,
        esTratante,
        explicacion,
        accesoAlContenido,
    }
}