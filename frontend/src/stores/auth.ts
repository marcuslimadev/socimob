import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api.ts'

export interface User {
  id: number
  name: string
  email: string
  role: string
  tipo: string
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('token'))
  const user = ref<User | null>(JSON.parse(localStorage.getItem('user') || 'null'))
  const error = ref<string>('')

  const isAuthenticated = computed(() => !!token.value)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const isAdmin = computed(() => user.value?.role === 'admin')

  async function login(email: string, password: string) {
    try {
      const response = await api.post('/auth/login', { email, password })
      
      if (response.data.success) {
        token.value = response.data.token
        user.value = response.data.user
        
        localStorage.setItem('token', response.data.token)
        localStorage.setItem('user', JSON.stringify(response.data.user))
        error.value = ''
        
        return { success: true }
      }
      
      error.value = response.data.message
      return { success: false, message: response.data.message }
    } catch (err: any) {
      const msg = err.response?.data?.message || 'Erro ao fazer login'
      error.value = msg
      return { success: false, message: msg }
    }
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    error.value = ''
  }

  return {
    token,
    user,
    error,
    isAuthenticated,
    isSuperAdmin,
    isAdmin,
    login,
    logout
  }
})
