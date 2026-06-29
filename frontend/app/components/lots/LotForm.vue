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
    <v-text-field
      v-model.number="formData.poids_kg"
      :label="$t('lots.poids')"
      type="number"
      step="0.01"
      :rules="[v => v > 0 || $t('errors.positive')]"
      variant="outlined"
      suffix="kg"
      class="mb-2"
    />
    <v-text-field
      v-model.number="formData.humidite_pct"
      :label="$t('lots.humidite')"
      type="number"
      step="0.1"
      :rules="[v => v >= 0 && v <= 100 || $t('errors.humidity')]"
      variant="outlined"
      suffix="%"
      class="mb-2"
    />
    <v-text-field
      v-model.number="formData.prix_kg_fcfa"
      :label="$t('lots.prix')"
      type="number"
      :rules="[v => v > 0 || $t('errors.positive')]"
      variant="outlined"
      suffix="FCFA/kg"
      class="mb-2"
    />
    <v-text-field
      v-model="formData.date_pesee"
      :label="$t('lots.date_pesee')"
      type="date"
      :rules="[v => !!v || $t('errors.required')]"
      variant="outlined"
      class="mb-4"
    />

    <!-- Montant estimé (lecture seule — non envoyé à l'API) -->
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
interface Producteur {
  id: string
  prenom: string
  nom: string
}

interface FormData {
  producteur_id: string
  poids_kg: number
  humidite_pct: number
  prix_kg_fcfa: number
  date_pesee: string
}

const props = defineProps<{ producteurs: Producteur[]; loading?: boolean }>()
const emit = defineEmits<{ submit: [data: FormData] }>()

const isOnline = ref(navigator.onLine)

onMounted(() => {
  window.addEventListener('online', () => { isOnline.value = true })
  window.addEventListener('offline', () => { isOnline.value = false })
})

const formData = ref<FormData>({
  producteur_id: '',
  poids_kg: 0,
  humidite_pct: 0,
  prix_kg_fcfa: 0,
  date_pesee: new Date().toISOString().split('T')[0],
})

const producteurItems = computed(() =>
  props.producteurs.map(p => ({ id: p.id, label: `${p.prenom} ${p.nom}` }))
)

// Montant estimé : affiché uniquement, jamais envoyé à l'API (règle métier)
const montantEstime = computed(() =>
  Math.round(formData.value.poids_kg * formData.value.prix_kg_fcfa * 100) / 100
)

function formatFcfa(n: number): string {
  return new Intl.NumberFormat('fr-BJ', { style: 'currency', currency: 'XOF' }).format(n)
}

const form = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)

async function handleSubmit() {
  if (!form.value) return
  const { valid } = await form.value.validate()
  if (!valid) return
  // montant_fcfa exclu intentionnellement — calculé côté serveur uniquement
  emit('submit', { ...formData.value })
}
</script>
