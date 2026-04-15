<template>
  <v-app-bar flat style="background: white; border-bottom: 1px solid #e0e0e0;" height="64">
    <v-app-bar-title>
      <span class="text-h6 font-weight-bold" style="color: #0D1B2A">
        {{ pageTitle }}
      </span>
    </v-app-bar-title>

    <template #append>
      <v-btn icon @click="goToNotifications" class="mr-1">
        <v-badge :content="notifStore.unreadCount" color="error" :model-value="notifStore.unreadCount > 0">
          <v-icon color="#0D1B2A">mdi-bell-outline</v-icon>
        </v-badge>
      </v-btn>

      <v-avatar color="primary" size="36" class="mr-4" style="cursor:pointer">
        <span class="text-body-2 font-weight-bold text-white">
          {{ authStore.user?.name?.charAt(0)?.toUpperCase() }}
        </span>
      </v-avatar>
    </template>
  </v-app-bar>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useNotificationStore } from '../../stores/notification'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const notifStore = useNotificationStore()

const pageTitles = {
  '/dashboard': 'Dashboard',
  '/organizations': 'Tashkilotlar',
  '/employees': 'Xodimlar',
  '/departments': "Bo'limlar",
  '/positions': 'Lavozimlar',
  '/exams/periodic': 'Davriy Imtihonlar',
  '/exams/types': 'Imtihon Turlari',
  '/exams/retakes': 'Qayta Topshirish',
  '/orders': 'Buyruqlar',
  '/notifications': 'Bildirishnomalar',
  '/settings/users': 'Foydalanuvchilar',
}

const pageTitle = computed(() => pageTitles[route.path] || 'Mehnat Muhofazasi')

function goToNotifications() {
  router.push('/notifications')
}
</script>
