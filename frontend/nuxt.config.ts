// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  ssr: false,
  devtools: { enabled: true },

  modules: [
    'vuetify-nuxt-module',
    '@pinia/nuxt',
    '@pinia-plugin-persistedstate/nuxt',
    '@nuxtjs/i18n',
    '@vite-pwa/nuxt',
  ],

  vuetify: {
    moduleOptions: { importComposables: true },
    vuetifyOptions: {
      theme: {
        defaultTheme: 'tracacajou',
        themes: {
          tracacajou: {
            dark: false,
            colors: {
              primary:    '#2D5A27',
              secondary:  '#E65100',
              error:      '#B71C1C',
              success:    '#388E3C',
              background: '#F5F5F5',
            },
          },
        },
      },
    },
  },

  i18n: {
    locales: [
      { code: 'fr', name: 'Français', file: 'fr.json' },
      { code: 'en', name: 'English',  file: 'en.json' },
    ],
    defaultLocale: 'fr',
    langDir: 'locales/',
    strategy: 'no_prefix',
  },

  pwa: {
    registerType: 'autoUpdate',
    manifest: {
      name: 'TraçaCajou',
      short_name: 'TracaCajou',
      description: 'Certification d\'origine cajou — Bénin',
      theme_color: '#2D5A27',
      background_color: '#FFFFFF',
      display: 'standalone',
      orientation: 'portrait',
      icons: [
        { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
        { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
      ],
    },
    workbox: {
      navigateFallback: null,
      globPatterns: ['**/*.{js,css,html,ico,png,svg}'],
      runtimeCaching: [
        {
          urlPattern: ({ url }: { url: URL }) => url.pathname.startsWith('/api/v1'),
          handler: 'NetworkFirst' as const,
          options: { cacheName: 'api-cache', networkTimeoutSeconds: 5 },
        },
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000',
    },
  },
})
