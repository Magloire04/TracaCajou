# TraçaCajou — Front-end Nuxt 3 PWA — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construire la PWA Nuxt 3 / Vue 3 de TraçaCajou : interface agent mobile-first (Android), saisie de lots offline avec Dexie.js + synchro hybride, et page de vérification publique brandée — consommant l'API REST du Plan 1.

**Architecture:** Nuxt 3 SPA (client-side rendering côté agent, SSG pour la page de vérification publique), Vuetify 3 pour l'UI Material Design mobile-first, Pinia pour l'état global, `$fetch` Nuxt + CSRF Sanctum pour les appels API, Dexie.js pour le stockage offline.

**Tech Stack:** Nuxt 3 · Vue 3 Composition API · Vuetify 3 (vuetify-nuxt-module) · Pinia · Dexie.js · @nuxtjs/i18n v9 · @vite-pwa/nuxt · Vitest · Playwright · TypeScript

## Global Constraints

- Répertoire projet : `frontend/` (sous-dossier du repo, Nuxt installé ici).
- **Mobile-first Android** : toutes les pages sont conçues pour smartphones (breakpoint `xs`), Vuetify en mode `mobile`.
- **Langues** : Français (défaut) + English — tout texte UI passe par `$t('...')`, jamais de chaîne en dur.
- **APDP** : aucune donnée nominative de producteur dans les URLs, logs, ou localStorage — uniquement dans les requêtes authentifiées.
- **Auth Sanctum SPA** : chaque appel API authentifié inclut `credentials: 'include'` et le header `X-XSRF-TOKEN`. Avant le premier POST/login, appeler `GET /sanctum/csrf-cookie`.
- **Cookie Sanctum** : le cookie `XSRF-TOKEN` est lu côté client (`document.cookie`) et injecté comme header — jamais stocké en localStorage.
- **Variables d'environnement** : préfixe `VITE_` pour exposition côté client. `VITE_API_BASE_URL=http://localhost:8000`.
- **Palette Vuetify** : primary `#2D5A27` (vert cajou), secondary `#E65100` (orange anacarde).
- **Codes lot/producteur** : générés côté client avec le package `ulid` (format horodatage) pour l'offline-first.
- **Tests** : Vitest pour unitaires (composables, utils), Playwright pour E2E.
- **Zéro `v-html`** sur contenu non contrôlé. Validation Vuetify pour tous les formulaires.

---

## Carte des fichiers

```
frontend/
├── nuxt.config.ts
├── app.vue
├── pages/
│   ├── index.vue                      (redirect auth → /lots, sinon /login)
│   ├── login.vue                      (page de connexion)
│   ├── producteurs/
│   │   ├── index.vue                  (liste paginée)
│   │   └── nouveau.vue                (formulaire d'enrôlement)
│   ├── lots/
│   │   ├── index.vue                  (dashboard — liste + badge sync)
│   │   └── nouveau.vue                (formulaire de saisie offline-capable)
│   └── certificats/
│       └── [uuid]/
│           └── verify.vue             (page publique de vérification)
├── components/
│   ├── layout/
│   │   ├── AppTopBar.vue              (titre + langue switcher)
│   │   └── AppBottomNav.vue           (navigation Producteurs / Lots / Sync)
│   ├── lots/
│   │   ├── LotForm.vue
│   │   ├── LotCard.vue
│   │   └── SyncStatusChip.vue         (badge compteur + bouton sync)
│   ├── producteurs/
│   │   ├── ProducteurForm.vue
│   │   └── ProducteurCard.vue
│   ├── certificats/
│   │   └── VerifyResult.vue           (résultat vérification — authentique/non)
│   └── common/
│       └── OfflineBanner.vue          (bandeau réseau hors-ligne)
├── composables/
│   ├── useApi.ts                      (fetch Nuxt + CSRF + credentials)
│   ├── useAuth.ts                     (login, logout, état agent)
│   └── useOfflineSync.ts              (navigator.onLine + SyncQueue)
├── stores/
│   ├── auth.ts                        (Pinia — agent, cooperative)
│   └── sync.ts                        (Pinia — lots en attente, statut)
├── services/
│   ├── database.ts                    (Dexie.js — schéma lotsEnAttente)
│   └── syncQueue.ts                   (SyncQueue — push, retry, status)
├── middleware/
│   └── auth.ts                        (protection routes agent)
├── locales/
│   ├── fr.json
│   └── en.json
├── plugins/
│   └── vuetify.ts                     (thème TraçaCajou)
└── tests/
    ├── unit/
    │   ├── syncQueue.test.ts
    │   └── useAuth.test.ts
    └── e2e/
        └── login-lot-verify.spec.ts   (Playwright)
```

---

### Task 1 : Scaffolding Nuxt 3 + Vuetify 3 + PWA + i18n

**Files:**
- Create: `frontend/` (projet Nuxt 3)
- Create: `frontend/nuxt.config.ts`
- Create: `frontend/app.vue`
- Create: `frontend/plugins/vuetify.ts`

**Interfaces:**
- Produces: projet Nuxt 3 fonctionnel avec Vuetify 3, PWA, i18n — `npm run dev` démarre sans erreur

- [ ] **Step 1 : Créer le projet Nuxt 3**

```bash
cd TracaCajou
npx nuxi@latest init frontend --package-manager npm
cd frontend
npm install
```

- [ ] **Step 2 : Installer les dépendances**

```bash
npm install vuetify@^3 vuetify-nuxt-module @mdi/font
npm install pinia @pinia/nuxt
npm install dexie
npm install @nuxtjs/i18n
npm install @vite-pwa/nuxt
npm install ulid
npm install --save-dev vitest @vue/test-utils @nuxt/test-utils jsdom
npm install --save-dev @playwright/test
```

- [ ] **Step 3 : Configurer `nuxt.config.ts`**

```ts
export default defineNuxtConfig({
  ssr: false,
  devtools: { enabled: true },

  modules: [
    'vuetify-nuxt-module',
    '@pinia/nuxt',
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
              primary:   '#2D5A27',
              secondary: '#E65100',
              error:     '#B71C1C',
              success:   '#388E3C',
              background:'#F5F5F5',
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
    lazy: true,
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
          urlPattern: ({ url }) => url.pathname.startsWith('/api/v1'),
          handler: 'NetworkFirst',
          options: { cacheName: 'api-cache', networkTimeoutSeconds: 5 },
        },
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.VITE_API_BASE_URL ?? 'http://localhost:8000',
    },
  },
})
```

- [ ] **Step 4 : Créer `app.vue` avec layout Vuetify de base**

```vue
<template>
  <v-app :theme="'tracacajou'">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </v-app>
</template>
```

- [ ] **Step 5 : Créer les fichiers de locale vides**

`frontend/locales/fr.json` :
```json
{
  "app": { "name": "TraçaCajou" },
  "auth": { "login": "Connexion", "logout": "Déconnexion", "email": "Email", "password": "Mot de passe", "submit": "Se connecter" },
  "lots": { "title": "Lots", "new": "Nouveau lot", "sync": "Synchroniser" },
  "producteurs": { "title": "Producteurs", "new": "Enrôler" },
  "offline": { "banner": "Mode hors-ligne — les lots seront synchronisés à la reconnexion." },
  "errors": { "required": "Champ obligatoire", "network": "Erreur réseau" }
}
```

