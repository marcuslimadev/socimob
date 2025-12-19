// Composable para gerenciar dados isolados por tenant
import { ref, computed } from 'vue'
import api from '@/services/api.ts'

export function useTenant() {
  const currentTenant = ref(null)
  const tenants = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Obter tenant atual do localStorage
  const getTenantId = () => localStorage.getItem('tenant_id')

  // Listar todos os tenants (super admin only)
  const fetchTenants = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await api.get('/api/super-admin/tenants')
      tenants.value = response.data.data || response.data
      return tenants.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao carregar tenants'
      return []
    } finally {
      loading.value = false
    }
  }

  // Criar novo tenant (super admin only)
  const createTenant = async (tenantData) => {
    loading.value = true
    error.value = null

    try {
      const response = await api.post('/api/super-admin/tenants', tenantData)
      const newTenant = response.data.data || response.data
      tenants.value.push(newTenant)
      return newTenant
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao criar tenant'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Atualizar tenant
  const updateTenant = async (tenantId, tenantData) => {
    loading.value = true
    error.value = null

    try {
      const response = await api.put(`/api/super-admin/tenants/${tenantId}`, tenantData)
      const updatedTenant = response.data.data || response.data
      
      const index = tenants.value.findIndex(t => t.id === tenantId)
      if (index !== -1) {
        tenants.value[index] = updatedTenant
      }
      
      return updatedTenant
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao atualizar tenant'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Deletar tenant
  const deleteTenant = async (tenantId) => {
    loading.value = true
    error.value = null

    try {
      await api.delete(`/api/super-admin/tenants/${tenantId}`)
      tenants.value = tenants.value.filter(t => t.id !== tenantId)
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao deletar tenant'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Ativar/desativar tenant
  const toggleTenantStatus = async (tenantId, isActive) => {
    return updateTenant(tenantId, { is_active: isActive ? 1 : 0 })
  }

  return {
    currentTenant,
    tenants,
    loading,
    error,
    getTenantId,
    fetchTenants,
    createTenant,
    updateTenant,
    deleteTenant,
    toggleTenantStatus
  }
}
