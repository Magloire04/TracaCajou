<template>
  <v-card variant="outlined" rounded="lg">
    <v-list-item
      :prepend-icon="sexeIcon"
      :title="`${producteur.prenom} ${producteur.nom}`"
      :subtitle="producteur.code"
    >
      <template #append>
        <v-chip size="small" color="secondary" variant="tonal">
          {{ producteur.code }}
        </v-chip>
      </template>
    </v-list-item>
  </v-card>
</template>

<script setup lang="ts">
interface Producteur {
  id: string
  code: string
  prenom: string
  nom: string
  sexe?: 'M' | 'F' | null
}

const props = defineProps<{ producteur: Producteur }>()

const sexeIcon = computed(() =>
  props.producteur.sexe === 'F' ? 'mdi-account-female' : 'mdi-account'
)
</script>
