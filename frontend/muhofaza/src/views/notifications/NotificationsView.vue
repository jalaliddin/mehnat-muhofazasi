<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Bildirishnomalar</div>
        <div class="text-body-2" style="color: #888">{{ notifStore.unreadCount }} o'qilmagan</div>
      </div>
      <v-btn
        v-if="notifStore.unreadCount > 0"
        variant="outlined"
        color="primary"
        rounded="lg"
        prepend-icon="mdi-check-all"
        :loading="markingAll"
        @click="markAll"
      >
        Hammasini o'qilgan deb belgilash
      </v-btn>
    </div>

    <!-- Filter tabs -->
    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4">
      <v-tabs v-model="tab" color="primary">
        <v-tab value="all">Hammasi</v-tab>
        <v-tab value="unread">
          O'qilmaganlar
          <v-badge v-if="notifStore.unreadCount > 0" :content="notifStore.unreadCount" color="error" inline class="ml-1" />
        </v-tab>
        <v-tab value="read">O'qilganlar</v-tab>
      </v-tabs>
    </v-card>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
      <div v-if="loading" class="pa-6 text-center">
        <v-progress-circular indeterminate color="primary" />
      </div>
      <div v-else-if="filteredNotifications.length === 0" class="pa-12 text-center">
        <v-icon icon="mdi-bell-off" size="64" color="grey-lighten-2" />
        <div class="text-h6 mt-3" style="color: #aaa">Bildirishnomalar yo'q</div>
      </div>
      <v-list v-else lines="two" class="pa-0">
        <template v-for="(notif, i) in filteredNotifications" :key="notif.id">
          <v-list-item
            :class="['pa-4', !notif.is_read ? notifBg(notif.type) : '']"
            style="border-radius: 0"
          >
            <template #prepend>
              <v-avatar :color="notifColor(notif.type)" size="40" class="mr-3">
                <v-icon :icon="notifIcon(notif.type)" color="white" size="20" />
              </v-avatar>
            </template>
            <v-list-item-title :style="{ fontWeight: notif.is_read ? '400' : '600', color: '#0D1B2A' }">
              {{ notif.title }}
            </v-list-item-title>
            <v-list-item-subtitle class="mt-1">{{ notif.message }}</v-list-item-subtitle>
            <template #append>
              <div class="d-flex flex-column align-end">
                <div class="text-caption" style="color: #aaa; white-space: nowrap">{{ formatDate(notif.created_at) }}</div>
                <v-btn
                  v-if="!notif.is_read"
                  size="x-small"
                  variant="text"
                  color="primary"
                  class="mt-1"
                  @click="markOne(notif.id)"
                >
                  O'qilgan
                </v-btn>
              </div>
            </template>
          </v-list-item>
          <v-divider v-if="i < filteredNotifications.length - 1" />
        </template>
      </v-list>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useNotificationStore } from '../../stores/notification'

const notifStore = useNotificationStore()
const loading = ref(false)
const markingAll = ref(false)
const tab = ref('all')

const filteredNotifications = computed(() => {
  const list = notifStore.notifications
  if (tab.value === 'unread') return list.filter(n => !n.is_read)
  if (tab.value === 'read') return list.filter(n => n.is_read)
  return list
})

function notifColor(type) {
  if (type === 'overdue') return '#8B0000'
  if (type === 'urgent' || type === 'week') return 'error'
  if (type === 'upcoming' || type === 'month') return 'warning'
  return 'primary'
}

function notifIcon(type) {
  if (type === 'overdue') return 'mdi-alert-octagon'
  if (type === 'urgent' || type === 'week') return 'mdi-alert-circle'
  if (type === 'upcoming' || type === 'month') return 'mdi-clock-alert'
  return 'mdi-bell'
}

function notifBg(type) {
  if (type === 'overdue') return 'bg-red-lighten-5'
  if (type === 'urgent' || type === 'week') return 'bg-red-lighten-5'
  if (type === 'upcoming' || type === 'month') return 'bg-orange-lighten-5'
  return 'bg-blue-lighten-5'
}

function formatDate(d) {
  if (!d) return '-'
  const date = new Date(d)
  return `${String(date.getDate()).padStart(2,'0')}.${String(date.getMonth()+1).padStart(2,'0')}.${date.getFullYear()} ${String(date.getHours()).padStart(2,'0')}:${String(date.getMinutes()).padStart(2,'0')}`
}

async function markOne(id) {
  await notifStore.markRead(id)
}

async function markAll() {
  markingAll.value = true
  await notifStore.markAllRead()
  markingAll.value = false
}

onMounted(async () => {
  loading.value = true
  await notifStore.fetchNotifications()
  loading.value = false
})
</script>