`frontend/locales/en.json` :
```json
{
  "app": { "name": "TraçaCajou" },
  "auth": { "login": "Login", "logout": "Logout", "email": "Email", "password": "Password", "submit": "Sign in" },
  "lots": { "title": "Lots", "new": "New lot", "sync": "Synchronize" },
  "producteurs": { "title": "Producers", "new": "Enroll" },
  "offline": { "banner": "Offline mode — lots will sync when reconnected." },
  "errors": { "required": "Required field", "network": "Network error" }
}
```

- [ ] **Step 6 : Vérifier que le projet démarre**

```bash
cd frontend && npm run dev
```
Expected: `Nuxt 3 ready on http://localhost:3000` sans erreur.

- [ ] **Step 7 : Commit**

```bash
git add frontend/
git commit -m "feat(frontend): scaffolding Nuxt 3 Vuetify 3 PWA i18n"
```

---

### Task 2 : API client + Auth store + login page

**Files:**
- Create: `frontend/composables/useApi.ts`
- Create: `frontend/stores/auth.ts`
- Create: `frontend/composables/useAuth.ts`
- Create: `frontend/middleware/auth.ts`
- Create: `frontend/pages/login.vue`
- Create: `frontend/tests/unit/useAuth.test.ts`

**Interfaces:**
- Produces:
  - `useApi(): { get, post, delete }` — fetch avec CSRF + credentials
  - `useAuth(): { login(email, password), logout(), agent, isAuthenticated }`
  - Route `/login` fonctionnelle

- [ ] **Step 1 : Écrire les tests (RED)**

`frontend/tests/unit/useAuth.test.ts` :
```ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '~/stores/auth'

describe('auth store', () => {
  beforeEach(() => { setActivePinia(createPinia()) })

  it('est non authentifié par défaut', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
    expect(store.agent).toBeNull()
  })

  it('setAgent met à jour l\'état', () => {
    const store = useAuthStore()
    store.setAgent({ id: '01H...', prenom: 'Kossi', nom: 'Hounsou', cooperative_id: 'c1', cooperative_code: 'AGPK' })
    expect(store.isAuthenticated).toBe(true)
    expect(store.agent?.cooperative_code).toBe('AGPK')
  })

  it('clearAgent réinitialise l\'état', () => {
    const store = useAuthStore()
    store.setAgent({ id: '01H...', prenom: 'Kossi', nom: 'H', cooperative_id: 'c1', cooperative_code: 'AGPK' })
    store.clearAgent()
    expect(store.isAuthenticated).toBe(false)
  })
})
```

- [ ] **Step 2 : Vérifier que le test échoue**

```bash
cd frontend && npx vitest run tests/unit/useAuth.test.ts
```
Expected: `FAIL — useAuthStore not found`.

- [ ] **Step 3 : Créer le store auth**

`frontend/stores/auth.ts` :
```ts
import { defineStore } from 'pinia'

interface Agent {
  id: string
  prenom: string
  nom: string
  cooperative_id: string
  cooperative_code: string
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    agent: null as Agent | null,
  }),
  getters: {
    isAuthenticated: (state) => state.agent !== null,
  },
  actions: {
    setAgent(agent: Agent) { this.agent = agent },
    clearAgent()           { this.agent = null },
  },
  persist: true,  // persistance session via pinia-plugin-persistedstate
})
```

- [ ] **Step 4 : Créer `useApi.ts`**

`frontend/composables/useApi.ts` :
```ts
export function useApi() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase

  function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    return match ? decodeURIComponent(match[1]) : ''
  }

  async function initCsrf() {
    await $fetch('/sanctum/csrf-cookie', { baseURL, credentials: 'include' })
  }

  async function get<T>(path: string): Promise<T> {
    return $fetch<T>(`/api/v1${path}`, {
      baseURL,
      credentials: 'include',
      headers: { 'Accept': 'application/json' },
    })
  }

  async function post<T>(path: string, body: unknown): Promise<T> {
    return $fetch<T>(`/api/v1${path}`, {
      method: 'POST',
      baseURL,
      credentials: 'include',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body,
    })
  }

  async function del<T>(path: string): Promise<T> {
    return $fetch<T>(`/api/v1${path}`, {
      method: 'DELETE',
      baseURL,
      credentials: 'include',
      headers: {
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
    })
  }

  return { get, post, del, initCsrf }
}
```

- [ ] **Step 5 : Créer `useAuth.ts`**

`frontend/composables/useAuth.ts` :
```ts
export function useAuth() {
  const store = useAuthStore()
  const api   = useApi()

  async function login(email: string, password: string) {
    await api.initCsrf()
    const res = await api.post<{ data: { id: string; prenom: string; nom: string; cooperative_id: string; cooperative_code: string } }>(
      '/auth/login', { email, password }
    )
    store.setAgent(res.data)
  }

  async function logout() {
    await api.post('/auth/logout', {})
    store.clearAgent()
    await navigateTo('/login')
  }

  return { login, logout, agent: computed(() => store.agent), isAuthenticated: computed(() => store.isAuthenticated) }
}
```

- [ ] **Step 6 : Créer le middleware auth**

`frontend/middleware/auth.ts` :
```ts
export default defineNuxtRouteMiddleware((to) => {
  const store = useAuthStore()
  if (!store.isAuthenticated && to.path !== '/login') {
    return navigateTo('/login')
  }
  if (store.isAuthenticated && to.path === '/login') {
    return navigateTo('/lots')
  }
})
```

- [ ] **Step 7 : Créer la page login**

`frontend/pages/login.vue` :
```vue
<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="4">
        <v-card elevation="4" rounded="lg" class="pa-4">
          <v-card-title class="text-center text-primary text-h5 mb-2">
            🌿 TraçaCajou
          </v-card-title>
          <v-card-subtitle class="text-center mb-4">{{ $t('auth.login') }}</v-card-subtitle>

          <v-form ref="form" @submit.prevent="handleLogin">
            <v-text-field
              v-model="email"
              :label="$t('auth.email')"
              type="email"
              :rules="[v => !!v || $t('errors.required')]"
              prepend-inner-icon="mdi-email"
              variant="outlined"
              class="mb-2"
            />
            <v-text-field
              v-model="password"
              :label="$t('auth.password')"
              type="password"
              :rules="[v => !!v || $t('errors.required')]"
              prepend-inner-icon="mdi-lock"
              variant="outlined"
              class="mb-4"
            />
            <v-alert v-if="error" type="error" class="mb-3" density="compact">{{ error }}</v-alert>
            <v-btn
              type="submit"
              color="primary"
              block
              size="large"
              :loading="loading"
            >
              {{ $t('auth.submit') }}
            </v-btn>
          </v-form>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth', layout: 'empty' })

const { login } = useAuth()
const email    = ref('')
const password = ref('')
const loading  = ref(false)
const error    = ref<string | null>(null)

async function handleLogin() {
  loading.value = true
  error.value = null
  try {
    await login(email.value, password.value)
    await navigateTo('/lots')
  } catch {
    error.value = 'Email ou mot de passe incorrect.'
  } finally {
    loading.value = false
  }
}
</script>
```

