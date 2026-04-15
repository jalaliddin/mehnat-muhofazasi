<template>
  <v-container fluid class="fill-height" style="background: linear-gradient(135deg, #0D1B2A 0%, #1565C0 100%)">
    <v-row justify="center" align="center">
      <v-col cols="12" sm="8" md="5" lg="4">
        <v-card rounded="xl" elevation="10" class="pa-8">
          <div class="text-center mb-6">
            <v-icon icon="mdi-shield-check" size="64" color="primary" />
            <h1 class="text-h5 font-weight-bold mt-3" style="color: #0D1B2A">Urganchtransgaz</h1>
            <p class="text-body-2" style="color: #666">Mehnat Muhofazasi va Sanoat Xavfsizligi</p>
          </div>

          <v-alert v-if="error" type="error" variant="tonal" class="mb-4" rounded="lg">{{ error }}</v-alert>

          <v-form @submit.prevent="handleLogin">
            <v-text-field
              v-model="username"
              label="Foydalanuvchi nomi"
              prepend-inner-icon="mdi-account"
              variant="outlined"
              rounded="lg"
              class="mb-3"
              :disabled="loading"
            />
            <v-text-field
              v-model="password"
              label="Parol"
              :type="showPass ? 'text' : 'password'"
              prepend-inner-icon="mdi-lock"
              :append-inner-icon="showPass ? 'mdi-eye-off' : 'mdi-eye'"
              @click:append-inner="showPass = !showPass"
              variant="outlined"
              rounded="lg"
              class="mb-6"
              :disabled="loading"
            />
            <v-btn
              type="submit"
              color="primary"
              size="large"
              block
              rounded="lg"
              :loading="loading"
            >
              Kirish
            </v-btn>
          </v-form>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notification'

const router = useRouter()
const authStore = useAuthStore()
const notifStore = useNotificationStore()

const username = ref('')
const password = ref('')
const showPass = ref(false)
const loading = ref(false)
const error = ref('')

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    await authStore.login(username.value, password.value)
    notifStore.startPolling()
    router.push('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || 'Xatolik yuz berdi'
  } finally {
    loading.value = false
  }
}
</script>
