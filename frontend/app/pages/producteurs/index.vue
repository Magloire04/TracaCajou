<template>
  <v-container>
    <v-row justify="space-between" align="center" class="mb-3">
      <v-col>
        <h2 class="text-h6">{{ $t('producteurs.title') }}</h2>
      </v-col>
      <v-col cols="auto">
        <v-btn color="primary" to="/producteurs/nouveau" prepend-icon="mdi-plus">
          {{ $t('producteurs.new') }}
        </v-btn>
      </v-col>
    </v-row>

    <v-progress-linear v-if="isLoading" indeterminate color="primary" class="mb-3" />

    <ProducteurCard
      v-for="producteur in producteurs"
      :key="producteur.id"
      :producteur="producteur"
      class="mb-2"
    />

    <div v-if="!isLoading && producteurs.length === 0" class="text-center text-grey mt-8">
      <v-icon size="64" color="grey-lighten-1">mdi-account-group</v-icon>
      <p>{{ $t('producteurs.empty') }}</p>
    </div>

    <v-pagination
      v-if="meta.total > meta.limit"
      v-model="page"
      :length="Math.ceil(meta.total / meta.limit)"
      class="mt-4"
    />
  </v-container>
</template>

<script setup lang="ts">
interface Producteur {
  id: string
  code: string
  prenom: string
  nom: string
  sexe?: 'M' | 'F' | null
}

interface Meta {
  page: number
  limit: number
  total: number
}

definePageMeta({ middleware: 'auth' })

const { get } = useApi()
const authStore = useAuthStore()
const coopId = authStore.agent!.cooperative_id

const LIMIT = 20
const page = ref(1)
const isLoading = ref(false)
const producteurs = ref<Producteur[]>([])
const meta = ref<Meta>({ page: 1, limit: LIMIT, total: 0 })

async function fetchProducteurs() {
  isLoading.value = true
  try {
    const res = await get<{ data: Producteur[]; meta: Meta }>(
      `/cooperatives/${coopId}/producteurs?page=${page.value}&limit=${LIMIT}`
    )
    producteurs.value = res.data
    meta.value = res.meta
  } finally {
    isLoading.value = false
  }
}

watch(page, fetchProducteurs)
onMounted(fetchProducteurs)
</script>
