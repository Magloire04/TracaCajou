<template>
  <v-container max-width="600">
    <!-- En-tête brandé TraçaCajou -->
    <div class="text-center mb-6 mt-4">
      <img src="/logo.svg" alt="TraçaCajou" width="72" height="72" class="mb-2" />
      <h1 class="text-h5 text-primary font-weight-bold">TraçaCajou</h1>
      <p class="text-caption text-grey">{{ $t('verify.subtitle') }}</p>
    </div>

    <!-- Sélecteur de langue -->
    <div class="d-flex justify-center mb-4">
      <v-btn-toggle v-model="locale" mandatory density="compact" rounded="xl">
        <v-btn value="fr" size="small">Français</v-btn>
        <v-btn value="en" size="small">English</v-btn>
      </v-btn-toggle>
    </div>

    <v-progress-linear v-if="isLoading" indeterminate color="primary" class="mb-4" />

    <VerifyResult v-if="result" :result="result" />

    <v-alert v-if="isNotFound" type="error" class="mt-4">
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

interface VerifyData {
  authentique: boolean
  statut: string
  cooperative: string
  commune: string
  poids_kg: number
  humidite_pct: number
  date_pesee: string
}

const route = useRoute()
const config = useRuntimeConfig()
const { locale } = useI18n()

const uuid = route.params.uuid as string
const isLoading = ref(true)
const isNotFound = ref(false)
const result = ref<VerifyData | null>(null)

onMounted(async () => {
  try {
    const res = await $fetch<{ data: VerifyData }>(
      `${config.public.apiBase}/api/v1/certificats/${uuid}/verify`
    )
    result.value = res.data
  } catch (e: unknown) {
    if ((e as { statusCode?: number }).statusCode === 404) {
      isNotFound.value = true
    }
  } finally {
    isLoading.value = false
  }
})
</script>
