import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('token') || null)

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const organizationId = computed(() => user.value?.organization_id)

  async function login(username, password) {
    const response = await api.post('/auth/login', { username, password })
    token.value = response.data.token
    user.value = response.data.user
    localStorage.setItem('token', token.value)
    localStorage.setItem('user', JSON.stringify(user.value))
    return response.data
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
    }
  }

  async function fetchMe() {
    const response = await api.get('/auth/me')
    user.value = response.data
    localStorage.setItem('user', JSON.stringify(user.value))
    return response.data
  }

  return { user, token, isAuthenticated, isAdmin, organizationId, login, logout, fetchMe }
})
