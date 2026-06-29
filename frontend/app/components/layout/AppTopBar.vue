<template>
  <v-app-bar color="primary" elevation="2">
    <v-app-bar-title class="text-white font-weight-bold">🌿 TraçaCajou</v-app-bar-title>
    <v-spacer />
    <v-btn-toggle v-model="locale" mandatory density="compact" class="mr-2">
      <v-btn value="fr" size="small" variant="text" class="text-white">FR</v-btn>
      <v-btn value="en" size="small" variant="text" class="text-white">EN</v-btn>
    </v-btn-toggle>
    <v-btn icon="mdi-logout" variant="text" color="white" @click="logout" />
  </v-app-bar>
</template>

<script setup lang="ts">
const { locale } = useI18n()
const { logout } = useAuth()
</script>