- [ ] **Step 8 : Vérifier que les tests passent**

```bash
cd frontend && npx vitest run tests/unit/useAuth.test.ts
```
Expected: `Tests: 3 passed`.

- [ ] **Step 9 : Commit**

```bash
git add frontend/
git commit -m "feat(frontend): useApi CSRF Sanctum + auth store + login page"
```

---

### Task 3 : Layout principal (AppTopBar + AppBottomNav + OfflineBanner)

**Files:**
- Create: `frontend/layouts/default.vue`
- Create: `frontend/layouts/empty.vue`
- Create: `frontend/components/layout/AppTopBar.vue`
- Create: `frontend/components/layout/AppBottomNav.vue`
- Create: `frontend/components/common/OfflineBanner.vue`
- Create: `frontend/pages/index.vue`

**Interfaces:**
- Produces: layout mobile (top bar + bottom navigation) utilisé par toutes les pages agent

- [ ] **Step 1 : Créer `layouts/empty.vue`** (pour login)

```vue
<template>
  <v-main>
    <slot />
  </v-main>
</template>
```

- [ ] **Step 2 : Créer `AppTopBar.vue`**

```vue
<template>
  <v-app-bar color="primary" elevation="2">
    <v-app-bar-title class="text-white font-weight-bold">🌿 TraçaCajou</v-app-bar-title>
    <v-spacer />
    <!-- Langue switcher -->
    <v-btn-toggle v-model="locale" mandatory density="compact" class="mr-2">
      <v-btn value="fr" size="small" variant="text" class="text-white">FR</v-btn>
      <v-btn value="en" size="small" variant="text" class="text-white">EN</v-btn>
    </v-btn-toggle>
    <!-- Déconnexion -->
    <v-btn icon="mdi-logout" variant="text" color="white" @click="logout" />
  </v-app-bar>
</template>

<script setup lang="ts">
const { locale } = useI18n()
const { logout } = useAuth()
</script>
```

- [ ] **Step 3 : Créer `AppBottomNav.vue`**

```vue
<template>
  <v-bottom-navigation color="primary" grow>
    <v-btn :to="'/producteurs'" value="producteurs">
      <v-icon>mdi-account-group</v-icon>
      <span>{{ $t('producteurs.title') }}</span>
    </v-btn>
    <v-btn :to="'/lots'" value="lots">
      <v-badge :content="pendingCount" color="error" :model-value="pendingCount > 0">
        <v-icon>mdi-package-variant</v-icon>
      </v-badge>
      <span>{{ $t('lots.title') }}</span>
    </v-btn>
  </v-bottom-navigation>
</template>

<script setup lang="ts">
const syncStore = useSyncStore()
const pendingCount = computed(() => syncStore.pendingCount)
</script>
```

- [ ] **Step 4 : Créer `OfflineBanner.vue`**

```vue
<template>
  <v-banner
    v-if="!isOnline"
    color="warning"
    icon="mdi-wifi-off"
    lines="one"
    sticky
  >
    {{ $t('offline.banner') }}
  </v-banner>
</template>

<script setup lang="ts">
const isOnline = ref(true)
onMounted(() => {
  isOnline.value = navigator.onLine
  window.addEventListener('online',  () => { isOnline.value = true })
  window.addEventListener('offline', () => { isOnline.value = false })
})
</script>
```

- [ ] **Step 5 : Créer `layouts/default.vue`**

```vue
<template>
  <AppTopBar />
  <OfflineBanner />
  <v-main class="bg-background">
    <slot />
  </v-main>
  <AppBottomNav />
</template>
```

- [ ] **Step 6 : Créer `pages/index.vue`** (redirect)

```vue
<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
await navigateTo('/lots')
</script>
```

- [ ] **Step 7 : Commit**

```bash
git add frontend/
git commit -m "feat(frontend): layout mobile AppTopBar BottomNav OfflineBanner"
```

---

### Task 4 : Dexie.js + SyncQueue service (TDD)

**Files:**
- Create: `frontend/services/database.ts`
- Create: `frontend/services/syncQueue.ts`
- Create: `frontend/stores/sync.ts`
- Create: `frontend/tests/unit/syncQueue.test.ts`

**Interfaces:**
- Produces:
  - `database.lotsEnAttente` — table Dexie
  - `SyncQueue.enqueue(lot)`, `SyncQueue.processAll()`, `SyncQueue.getPendingCount()`
  - `useSyncStore` — Pinia : `pendingCount`, `syncAll()`

- [ ] **Step 1 : Écrire les tests (RED)**

`frontend/tests/unit/syncQueue.test.ts` :
```ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { SyncQueue } from '~/services/syncQueue'

// Mock Dexie (IndexedDB non disponible en test)
vi.mock('~/services/database', () => ({
  database: {
    lotsEnAttente: {
      add:    vi.fn().mockResolvedValue(1),
      toArray: vi.fn().mockResolvedValue([
        { id: 'LOT01', code: 'AGPKL20260628', poids_kg: 100, statut: 'en_attente', tentatives: 0 }
      ]),
      delete: vi.fn().mockResolvedValue(undefined),
      update: vi.fn().mockResolvedValue(undefined),
      count:  vi.fn().mockResolvedValue(1),
    },
  },
}))

describe('SyncQueue', () => {
  it('enqueue ajoute un lot à la base locale', async () => {
    const queue = new SyncQueue(vi.fn())
    await queue.enqueue({ code: 'AGPKL20260628', poids_kg: 100, humidite_pct: 7, prix_kg_fcfa: 270, producteur_id: 'p1', cooperative_id: 'c1', date_pesee: '2026-06-29' })
    const { database } = await import('~/services/database')
    expect(database.lotsEnAttente.add).toHaveBeenCalled()
  })

  it('getPendingCount retourne le nombre de lots en attente', async () => {
    const queue = new SyncQueue(vi.fn())
    const count = await queue.getPendingCount()
    expect(count).toBe(1)
  })

  it('processAll appelle le callback pour chaque lot en attente', async () => {
    const pushFn = vi.fn().mockResolvedValue({ data: { certificat: { public_uuid: 'uuid1' } } })
    const queue  = new SyncQueue(pushFn)
    await queue.processAll()
    expect(pushFn).toHaveBeenCalledTimes(1)
  })
})
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
cd frontend && npx vitest run tests/unit/syncQueue.test.ts
```
Expected: `FAIL — SyncQueue not found`.

- [ ] **Step 3 : Créer `services/database.ts`**

