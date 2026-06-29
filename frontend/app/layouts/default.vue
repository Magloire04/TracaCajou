<template>
  <AppTopBar />
  <OfflineBanner />
  <v-main class="bg-background" style="padding-bottom: 56px;">
    <slot />
  </v-main>
  <AppBottomNav />
</template>
