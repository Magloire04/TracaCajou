<template>
  <v-card rounded="lg" elevation="1">
    <v-card-item>
      <v-card-title class="text-body-1 font-weight-bold">{{ lot.code }}</v-card-title>
      <v-card-subtitle>
        {{ lot.poids_kg }} kg &middot; {{ lot.humidite_pct }}% &middot; {{ formatFcfa(lot.montant_fcfa) }}
      </v-card-subtitle>
      <template #append>
        <v-chip :color="statutColor" size="small">{{ lot.statut }}</v-chip>
      </template>
    </v-card-item>
    <v-card-text class="pt-0 text-caption text-medium-emphasis">
      {{ lot.date_pesee }}
    </v-card-text>
    <v-card-actions v-if="lot.certificat">
      <v-btn
        :to="`/certificats/${(lot.certificat as { public_uuid: string }).public_uuid}/verify`"
        variant="text"
        size="small"
        color="primary"
      >
        {{ $t('lots.verify') }}
      </v-btn>
    </v-card-actions>
  </v-card>
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

const props = defineProps<{ lot: Lot }>()

const statutColor = computed(() => {
  if (props.lot.statut === 'certifie') return 'success'
  if (props.lot.statut === 'revoque') return 'error'
  return 'warning'
})

function formatFcfa(n: number): string {
  return new Intl.NumberFormat('fr-BJ', { style: 'currency', currency: 'XOF' }).format(n)
}
</script>