```ts
import Dexie, { type Table } from 'dexie'

export interface LotEnAttente {
  id?:            number
  code:           string
  producteur_id:  string
  cooperative_id: string
  poids_kg:       number
  humidite_pct:   number
  prix_kg_fcfa:   number
  date_pesee:     string
  statut:         'en_attente' | 'en_cours' | 'erreur'
  tentatives:     number
  cree_le:        string
}

class TraçaCajouDB extends Dexie {
  lotsEnAttente!: Table<LotEnAttente, number>

  constructor() {
    super('TracaCajouDB')
    this.version(1).stores({
      lotsEnAttente: '++id, code, statut, cree_le',
    })
  }
}

export const database = new TraçaCajouDB()
```

- [ ] **Step 4 : Créer `services/syncQueue.ts`**

```ts
import { database, type LotEnAttente } from './database'

type PushFn = (lot: Omit<LotEnAttente, 'id' | 'statut' | 'tentatives' | 'cree_le'>) => Promise<unknown>

const MAX_TENTATIVES = 3

export class SyncQueue {
  constructor(private readonly pushToApi: PushFn) {}

  async enqueue(lot: Omit<LotEnAttente, 'id' | 'statut' | 'tentatives' | 'cree_le'>): Promise<void> {
    await database.lotsEnAttente.add({
      ...lot,
      statut:     'en_attente',
      tentatives: 0,
      cree_le:    new Date().toISOString(),
    })
  }

  async getPendingCount(): Promise<number> {
    return database.lotsEnAttente.count()
  }

  async processAll(): Promise<void> {
    const lots = await database.lotsEnAttente.toArray()
    for (const lot of lots) {
      if (!lot.id) continue
      if (lot.tentatives >= MAX_TENTATIVES) continue
      await database.lotsEnAttente.update(lot.id, { statut: 'en_cours' })
      try {
        await this.pushToApi(lot)
        await database.lotsEnAttente.delete(lot.id)
      } catch {
        const tentatives = lot.tentatives + 1
        await database.lotsEnAttente.update(lot.id, {
          statut: tentatives >= MAX_TENTATIVES ? 'erreur' : 'en_attente',
          tentatives,
        })
      }
    }
  }
}
```

- [ ] **Step 5 : Créer `stores/sync.ts`**

```ts
import { defineStore } from 'pinia'
import { SyncQueue } from '~/services/syncQueue'

export const useSyncStore = defineStore('sync', {
  state: () => ({ pendingCount: 0, isSyncing: false }),
  actions: {
    async refreshCount(queue: SyncQueue) {
      this.pendingCount = await queue.getPendingCount()
    },
    async syncAll(queue: SyncQueue) {
      if (this.isSyncing) return
      this.isSyncing = true
      try {
        await queue.processAll()
      } finally {
        this.isSyncing = false
        await this.refreshCount(queue)
      }
    },
  },
})
```

- [ ] **Step 6 : Vérifier que les tests passent**

```bash
cd frontend && npx vitest run tests/unit/syncQueue.test.ts
```
Expected: `Tests: 3 passed`.

- [ ] **Step 7 : Commit**

```bash
git add frontend/
git commit -m "feat(frontend): Dexie.js schema + SyncQueue TDD + sync store"
```

---

### Task 5 : composable useOfflineSync + SyncStatusChip

**Files:**
- Create: `frontend/composables/useOfflineSync.ts`
- Create: `frontend/components/lots/SyncStatusChip.vue`

**Interfaces:**
- Consumes: `SyncQueue` (Task 4), `useSyncStore` (Task 4)
- Produces: auto-sync à la reconnexion + composant bouton sync visible

- [ ] **Step 1 : Créer `useOfflineSync.ts`**

```ts
export function useOfflineSync() {
  const api       = useApi()
  const syncStore = useSyncStore()

  const pushToApi = (lot: Record<string, unknown>) =>
    api.post(`/cooperatives/${lot.cooperative_id}/lots`, lot)

  const queue = new SyncQueue(pushToApi as Parameters<typeof SyncQueue>[0])

  async function syncNow() {
    if (!navigator.onLine) return
    await syncStore.syncAll(queue)
  }

  // Auto-sync à la reconnexion réseau
  onMounted(async () => {
    await syncStore.refreshCount(queue)
    window.addEventListener('online', syncNow)
  })

  onUnmounted(() => {
    window.removeEventListener('online', syncNow)
  })

  return { syncNow, queue, isSyncing: computed(() => syncStore.isSyncing), pendingCount: computed(() => syncStore.pendingCount) }
}
```

- [ ] **Step 2 : Créer `SyncStatusChip.vue`**

```vue
<template>
  <div class="d-flex align-center ga-2 pa-2">
    <v-chip v-if="pendingCount > 0" color="warning" size="small" prepend-icon="mdi-cloud-upload-outline">
      {{ pendingCount }} {{ $t('lots.pending') }}
    </v-chip>
    <v-chip v-else color="success" size="small" prepend-icon="mdi-check-circle">
      {{ $t('lots.synced') }}
    </v-chip>
    <v-btn
      v-if="pendingCount > 0"
      color="primary"
      size="small"
      variant="tonal"
      :loading="isSyncing"
      prepend-icon="mdi-sync"
      @click="$emit('sync')"
    >
      {{ $t('lots.sync') }}
    </v-btn>
  </div>
</template>

<script setup lang="ts">
defineProps<{ pendingCount: number; isSyncing: boolean }>()
defineEmits<{ sync: [] }>()
</script>
```

Ajouter dans `locales/fr.json` : `"pending": "lot(s) en attente", "synced": "Tout synchronisé"`.
Ajouter dans `locales/en.json` : `"pending": "lot(s) pending", "synced": "All synced"`.

- [ ] **Step 3 : Commit**

```bash
git add frontend/
git commit -m "feat(frontend): useOfflineSync auto-sync + SyncStatusChip"
```

---

### Task 6 : Producteurs — liste + enrôlement (APDP)

**Files:**
- Create: `frontend/components/producteurs/ProducteurForm.vue`
- Create: `frontend/components/producteurs/ProducteurCard.vue`
- Create: `frontend/pages/producteurs/index.vue`
- Create: `frontend/pages/producteurs/nouveau.vue`

**Interfaces:**
- Consumes: `useApi.get/post` (Task 2), `useAuthStore` (Task 2)
- Produces: liste paginée + formulaire d'enrôlement avec case consentement non pré-cochée

- [ ] **Step 1 : Créer `ProducteurForm.vue`**

```vue
<template>
  <v-form ref="form" @submit.prevent="$emit('submit', formData)">
    <v-text-field v-model="formData.prenom" :label="$t('producteurs.prenom')"
      :rules="[v => !!v || $t('errors.required')]" variant="outlined" class="mb-2" />
    <v-text-field v-model="formData.nom" :label="$t('producteurs.nom')"
      :rules="[v => !!v || $t('errors.required')]" variant="outlined" class="mb-2" />
    <v-select v-model="formData.sexe" :items="[{title:'Homme',value:'M'},{title:'Femme',value:'F'}]"
      :label="$t('producteurs.sexe')" variant="outlined" class="mb-2" clearable />
    <v-text-field v-model="formData.localite" :label="$t('producteurs.localite')"
      variant="outlined" class="mb-3" />

    <!-- APDP : consentement — case non pré-cochée -->
    <v-checkbox
      v-model="formData.consentement"
      :label="$t('producteurs.consentement')"
      :rules="[v => v === true || $t('producteurs.consentement_required')]"
      color="primary"
      class="mb-3"
    />

    <v-btn type="submit" color="primary" block :loading="loading">
      {{ $t('producteurs.new') }}
    </v-btn>
  </v-form>
</template>

<script setup lang="ts">
defineProps<{ loading?: boolean }>()
defineEmits<{ submit: [data: typeof formData.value] }>()

const formData = ref({ prenom: '', nom: '', sexe: '' as 'M' | 'F' | '', localite: '', consentement: false })
</script>
```

