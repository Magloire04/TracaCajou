<template>
  <v-container>
    <v-btn to="/lots" variant="text" prepend-icon="mdi-arrow-left" class="mb-3">
      {{ $t('common.back') }}
    </v-btn>
    <h2 class="text-h6 mb-2">{{ $t('lots.new') }}</h2>

    <SyncStatusChip
      :pending-count="pendingCount"
      :is-syncing="isSyncing"
      class="mb-3"
      @sync="syncNow"
    />

    <v-alert v-if="isSuccess" type="success" class="mb-3">{{ successMessage }}</v-alert>
    <v-alert v-if="errorMessage" type="error" class="mb-3">{{ errorMessage }}</v-alert>

    <LotForm :producteurs="producteurs" :loading="isLoading" @submit="handleSubmit" />
  </v-container>
</template>

<script setup lang="ts">
interface Producteur {
  id: string
  prenom: string
  nom: string
}

interface LotFormData {
  producteur_id: string
  poids_kg: number
  humidite_pct: number
  prix_kg_fcfa: number
  date_pesee: string
}

definePageMeta({ middleware: 'auth' })

const { get, post } = useApi()
const authStore = useAuthStore()
const { t } = useI18n()
const { syncNow, queue, isSyncing, pendingCount } = useOfflineSync()
const syncStore = useSyncStore()

const coopId = authStore.agent!.cooperative_id
const coopCode = authStore.agent!.cooperative_code

const isLoading = ref(false)
const isSuccess = ref(false)
const successMessage = ref('')
const errorMessage = ref<string | null>(null)
const producteurs = ref<Producteur[]>([])

onMounted(async () => {
  const res = await get<{ data: Producteur[] }>(`/cooperatives/${coopId}/producteurs?limit=100`)
  producteurs.value = res.data
})

async function handleSubmit(data: LotFormData) {
  isLoading.value = true
  errorMessage.value = null
  isSuccess.value = false

  try {
    if (navigator.onLine) {
      // En ligne : POST sans montant_fcfa (calculé côté serveur)
      await post(`/cooperatives/${coopId}/lots`, {
        producteur_id: data.producteur_id,
        poids_kg: data.poids_kg,
        humidite_pct: data.humidite_pct,
        prix_kg_fcfa: data.prix_kg_fcfa,
        date_pesee: data.date_pesee,
      })
      successMessage.value = t('lots.created_online')
    } else {
      // Hors-ligne : génération du code et mise en file d'attente Dexie
      const code = `${coopCode}L${new Date().toISOString().replace(/\D/g, '').slice(0, 14)}`
      await queue.enqueue({
        code,
        cooperative_id: coopId,
        producteur_id: data.producteur_id,
        poids_kg: data.poids_kg,
        humidite_pct: data.humidite_pct,
        prix_kg_fcfa: data.prix_kg_fcfa,
        date_pesee: data.date_pesee,
      })
      await syncStore.refreshCount(queue)
      successMessage.value = t('lots.saved_offline')
    }

    isSuccess.value = true
    setTimeout(() => navigateTo('/lots'), 1500)
  } catch {
    errorMessage.value = t('errors.network')
  } finally {
    isLoading.value = false
  }
}
</script>
