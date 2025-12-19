// Composable para gerenciar usuários com RBAC
import { ref, computed } from 'vue'
import api from '@/services/api.ts'

export const ROLES = {
  SUPER_ADMIN: 'super_admin',
  ADMIN: 'admin',
  USER: 'user',
  CLIENT: 'client'
}

export const ROLE_LABELS = {
  super_admin: 'Super Administrador',
  admin: 'Administrador',
  user: 'Usuário',
  client: 'Cliente'
}

export const ROLE_PERMISSIONS = {
  super_admin: ['manage_tenants', 'manage_users', 'manage_subscriptions', 'view_all_data'],
  admin: ['manage_users_in_tenant', 'manage_properties', 'manage_leads', 'view_tenant_data'],
  user: ['manage_own_leads', 'view_properties', 'manage_own_data'],
  client: ['view_properties', 'view_own_data']
}

export function useUsers() {
  const users = ref([])
  const currentUser = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const tenantId = ref(localStorage.getItem('tenant_id') || null)

  // Listar usuários do tenant (admin) ou todos (super_admin)
  const fetchUsers = async (filters = {}) => {
    loading.value = true
    error.value = null

    try {
      let endpoint = '/api/users'
      
      if (filters.tenantId) {
        endpoint = `/api/tenants/${filters.tenantId}/users`
      }

      const params = new URLSearchParams()
      if (filters.role) params.append('role', filters.role)
      if (filters.is_active !== undefined) params.append('is_active', filters.is_active)
      if (filters.search) params.append('search', filters.search)

      const queryString = params.toString()
      const url = queryString ? `${endpoint}?${queryString}` : endpoint

      const response = await api.get(url)
      users.value = response.data.data || response.data
      return users.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao carregar usuários'
      return []
    } finally {
      loading.value = false
    }
  }

  // Criar novo usuário
  const createUser = async (userData) => {
    loading.value = true
    error.value = null

    try {
      // Adicionar tenant_id ao criar usuário em um tenant específico
      if (tenantId.value && !userData.tenant_id) {
        userData.tenant_id = tenantId.value
      }

      const response = await api.post('/api/users', userData)
      const newUser = response.data.data || response.data
      users.value.push(newUser)
      return newUser
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao criar usuário'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Atualizar usuário
  const updateUser = async (userId, userData) => {
    loading.value = true
    error.value = null

    try {
      const response = await api.put(`/api/users/${userId}`, userData)
      const updatedUser = response.data.data || response.data

      const index = users.value.findIndex(u => u.id === userId)
      if (index !== -1) {
        users.value[index] = updatedUser
      }

      return updatedUser
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao atualizar usuário'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Deletar usuário
  const deleteUser = async (userId) => {
    loading.value = true
    error.value = null

    try {
      await api.delete(`/api/users/${userId}`)
      users.value = users.value.filter(u => u.id !== userId)
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao deletar usuário'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Ativar/desativar usuário
  const toggleUserStatus = async (userId, isActive) => {
    return updateUser(userId, { is_active: isActive ? 1 : 0 })
  }

  // Mudar role do usuário
  const changeUserRole = async (userId, newRole) => {
    if (!Object.values(ROLES).includes(newRole)) {
      throw new Error('Role inválido')
    }
    return updateUser(userId, { role: newRole })
  }

  // Obter usuário por ID
  const getUserById = (userId) => {
    return users.value.find(u => u.id === userId)
  }

  // Contar usuários por role
  const countByRole = computed(() => {
    const counts = {}
    Object.values(ROLES).forEach(role => {
      counts[role] = users.value.filter(u => u.role === role).length
    })
    return counts
  })

  // Usuários ativos
  const activeUsers = computed(() => {
    return users.value.filter(u => u.is_active === 1 || u.is_active === true)
  })

  return {
    users,
    currentUser,
    loading,
    error,
    tenantId,
    fetchUsers,
    createUser,
    updateUser,
    deleteUser,
    toggleUserStatus,
    changeUserRole,
    getUserById,
    countByRole,
    activeUsers
  }
}
