<template>
  <v-app :theme="'light'" style="background: #F5F7FA">
    <router-view />
  </v-app>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from './stores/auth'
import { useNotificationStore } from './stores/notification'

const authStore = useAuthStore()
const notifStore = useNotificationStore()

onMounted(() => {
  if (authStore.isAuthenticated) {
    notifStore.startPolling()
  }
})
</script>
