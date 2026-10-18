// Configuración de la interfaz de Psicología (Nuxt con render en el servidor).
//
// Las versiones están fijadas por rango mayor, no exacto, a propósito: una
// corrección de seguridad dentro de la rama compatible no debería exigir
// rehacer la imagen. El rango mayor viene de las demás aplicaciones de Hefesto,
// que usan el mismo esqueleto.
//
// LO QUE ESTA CONFIGURACIÓN DICE Y LO QUE NO
// ------------------------------------------
// Dice cómo se dibuja la aplicación. No dice qué puede ver cada persona: eso
// lo decide la API, y la interfaz solo lo muestra.
//
// La razón es que el navegador es del lado del cliente. Un `if` en la
// La razón es que el navegador es del lado del cliente: quien sepa abrir las
// herramientas del navegador ve lo que quiera. El permiso se comprueba en la
// API, y lo que la interfaz hace es explicarlo. Ver docs/03.

export default defineNuxtConfig({
    // La interfaz se renderiza en el servidor. Motivo concreto: el nombre de
    // un paciente y el contenido de una sesión no deben quedar en el HTML de
    // una página cacheable, y una pantalla que se dibuja en el servidor no
    // depende de que el JavaScript haya cargado para no filtrar nada.
    ssr: true,

    compatibilityDate: '2026-10-18',

    devtools: { enabled: false },

    runtimeConfig: {
        // URL interna de la API de Laravel. Solo la usa el servidor de Nuxt:
        // nunca viaja al navegador.
        apiBase: process.env.NUXT_API_BASE || 'http://api:8000',

        public: {
            // Rango de agenda por omisión, en días.
            diasAgenda: Number(process.env.NUXT_DIAS_AGENDA || 1),
        },
    },

    css: ['~/assets/css/main.css'],

    app: {
        head: {
            htmlAttrs: { lang: 'es-CL' },
            title: 'Psicología',
            meta: [
                { charset: 'utf-8' },
                { name: 'viewport', content: 'width=device-width, initial-scale=1' },
                // La aplicación no se indexa: no es un sitio público, y no
                // tiene nada que un buscador deba encontrar.
                { name: 'robots', content: 'noindex, nofollow' },
                { name: 'referrer', content: 'same-origin' },
            ],
        },
    },

    nitro: {
        // El servidor de Nuxt reenvía la cookie de sesión de Laravel. Por eso
        // la aplicación NO necesita ningún token propio en el navegador.
        compressPublicAssets: true,
    },

    routeRules: {
        // Nada que dependa de una sesión se cachea en un proxy. Un nombre de
        // paciente en una caché compartida sería un nombre de paciente en un
        // disco que nadie está mirando.
        '/**': { headers: { 'cache-control': 'no-store' } },
        '/entrar': { ssr: true },
    },
})