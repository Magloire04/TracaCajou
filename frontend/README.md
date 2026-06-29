# TraçaCajou — Front-end Nuxt 4 PWA

PWA mobile-first pour les agents de coopérative : saisie offline de lots cajou, synchronisation différée, et vérification publique de certificats ECDSA P-384.

## Stack

- Nuxt 4 · Vue 3 · Vuetify 3 · Pinia (persistedstate) · Dexie.js · @nuxtjs/i18n (FR + EN) · @vite-pwa/nuxt

## Installation

```bash
npm install
cp .env.example .env.local
# Éditer .env.local : VITE_API_BASE_URL=http://localhost:8000
npm run dev
```

## Variables d'environnement

| Variable | Description | Défaut |
|---|---|---|
| `VITE_API_BASE_URL` | URL du back-end Laravel | `http://localhost:8000` |

## Tests

```bash
# Unitaires (Vitest)
npx vitest run

# E2E Playwright (requiert back-end sur :8000 et front sur :3000)
npx playwright test
```

## Architecture

- `app/pages/` — Routes (login, producteurs, lots, certificats)
- `app/components/` — Composants UI (layout, lots, producteurs, certificats)
- `app/composables/` — Logique réutilisable (useApi, useAuth, useOfflineSync)
- `app/stores/` — État global Pinia (auth, sync)
- `app/services/` — Services métier (SyncQueue, Dexie database)
- `i18n/locales/` — Traductions FR et EN
