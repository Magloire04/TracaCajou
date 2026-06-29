<template>
  <v-form ref="form" @submit.prevent="handleSubmit">
    <v-text-field
      v-model="formData.prenom"
      :label="$t('producteurs.prenom')"
      :rules="[v => !!v || $t('errors.required')]"
      variant="outlined"
      class="mb-2"
    />
    <v-text-field
      v-model="formData.nom"
      :label="$t('producteurs.nom')"
      :rules="[v => !!v || $t('errors.required')]"
      variant="outlined"
      class="mb-2"
    />
    <v-select
      v-model="formData.sexe"
      :items="[{ title: 'Homme', value: 'M' }, { title: 'Femme', value: 'F' }]"
      :label="$t('producteurs.sexe')"
      variant="outlined"
      class="mb-2"
      clearable
    />
    <v-text-field
      v-model="formData.localite"
      :label="$t('producteurs.localite')"
      variant="outlined"
      class="mb-3"
    />

    <!-- APDP : consentement — case NON pré-cochée (false par défaut) -->
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
interface FormData {
  prenom: string
  nom: string
  sexe: 'M' | 'F' | ''
  localite: string
  consentement: boolean
}

defineProps<{ loading?: boolean }>()
const emit = defineEmits<{ submit: [data: FormData] }>()

const form = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)

const formData = ref<FormData>({
  prenom: '',
  nom: '',
  sexe: '',
  localite: '',
  consentement: false, // APDP — non pré-cochée
})

async function handleSubmit() {
  if (!form.value) return
  const { valid } = await form.value.validate()
  if (!valid) return
  emit('submit', { ...formData.value })
}
</script>
