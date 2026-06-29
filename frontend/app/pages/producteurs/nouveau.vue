<template>
  <v-container>
    <v-btn to="/producteurs" variant="text" prepend-icon="mdi-arrow-left" class="mb-3">
      {{ $t('common.back') }}
    </v-btn>
    <h2 class="text-h6 mb-4">{{ $t('producteurs.new') }}</h2>

    <v-alert v-if="isSuccess" type="success" class="mb-3">
      {{ $t('producteurs.enrolled') }}
    </v-alert>
    <v-alert v-if="errorMessage" type="error" class="mb-3">
      {{ errorMessage }}
    </v-alert>

    <ProducteurForm :loading="isLoading" @submit="handleSubmit" />
  </v-container>
</template>

<script setup lang="ts">
interface FormData {
  prenom: string
  nom: string
  sexe: 'M' | 'F' | ''
  localite: string
  consentement: boolean
}

definePageMeta({ middleware: 'auth' })

const { post } = useApi()
const authStore = useAuthStore()
const coopId = authStore.agent!.cooperative_id

const isLoading = ref(false)
const isSuccess = ref(false)
const errorMessage = ref<string | null>(null)

async function handleSubmit(data: FormData) {
  isLoading.value = true
  errorMessage.value = null
  try {
    await post(`/cooperatives/${coopId}/producteurs`, {
      prenom: data.prenom,
      nom: data.nom,
      sexe: data.sexe || undefined,
      localite: data.localite || undefined,
      consentement: data.consentement, // boolean true — requis APDP
    })
    isSuccess.value = true
    setTimeout(() => navigateTo('/producteurs'), 1500)
  } catch {
    errorMessage.value = 'Erreur lors de l\'enrôlement.'
  } finally {
    isLoading.value = false
  }
}
</script>
