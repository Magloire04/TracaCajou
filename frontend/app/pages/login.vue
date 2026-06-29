<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="4">
        <v-card elevation="4" rounded="lg" class="pa-4">
          <v-card-title class="text-center text-primary text-h5 mb-2">
            TraçaCajou
          </v-card-title>
          <v-card-subtitle class="text-center mb-4">{{ $t('auth.login') }}</v-card-subtitle>

          <v-form ref="form" @submit.prevent="handleLogin">
            <v-text-field
              v-model="email"
              :label="$t('auth.email')"
              type="email"
              :rules="[v => !!v || $t('errors.required')]"
              prepend-inner-icon="mdi-email"
              variant="outlined"
              class="mb-2"
            />
            <v-text-field
              v-model="password"
              :label="$t('auth.password')"
              type="password"
              :rules="[v => !!v || $t('errors.required')]"
              prepend-inner-icon="mdi-lock"
              variant="outlined"
              class="mb-4"
            />
            <v-alert v-if="error" type="error" class="mb-3" density="compact">{{ error }}</v-alert>
            <v-btn
              type="submit"
              color="primary"
              block
              size="large"
              :loading="isLoading"
            >
              {{ $t('auth.submit') }}
            </v-btn>
          </v-form>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth', layout: 'empty' })

const { login } = useAuth()
const email = ref('')
const password = ref('')
const isLoading = ref(false)
const error = ref<string | null>(null)

async function handleLogin() {
  isLoading.value = true
  error.value = null
  try {
    await login(email.value, password.value)
    await navigateTo('/lots')
  } catch {
    error.value = 'Email ou mot de passe incorrect.'
  } finally {
    isLoading.value = false
  }
}
</script>