- [ ] **Step 2 : Créer `pages/producteurs/index.vue`**

```vue
<template>
  <v-container>
    <v-row justify="space-between" align="center" class="mb-3">
      <v-col><h2 class="text-h6">{{ $t('producteurs.title') }}</h2></v-col>
      <v-col cols="auto"><v-btn color="primary" :to="'/producteurs/nouveau'" prepend-icon="mdi-plus">{{ $t('producteurs.new') }}</v-btn></v-col>
    </v-row>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3" />

    <ProducteurCard v-for="p in producteurs" :key="p.id" :producteur="p" class="mb-2" />

    <div v-if="!loading && producteurs.length === 0" class="text-center text-grey mt-8">
      <v-icon size="64" color="grey-lighten-1">mdi-account-group</v-icon>
      <p>{{ $t('producteurs.empty') }}</p>
    </div>

    <v-pagination v-if="meta.total > meta.limit" v-model="page" :length="Math.ceil(meta.total / meta.limit)" class="mt-4" />
  </v-container>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { get }   = useApi()
const authStore = useAuthStore()
const coopId    = authStore.agent!.cooperative_id

const page  = ref(1)
const limit = 20
const loading     = ref(false)
const producteurs = ref<Record<string, unknown>[]>([])
const meta        = ref({ page: 1, limit, total: 0 })

async function fetchProducteurs() {
  loading.value = true
  try {
    const res = await get<{ data: Record<string, unknown>[]; meta: typeof meta.value }>(
      `/cooperatives/${coopId}/producteurs?page=${page.value}&limit=${limit}`
    )
    producteurs.value = res.data
    meta.value = res.meta
  } finally {
    loading.value = false
  }
}

watch(page, fetchProducteurs)
onMounted(fetchProducteurs)
</script>
```

- [ ] **Step 3 : Créer `pages/producteurs/nouveau.vue`**

```vue
<template>
  <v-container>
    <v-btn :to="'/producteurs'" variant="text" prepend-icon="mdi-arrow-left" class="mb-3">{{ $t('common.back') }}</v-btn>
    <h2 class="text-h6 mb-4">{{ $t('producteurs.new') }}</h2>

    <v-alert v-if="success" type="success" class="mb-3">{{ $t('producteurs.enrolled') }}</v-alert>
    <v-alert v-if="error" type="error" class="mb-3">{{ error }}</v-alert>

    <ProducteurForm :loading="loading" @submit="handleSubmit" />
  </v-container>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { post }  = useApi()
const authStore = useAuthStore()
const coopId    = authStore.agent!.cooperative_id
const loading   = ref(false)
const success   = ref(false)
const error     = ref<string | null>(null)

async function handleSubmit(data: Record<string, unknown>) {
  loading.value = true
  error.value = null
  try {
    await post(`/cooperatives/${coopId}/producteurs`, data)
    success.value = true
    setTimeout(() => navigateTo('/producteurs'), 1500)
  } catch {
    error.value = 'Erreur lors de l\'enrôlement.'
  } finally {
    loading.value = false
  }
}
</script>
```

- [ ] **Step 4 : Commit**

```bash
git add frontend/
git commit -m "feat(frontend): producteurs liste + enrolement APDP consentement"
```

---

### Task 7 : Lots — formulaire + création online/offline

**Files:**
- Create: `frontend/components/lots/LotForm.vue`
- Create: `frontend/components/lots/LotCard.vue`
- Create: `frontend/pages/lots/index.vue`
- Create: `frontend/pages/lots/nouveau.vue`

**Interfaces:**
- Consumes: `useApi`, `useOfflineSync` (Task 5), `SyncQueue` (Task 4)
- Produces: formulaire de lot avec logique online (API) / offline (Dexie), badge sync, liste des lots

- [ ] **Step 1 : Créer `LotForm.vue`**

```vue
<template>
  <v-form ref="form" @submit.prevent="handleSubmit">
    <!-- Sélection producteur -->
    <v-autocomplete
      v-model="formData.producteur_id"
      :items="producteurItems"
      item-title="label"
      item-value="id"
      :label="$t('lots.producteur')"
      :rules="[v => !!v || $t('errors.required')]"
      variant="outlined"
      class="mb-2"
    />
    <v-text-field v-model.number="formData.poids_kg" :label="$t('lots.poids')" type="number" step="0.01"
      :rules="[v => v > 0 || $t('errors.positive')]" variant="outlined" suffix="kg" class="mb-2" />
    <v-text-field v-model.number="formData.humidite_pct" :label="$t('lots.humidite')" type="number" step="0.1"
      :rules="[v => v >= 0 && v <= 100 || $t('errors.humidity')]" variant="outlined" suffix="%" class="mb-2" />
    <v-text-field v-model.number="formData.prix_kg_fcfa" :label="$t('lots.prix')" type="number"
      :rules="[v => v > 0 || $t('errors.positive')]" variant="outlined" suffix="FCFA/kg" class="mb-2" />
    <v-text-field v-model="formData.date_pesee" :label="$t('lots.date_pesee')" type="date"
      :rules="[v => !!v || $t('errors.required')]" variant="outlined" class="mb-4" />

    <!-- Montant calculé (lecture seule) -->
    <v-alert v-if="montantEstime > 0" type="info" density="compact" class="mb-4">
      {{ $t('lots.montant_estime') }} : <strong>{{ formatFcfa(montantEstime) }}</strong>
    </v-alert>

    <v-btn type="submit" color="primary" block size="large" :loading="loading">
      <v-icon start>mdi-content-save</v-icon>
      {{ isOnline ? $t('lots.save_online') : $t('lots.save_offline') }}
    </v-btn>
  </v-form>
</template>

<script setup lang="ts">
const props = defineProps<{ producteurs: { id: string; prenom: string; nom: string }[]; loading?: boolean }>()
const emit  = defineEmits<{ submit: [data: typeof formData.value] }>()

const isOnline = ref(navigator.onLine)
onMounted(() => {
  window.addEventListener('online',  () => { isOnline.value = true })
  window.addEventListener('offline', () => { isOnline.value = false })
})

const formData = ref({
  producteur_id: '',
  poids_kg: 0,
  humidite_pct: 0,
  prix_kg_fcfa: 0,
  date_pesee: new Date().toISOString().split('T')[0],
})

const producteurItems = computed(() =>
  props.producteurs.map(p => ({ id: p.id, label: `${p.prenom} ${p.nom}` }))
)
const montantEstime = computed(() => Math.round(formData.value.poids_kg * formData.value.prix_kg_fcfa * 100) / 100)

function formatFcfa(n: number) {
  return new Intl.NumberFormat('fr-BJ', { style: 'currency', currency: 'XOF' }).format(n)
}

const form = ref()
async function handleSubmit() {
  const { valid } = await form.value.validate()
  if (!valid) return
  emit('submit', formData.value)
}
</script>
```

