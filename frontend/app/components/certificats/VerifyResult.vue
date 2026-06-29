<template>
  <div>
    <!-- Authentique -->
    <v-alert v-if="result.authentique && result.statut !== 'revoque'" type="success" prominent class="mb-4">
      <v-alert-title>{{ $t('verify.authentic') }}</v-alert-title>
      {{ $t('verify.authentic_desc') }}
    </v-alert>

    <!-- Révoqué -->
    <v-alert v-else-if="result.statut === 'revoque'" type="warning" prominent class="mb-4">
      <v-alert-title>{{ $t('verify.revoked') }}</v-alert-title>
      {{ $t('verify.revoked_desc') }}
    </v-alert>

    <!-- Non authentique -->
    <v-alert v-else type="error" prominent class="mb-4">
      <v-alert-title>{{ $t('verify.not_authentic') }}</v-alert-title>
    </v-alert>

    <!-- Données du certificat (minimisées — APDP) -->
    <v-list lines="two" class="rounded-lg" elevation="1">
      <v-list-item
        :title="$t('verify.cooperative')"
        :subtitle="result.cooperative"
        prepend-icon="mdi-domain"
      />
      <v-divider />
      <v-list-item
        :title="$t('verify.commune')"
        :subtitle="result.commune"
        prepend-icon="mdi-map-marker"
      />
      <v-divider />
      <v-list-item
        :title="$t('verify.poids')"
        :subtitle="`${result.poids_kg} kg`"
        prepend-icon="mdi-weight-kilogram"
      />
      <v-divider />
      <v-list-item
        :title="$t('verify.humidite')"
        :subtitle="`${result.humidite_pct} %`"
        prepend-icon="mdi-water-percent"
      />
      <v-divider />
      <v-list-item
        :title="$t('verify.date_pesee')"
        :subtitle="result.date_pesee"
        prepend-icon="mdi-calendar"
      />
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
