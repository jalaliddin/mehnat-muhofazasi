import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../services/api'

export const useNotificationStore = defineStore('notification', () => {
  const unreadCount = ref(0)
  const notifications = ref([])

  async function fetchUnreadCount() {
    try {
      const response = await api.get('/notifications/unread-count')
      unreadCount.value = response.data.count
    } catch (e) {}
  }

  async function fetchNotifications() {
    const response = await api.get('/notifications')
    notifications.value = response.data.data || response.data
    return notifications.value
  }

  async function markRead(id) {
    await api.post(`/notifications/${id}/read`)
    await fetchUnreadCount()
    await fetchNotifications()
  }

  async function markAllRead() {
    await api.post('/notifications/read-all')
    await fetchUnreadCount()
    await fetchNotifications()
  }

  function startPolling() {
    fetchUnreadCount()
    setInterval(fetchUnreadCount, 30000)
  }

  return { unreadCount, notifications, fetchUnreadCount, fetchNotifications, markRead, markAllRead, startPolling }
})
