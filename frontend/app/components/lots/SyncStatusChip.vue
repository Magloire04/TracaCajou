<template>
  <div class="d-flex align-center ga-2 pa-2">
    <v-chip v-if="pendingCount > 0" color="warning" size="small" prepend-icon="mdi-cloud-upload-outline">
      {{ pendingCount }} {{ $t('lots.pending') }}
    </v-chip>
    <v-chip v-else color="success" size="small" prepend-icon="mdi-check-circle">
      {{ $t('lots.synced') }}
    </v-chip>
    <v-btn
      v-if="pendingCount > 0"
      color="primary"
      size="small"
      variant="tonal"
      :loading="isSyncing"
      prepend-icon="mdi-sync"
      @click="$emit('sync')"
    >
      {{ $t('lots.sync') }}
    </v-btn>
  </div>
</template>

<script setup lang="ts">
defineProps<{ pendingCount: number; isSyncing: boolean }>()
defineEmits<{ sync: [] }>()
</script>