- [ ] **Step 2 : Créer `pages/lots/nouveau.vue`**

```vue
<template>
  <v-container>
    <v-btn :to="'/lots'" variant="text" prepend-icon="mdi-arrow-left" class="mb-3">{{ $t('common.back') }}</v-btn>
    <h2 class="text-h6 mb-2">{{ $t('lots.new') }}</h2>
    <SyncStatusChip :pending-count="pendingCount" :is-syncing="isSyncing" class="mb-3" @sync="syncNow" />

    <v-alert v-if="success" type="success" class="mb-3">{{ successMsg }}</v-alert>
    <v-alert v-if="error" type="error" class="mb-3">{{ error }}</v-alert>

    <LotForm :producteurs="producteurs" :loading="loading" @submit="handleSubmit" />
  </v-container>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { get, post } = useApi()
const authStore     = useAuthStore()
const { t }         = useI18n()
const { syncNow, queue, isSyncing, pendingCount } = useOfflineSync()

const coopId      = authStore.agent!.cooperative_id
const coopCode    = authStore.agent!.cooperative_code
const loading     = ref(false)
const success     = ref(false)
const successMsg  = ref('')
const error       = ref<string | null>(null)
const producteurs = ref<{ id: string; prenom: string; nom: string }[]>([])

onMounted(async () => {
  const res = await get<{ data: typeof producteurs.value }>(`/cooperatives/${coopId}/producteurs?limit=100`)
  producteurs.value = res.data
})

async function handleSubmit(data: Record<string, unknown>) {
  loading.value = true
  error.value   = null
  success.value = false
  try {
    if (navigator.onLine) {
      await post(`/cooperatives/${coopId}/lots`, data)
      successMsg.value = t('lots.created_online')
    } else {
      // Génerer code offline
      const { ulid } = await import('ulid')
      const code = `${coopCode}L${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`
      await queue.enqueue({ ...data as Record<string, unknown>, code, cooperative_id: coopId } as Parameters<typeof queue.enqueue>[0])
      successMsg.value = t('lots.saved_offline')
      await syncStore.refreshCount(queue)
    }
    success.value = true
    setTimeout(() => navigateTo('/lots'), 1500)
  } catch {
    error.value = t('errors.network')
  } finally {
    loading.value = false
  }
}

const syncStore = useSyncStore()
</script>
```

- [ ] **Step 3 : Créer `pages/lots/index.vue`** (dashboard)

```vue
<template>
  <v-container>
    <v-row justify="space-between" align="center" class="mb-2">
      <v-col><h2 class="text-h6">{{ $t('lots.title') }}</h2></v-col>
      <v-col cols="auto"><v-btn color="primary" :to="'/lots/nouveau'" prepend-icon="mdi-plus">{{ $t('lots.new') }}</v-btn></v-col>
    </v-row>

    <SyncStatusChip :pending-count="pendingCount" :is-syncing="isSyncing" class="mb-3" @sync="syncNow" />

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-3" />

    <LotCard v-for="lot in lots" :key="lot.id" :lot="lot" class="mb-2" />

    <div v-if="!loading && lots.length === 0" class="text-center text-grey mt-8">
      <v-icon size="64" color="grey-lighten-1">mdi-package-variant</v-icon>
      <p>{{ $t('lots.empty') }}</p>
    </div>
  </v-container>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { get }   = useApi()
const authStore = useAuthStore()
const { syncNow, isSyncing, pendingCount } = useOfflineSync()
const coopId    = authStore.agent!.cooperative_id
const loading   = ref(false)
const lots      = ref<Record<string, unknown>[]>([])

async function fetchLots() {
  loading.value = true
  try {
    const res = await get<{ data: Record<string, unknown>[] }>(`/cooperatives/${coopId}/lots?limit=50`)
    lots.value = res.data
  } finally { loading.value = false }
}

onMounted(fetchLots)
</script>
```

- [ ] **Step 4 : Créer `LotCard.vue`**

```vue
<template>
  <v-card rounded="lg" elevation="1">
    <v-card-item>
      <v-card-title class="text-body-1 font-weight-bold">{{ lot.code }}</v-card-title>
      <v-card-subtitle>{{ lot.poids_kg }} kg · {{ lot.humidite_pct }}% · {{ formatFcfa(lot.montant_fcfa) }}</v-card-subtitle>
      <template #append>
        <v-chip :color="statusColor" size="small">{{ lot.statut }}</v-chip>
      </template>
    </v-card-item>
    <v-card-actions v-if="lot.certificat">
      <v-btn :to="`/certificats/${lot.certificat.public_uuid}/verify`" variant="text" size="small" color="primary">
        {{ $t('lots.verify') }}
      </v-btn>
    </v-card-actions>
  </v-card>
</template>

<script setup lang="ts">
const props = defineProps<{ lot: Record<string, unknown> }>()
const statusColor = computed(() => props.lot.statut === 'certifie' ? 'success' : props.lot.statut === 'revoque' ? 'error' : 'warning')
function formatFcfa(n: unknown) {
  return new Intl.NumberFormat('fr-BJ', { style: 'currency', currency: 'XOF' }).format(Number(n))
}
</script>
```

- [ ] **Step 5 : Commit**

```bash
git add frontend/
git commit -m "feat(frontend): LotForm online/offline + dashboard lots + SyncStatusChip"
```

---

### Task 8 : Page de vérification publique (brandée TraçaCajou)

**Files:**
- Create: `frontend/pages/certificats/[uuid]/verify.vue`
- Create: `frontend/components/certificats/VerifyResult.vue`
- Create: `frontend/public/logo.svg` (logo TraçaCajou simple)

**Interfaces:**
- Consumes: `GET /api/v1/certificats/:uuid/verify` (public, pas d'auth)
- Produces: page publique brandée, accessible sans compte, i18n FR/EN

- [ ] **Step 1 : Créer un logo SVG simple**

`frontend/public/logo.svg` :
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="80" height="80">
  <circle cx="50" cy="50" r="48" fill="#2D5A27"/>
  <text x="50" y="62" font-size="48" text-anchor="middle" fill="white">🌿</text>
</svg>
```

- [ ] **Step 2 : Créer `VerifyResult.vue`**

```vue
<template>
  <div>
    <!-- Authentique -->
    <v-alert v-if="result.authentique && result.statut !== 'revoque'" type="success" prominent class="mb-4">
      <v-alert-title>✅ {{ $t('verify.authentic') }}</v-alert-title>
      {{ $t('verify.authentic_desc') }}
    </v-alert>

    <!-- Révoqué -->
    <v-alert v-else-if="result.statut === 'revoque'" type="warning" prominent class="mb-4">
      <v-alert-title>⚠️ {{ $t('verify.revoked') }}</v-alert-title>
      {{ $t('verify.revoked_desc') }}
    </v-alert>

    <!-- Non authentique -->
    <v-alert v-else type="error" prominent class="mb-4">
      <v-alert-title>❌ {{ $t('verify.not_authentic') }}</v-alert-title>
    </v-alert>

    <!-- Données du certificat (minimisées — APDP) -->
    <v-list lines="two" class="rounded-lg" elevation="1">
      <v-list-item :title="$t('verify.cooperative')"  :subtitle="result.cooperative" prepend-icon="mdi-domain" />
      <v-divider />
      <v-list-item :title="$t('verify.commune')"      :subtitle="result.commune"     prepend-icon="mdi-map-marker" />
      <v-divider />
      <v-list-item :title="$t('verify.poids')"        :subtitle="`${result.poids_kg} kg`"       prepend-icon="mdi-weight-kilogram" />
      <v-divider />
      <v-list-item :title="$t('verify.humidite')"     :subtitle="`${result.humidite_pct} %`"    prepend-icon="mdi-water-percent" />
      <v-divider />
      <v-list-item :title="$t('verify.date_pesee')"   :subtitle="result.date_pesee"  prepend-icon="mdi-calendar" />
    </v-list>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  result: {
    authentique: boolean
    statut: string
    cooperative: string
    commune: string
    poids_kg: number
    humidite_pct: number
    date_pesee: string
  }
}>()
</script>
```

- [ ] **Step 3 : Créer `pages/certificats/[uuid]/verify.vue`**

```vue
<template>
  <v-container max-width="600">
    <!-- En-tête branded -->
    <div class="text-center mb-6 mt-4">
      <img src="/logo.svg" alt="TraçaCajou" width="72" height="72" class="mb-2" />
      <h1 class="text-h5 text-primary font-weight-bold">TraçaCajou</h1>
      <p class="text-caption text-grey">{{ $t('verify.subtitle') }}</p>
    </div>

    <!-- Switcher de langue -->
    <div class="d-flex justify-center mb-4">
      <v-btn-toggle v-model="locale" mandatory density="compact" rounded="xl">
        <v-btn value="fr" size="small">Français</v-btn>
        <v-btn value="en" size="small">English</v-btn>
      </v-btn-toggle>
    </div>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />

    <VerifyResult v-if="result" :result="result" />

    <v-alert v-if="notFound" type="error" class="mt-4">
      {{ $t('verify.not_found') }}
    </v-alert>

    <p class="text-caption text-grey text-center mt-6">
      {{ $t('verify.powered_by') }} · filière anacarde Bénin
    </p>
  </v-container>
</template>

<script setup lang="ts">
// Page publique — pas de middleware auth
definePageMeta({ layout: 'empty' })

const route    = useRoute()
const config   = useRuntimeConfig()
const { locale } = useI18n()

const uuid     = route.params.uuid as string
const loading  = ref(true)
const notFound = ref(false)
const result   = ref<Record<string, unknown> | null>(null)

onMounted(async () => {
  try {
    const res = await $fetch<{ data: Record<string, unknown> }>(
      `${config.public.apiBase}/api/v1/certificats/${uuid}/verify`
    )
    result.value = res.data
  } catch (e: unknown) {
    if ((e as { statusCode?: number }).statusCode === 404) notFound.value = true
  } finally {
    loading.value = false
  }
})
</script>
```

- [ ] **Step 4 : Ajouter les clés i18n verify**

Dans `fr.json`, ajouter dans la section `verify` :
```json
"verify": {
  "subtitle": "Vérification de certificat d'origine cajou",
  "authentic": "Certificat authentique",
  "authentic_desc": "Ce lot a été certifié par la coopérative.",
  "revoked": "Certificat révoqué",
  "revoked_desc": "Ce certificat a été annulé. Un nouveau certificat peut avoir été émis.",
  "not_authentic": "Certificat invalide",
  "not_found": "Aucun certificat trouvé pour cet identifiant.",
  "cooperative": "Coopérative",
  "commune": "Commune",
  "poids": "Poids",
  "humidite": "Humidité",
  "date_pesee": "Date de pesée",
  "powered_by": "TraçaCajou"
}
```

Dans `en.json`, ajouter :
```json
"verify": {
  "subtitle": "Cashew origin certificate verification",
  "authentic": "Authentic certificate",
  "authentic_desc": "This lot was certified by the cooperative.",
  "revoked": "Revoked certificate",
  "revoked_desc": "This certificate has been cancelled.",
  "not_authentic": "Invalid certificate",
  "not_found": "No certificate found for this identifier.",
  "cooperative": "Cooperative",
  "commune": "Commune",
  "poids": "Weight",
  "humidite": "Humidity",
  "date_pesee": "Weighing date",
  "powered_by": "TraçaCajou"
}
```

- [ ] **Step 5 : Commit**

```bash
git add frontend/
git commit -m "feat(frontend): page verification publique brandee TraçaCajou i18n"
```

---

### Task 9 : Finalisation i18n (toutes les chaînes manquantes)

**Files:**
- Modify: `frontend/locales/fr.json` (compléter toutes les clés)
- Modify: `frontend/locales/en.json` (compléter toutes les clés)

**Interfaces:**
- Produces: zéro chaîne en dur dans les composants

- [ ] **Step 1 : Compléter `fr.json`**

```json
{
  "app": { "name": "TraçaCajou" },
  "common": { "back": "Retour", "save": "Enregistrer", "loading": "Chargement..." },
  "auth": { "login": "Connexion", "logout": "Déconnexion", "email": "Email", "password": "Mot de passe", "submit": "Se connecter" },
  "lots": {
    "title": "Lots", "new": "Nouveau lot", "sync": "Synchroniser maintenant",
    "pending": "lot(s) en attente", "synced": "Tout synchronisé",
    "producteur": "Producteur", "poids": "Poids (kg)", "humidite": "Humidité (%)",
    "prix": "Prix (FCFA/kg)", "date_pesee": "Date de pesée",
    "montant_estime": "Montant estimé",
    "save_online": "Enregistrer et certifier",
    "save_offline": "Sauvegarder (hors-ligne)",
    "created_online": "Lot créé et certificat généré.",
    "saved_offline": "Lot sauvegardé hors-ligne. Il sera synchronisé à la reconnexion.",
    "verify": "Vérifier le certificat",
    "empty": "Aucun lot enregistré."
  },
  "producteurs": {
    "title": "Producteurs", "new": "Enrôler un producteur",
    "prenom": "Prénom", "nom": "Nom", "sexe": "Sexe", "localite": "Localité",
    "consentement": "Le producteur consent à l'enregistrement de ses données (APDP)",
    "consentement_required": "Le consentement est obligatoire",
    "enrolled": "Producteur enrôlé avec succès.",
    "empty": "Aucun producteur enrôlé."
  },
  "offline": { "banner": "Mode hors-ligne — les lots seront synchronisés à la reconnexion." },
  "errors": { "required": "Champ obligatoire", "positive": "Valeur doit être > 0", "humidity": "Valeur entre 0 et 100", "network": "Erreur réseau. Veuillez réessayer." },
  "verify": {
    "subtitle": "Vérification de certificat d'origine cajou",
    "authentic": "Certificat authentique", "authentic_desc": "Ce lot a été certifié par la coopérative.",
    "revoked": "Certificat révoqué", "revoked_desc": "Ce certificat a été annulé.",
    "not_authentic": "Certificat invalide",
    "not_found": "Aucun certificat trouvé pour cet identifiant.",
    "cooperative": "Coopérative", "commune": "Commune", "poids": "Poids",
    "humidite": "Humidité", "date_pesee": "Date de pesée", "powered_by": "TraçaCajou"
  }
}
```

- [ ] **Step 2 : Compléter `en.json`** (même structure, en anglais)

- [ ] **Step 3 : Vérifier qu'aucune page n'affiche de clé i18n manquante**

```bash
cd frontend && npm run dev
# Naviguer sur /login, /lots, /producteurs, /certificats/test/verify
# Changer la langue FR ↔ EN — aucune clé manquante
```

- [ ] **Step 4 : Commit**

```bash
git add frontend/locales/
git commit -m "feat(frontend): i18n FR+EN complet — toutes les cles renseignees"
```

---

### Task 10 : Tests E2E Playwright + README frontend

**Files:**
- Create: `frontend/tests/e2e/login-lot-verify.spec.ts`
- Create: `frontend/playwright.config.ts`
- Create: `frontend/README.md`

**Interfaces:**
- Consumes: API back-end en cours d'exécution (`http://localhost:8000`), front-end (`http://localhost:3000`)
- Produces: test E2E Playwright : login → création lot → vérification publique QR

- [ ] **Step 1 : Configurer Playwright**

`frontend/playwright.config.ts` :
```ts
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  use: {
    baseURL: 'http://localhost:3000',
    trace:   'on-first-retry',
  },
  projects: [
    { name: 'Mobile Chrome', use: { ...devices['Pixel 5'] } },
  ],
  webServer: {
    command: 'npm run dev',
    url:     'http://localhost:3000',
    reuseExistingServer: true,
  },
})
```

- [ ] **Step 2 : Écrire le test E2E**

`frontend/tests/e2e/login-lot-verify.spec.ts` :
```ts
import { test, expect } from '@playwright/test'

test.describe('Parcours complet agent', () => {
  test('login → création lot → vérification publique', async ({ page }) => {
    // 1. Login
    await page.goto('/login')
    await page.fill('input[type="email"]', 'agent@agpk.bj')
    await page.fill('input[type="password"]', 'Demo@2026!')
    await page.getByRole('button', { name: /connexion|sign in/i }).click()
    await expect(page).toHaveURL('/lots')

    // 2. Créer un lot
    await page.getByRole('link', { name: /nouveau lot|new lot/i }).click()
    await expect(page).toHaveURL('/lots/nouveau')

    // Sélectionner un producteur
    await page.locator('[data-testid="producteur-select"]').click()
    await page.getByRole('option').first().click()

    await page.fill('input[name="poids_kg"]', '200')
    await page.fill('input[name="humidite_pct"]', '7')
    await page.fill('input[name="prix_kg_fcfa"]', '270')
    // La date est pré-remplie avec aujourd'hui

    await page.getByRole('button', { name: /enregistrer|save/i }).click()

    // 3. Attendre la redirection et récupérer l'UUID du certificat
    await expect(page).toHaveURL('/lots')
    const lotCard = page.locator('.v-card').first()
    await expect(lotCard).toBeVisible()
    const verifyLink = lotCard.getByRole('link', { name: /vérifier|verify/i })
    const href = await verifyLink.getAttribute('href')
    expect(href).toMatch(/\/certificats\/.+\/verify/)

    // 4. Page de vérification publique (pas d'auth)
    await page.context().clearCookies()
    await page.goto(href!)
    await expect(page.getByText(/authentique|authentic/i)).toBeVisible()
    await expect(page.locator('text=Coopérative AGPK')).toBeVisible()
  })
})
```

- [ ] **Step 3 : Installer les navigateurs Playwright**

```bash
cd frontend && npx playwright install chromium
```

- [ ] **Step 4 : Créer `frontend/README.md`**

```markdown
# TraçaCajou — Front-end Nuxt 3 PWA

PWA mobile-first pour les agents de coopérative : saisie offline de lots cajou, synchronisation différée, et vérification publique de certificats ECDSA P-384.

## Stack

- Nuxt 3 · Vue 3 · Vuetify 3 · Pinia · Dexie.js · @nuxtjs/i18n · @vite-pwa/nuxt

## Installation

```bash
npm install
cp .env.example .env.local
# Éditer .env.local : VITE_API_BASE_URL=http://localhost:8000
npm run dev
```

## Tests

```bash
# Unitaires
npx vitest run

# E2E (back-end doit tourner sur :8000)
npx playwright test
```

## Variables d'environnement

| Variable | Description | Défaut |
|---|---|---|
| `VITE_API_BASE_URL` | URL du back-end Laravel | `http://localhost:8000` |
```

- [ ] **Step 5 : Lancer les tests unitaires**

```bash
cd frontend && npx vitest run
```
Expected: `Tests: 6+ passed`.

- [ ] **Step 6 : Commit final**

```bash
git add frontend/
git commit -m "feat(frontend): tests E2E Playwright + README frontend"
```

---

## Auto-révision du plan

**Couverture spec → tâches :**

| Exigence spec | Tâche couverte |
| --- | --- |
| Auth agent cookie httpOnly | Task 2 (useApi CSRF + credentials) |
| Formulaire enrôlement producteur + consentement | Task 6 (ProducteurForm, case non pré-cochée) |
| Formulaire lot offline-capable | Task 7 (LotForm + Dexie) |
| SyncQueue Dexie.js | Task 4 (TDD) |
| Synchro hybride auto + bouton | Task 5 (useOfflineSync + SyncStatusChip) |
| Historique lots paginé | Task 7 (lots/index.vue) |
| Vérification publique QR | Task 8 (brandée, i18n) |
| PWA installable offline-first | Task 1 (@vite-pwa/nuxt, manifest) |
| Langues FR + EN | Task 9 (complet) |
| Mobile-first Android | Tasks 1-9 (Vuetify mobile, bottom nav) |
| Tests unitaires (composables) | Tasks 2, 4 |
| Test E2E | Task 10 (Playwright) |

**Vérification types/noms cohérents :**
- `SyncQueue.enqueue()` — Task 4 produit, Task 7 consomme ✓
- `useOfflineSync()` retourne `{ syncNow, queue, isSyncing, pendingCount }` — Task 5 produit, Tasks 7 consomment ✓
- `useApi().post()` — Task 2 produit, Tasks 6, 7 consomment ✓
- `useAuthStore().agent.cooperative_id` — Task 2 produit, Tasks 6, 7 consomment ✓
