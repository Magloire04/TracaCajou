<template>
  <v-banner
    v-if="!isOnline"
    color="warning"
    icon="mdi-wifi-off"
    lines="one"
    sticky
  >
    {{ $t('offline.banner') }}
  </v-banner>
</template>

<script setup lang="ts">
const isOnline = ref(true)

onMounted(() => {
  isOnline.value = navigator.onLine
  window.addEventListener('online', () => { isOnline.value = true })
  window.addEventListener('offline', () => { isOnline.value = false })
})
</script>
