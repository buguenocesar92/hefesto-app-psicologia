/**
 * Cliente interno hacia la API de Laravel (el servidor de Nuxt hace de
 * intermediario).
 *
 * QUE HACE ESTE ARCHIVO
 * ---------------------
 * El navegador nunca habla directo con la API. Habla con el servidor de Nuxt,
 * que reenvía la cookie de sesión. Esa es la única razón por la que no hay
 * ningún token en el código del navegador.
 *
 * POR QUE SE REENVIA LA CABECERA X-XSRF-TOKEN
 * -------------------------------------------
 * Laravel valida el token CSRF en las peticiones que modifican datos. Con el
 * controlador de sesión `web`, el token viaja en la cookie `XSRF-TOKEN`, que se
 * puede leer desde JavaScript, y hay que devolverlo en la cabecera
 * `X-XSRF-TOKEN`. Si se olvida, todos los POST devuelven 419 y el error se
 * vuelve muy difícil de entender.
 *
 * QUE NO HACE
 * -----------
 * No reintenta, no cachea y no "corrige" errores. Un 404 de la API se devuelve
 * como 404.
 *
 * OJO CON EL 404
 * --------------
 * En esta aplicación un 404 significa dos cosas distintas: o que el recurso no
 * existe, o que el permiso lo rechazó y el servidor lo escondió para no
 * confirmar que existe. La interfaz no puede distinguirlas, y no debe
 * intentarlo. Lo que sí hace es mostrar una explicación del rol, que viene de
 * `GET /api/v1/auth/yo`, no de adivinar a partir del 404.
 */

import type { H3Event } from 'h3'

/**
 * Error de la API, con su código intacto.
 */
export class ErrorDeApi extends Error {
    constructor(
        public readonly codigo: number,
        public readonly cuerpo: unknown,
        message: string,
    ) {
        super(message)
        this.name = 'ErrorDeApi'
    }
}

export interface OpcionesLlamada {
    metodo?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
    cuerpo?: unknown
    consulta?: Record<string, string | number | boolean | undefined>
}

/**
 * Llama a la API reenviando la sesión del navegador.
 */
export async function llamarApi<T>(
    evento: H3Event,
    ruta: string,
    opciones: OpcionesLlamada = {},
): Promise<T> {
    const config = useRuntimeConfig(evento)
    const metodo = opciones.metodo ?? 'GET'

    const cabeceras: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    }

    const cookies = getRequestHeader(evento, 'cookie') ?? ''

    if (cookies !== '') {
        cabeceras.Cookie = cookies
    }

    const tokenCsrf = leerCookie(evento, 'XSRF-TOKEN')

    if (tokenCsrf !== null) {
        cabeceras['X-XSRF-TOKEN'] = decodeURIComponent(tokenCsrf)
    }

    let cuerpo: string | undefined

    if (opciones.cuerpo !== undefined) {
        cabeceras['Content-Type'] = 'application/json'
        cuerpo = JSON.stringify(opciones.cuerpo)
    }

    const respuesta = await $fetch.raw(`${config.apiBase}/api/v1${ruta}`, {
        method: metodo,
        headers: cabeceras,
        body: cuerpo,
        query: opciones.consulta,
        // Las cookies de la API se emiten en el servidor de Nuxt; por eso hay
        // que copiar las que devuelve Laravel.
        onResponse: async ({ headers }) => {
            for (const cookie of leerSetCookie(headers)) {
                appendResponseHeader(evento, 'set-cookie', cookie)
            }
        },
        // La API devuelve 404 y 422 a propósito (ver bootstrap/app.php). Sin
        // esto, $fetch lanzaría por cualquier estado 4xx y se perdería el
        // cuerpo de la respuesta, que es donde está el mensaje que hay que
        // mostrarle a la persona.
        ignoreResponseError: true,
    })

    const datos = respuesta._data ?? null

    if (respuesta.status >= 400) {
        throw new ErrorDeApi(
            respuesta.status,
            datos,
            mensajeDeError(respuesta.status, datos),
        )
    }

    return datos as T
}

/**
 * Elige el mensaje que se le muestra a la persona.
 *
 * Prioriza el mensaje de la API: lo escribe el dominio, y es el único que sabe
 * por qué no se puede hacer lo que se intentaba. Un texto genérico del
 * servidor de Nuxt sería menos útil y taparía la causa real.
 */
function mensajeDeError(codigo: number, datos: unknown): string {
    if (datos && typeof datos === 'object') {
        const objeto = datos as Record<string, unknown>

        if (typeof objeto.mensaje === 'string' && objeto.mensaje !== '') {
            return objeto.mensaje
        }

        // Errores de validación de Laravel: se muestran los del primer campo.
        const errores = objeto.errores

        if (errores && typeof errores === 'object') {
            for (const valor of Object.values(errores as Record<string, unknown>)) {
                if (Array.isArray(valor) && typeof valor[0] === 'string') {
                    return valor[0]
                }
            }
        }
    }

    if (codigo === 401) {
        return 'La sesión no es válida o venció. Entre de nuevo.'
    }

    if (codigo === 403) {
        return 'Esta acción es de la administración del consultorio, o su cuenta no está habilitada.'
    }

    if (codigo === 404) {
        return 'No encontrado. Si cree que debería existir, consulte con la dirección del consultorio.'
    }

    if (codigo === 405) {
        return 'Esta operación no está disponible en la aplicación.'
    }

    if (codigo === 419) {
        return 'La sesión expiró mientras se Guardaba. Entre de nuevo.'
    }

    return 'La petición no se pudo completar.'
}

/**
 * Lee una cookie del navegador que llegó al servidor de Nuxt.
 */
function leerCookie(evento: H3Event, nombre: string): string | null {
    const cabecera = getRequestHeader(evento, 'cookie')

    if (!cabecera) {
        return null
    }

    for (const parte of cabecera.split(';')) {
        const [clave, ...resto] = parte.trim().split('=')

        if (clave === nombre) {
            return resto.join('=')
        }
    }

    return null
}

/**
 * Cookies que devuelve la API y hay que reenviar al navegador.
 */
function leerSetCookie(headers: Headers): string[] {
    const cabecera = headers.get('set-cookie')

    if (!cabecera) {
        return []
    }

    // En Node varias cookies llegan separadas por ", " menos cuando el valor
    // contiene una coma (las fechas lo hacen). Se separan por el patrón
    // `nombre=valor;` en lugar de por comas a secas.
    return cabecera.split(/,\s*(?=[^;,\s]+=)/)
}