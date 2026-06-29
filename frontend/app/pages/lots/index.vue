<template>
  <v-container>
    <v-row justify="space-between" align="center" class="mb-2">
      <v-col>
        <h2 class="text-h6">{{ $t('lots.title') }}</h2>
      </v-col>
      <v-col cols="auto">
        <v-btn color="primary" to="/lots/nouveau" prepend-icon="mdi-plus">
          {{ $t('lots.new') }}
        </v-btn>
      </v-col>
    </v-row>

    <SyncStatusChip
      :pending-count="pendingCount"
      :is-syncing="isSyncing"
      class="mb-3"
      @sync="syncNow"
    />

    <v-progress-linear v-if="isLoading" indeterminate color="primary" class="mb-3" />

    <LotCard
      v-for="lot in lots"
      :key="lot.id"
      :lot="lot"
      class="mb-2"
    />

    <div v-if="!isLoading && lots.length === 0" class="text-center text-grey mt-8">
      <v-icon size="64" color="grey-lighten-1">mdi-package-variant</v-icon>
      <p>{{ $t('lots.empty') }}</p>
    </div>
  </v-container>
</template>

<script setup lang="ts">
interface Certificat {
  public_uuid: string
}

interface Lot {
  id: string
  code: string
  poids_kg: number
  humidite_pct: number
  montant_fcfa: number
  date_pesee: string
  statut: 'enregistre' | 'certifie' | 'revoque'
  certificat?: Certificat | null
}

definePageMeta({ middleware: 'auth' })

const { get } = useApi()
const authStore = useAuthStore()
const { syncNow, isSyncing, pendingCount } = useOfflineSync()
const coopId = authStore.agent!.cooperative_id

const isLoading = ref(false)
const lots = ref<Lot[]>([])

async function fetchLots() {
  isLoading.value = true
  try {
    const res = await get<{ data: Lot[] }>(`/cooperatives/${coopId}/lots?limit=50`)
    lots.value = res.data
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchLots)
</script>
