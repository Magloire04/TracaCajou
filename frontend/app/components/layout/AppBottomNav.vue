<template>
  <v-bottom-navigation color="primary" grow>
    <v-btn :to="'/producteurs'" value="producteurs">
      <v-icon>mdi-account-group</v-icon>
      <span>{{ $t('producteurs.title') }}</span>
    </v-btn>
    <v-btn :to="'/lots'" value="lots">
      <v-badge :content="pendingCount" color="error" :model-value="pendingCount > 0">
        <v-icon>mdi-package-variant</v-icon>
      </v-badge>
      <span>{{ $t('lots.title') }}</span>
    </v-btn>
  </v-bottom-navigation>
</template>

<script setup lang="ts">
const syncStore = useSyncStore()
const pendingCount = computed(() => syncStore.pendingCount)
</script>
