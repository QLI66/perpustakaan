import tailwindcss from '@tailwindcss/vite'
import Aura from '@primeuix/themes/aura'

export default defineNuxtConfig({
    compatibilityDate: '2025-07-15',

    devtools: {
        enabled: true,
    },

    modules: [
        '@nuxt/eslint',
        '@pinia/nuxt',
        '@primevue/nuxt-module',
    ],

    css: [
        '~/assets/css/main.css',
    ],

    vite: {
        plugins: [
            tailwindcss(),
        ],
        optimizeDeps: {
            include: [
                '@vue/devtools-core',
                'jspdf',
                'xlsx',
            ],
        },
    },

    primevue: {
        options: {
            ripple: true,
            theme: {
                preset: Aura,
                options: {
                    prefix: 'p',
                    darkModeSelector: 'system',
                    cssLayer: false,
                },
            },
        },
    },

    runtimeConfig: {
        public: {
            apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'http://127.0.0.1/api',
        },
    },

    typescript: {
        strict: true,
        typeCheck: true,
    },
})
