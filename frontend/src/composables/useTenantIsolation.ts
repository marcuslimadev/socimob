// Composable para gerenciar dados isolados por tenant com integração ao backend
import { ref, computed } from 'vue'
import { useAuth } from './useAuth'
import api from '@/services/api.ts'

export function useTenantIsolation() {
  const auth = useAuth()
  const tenantId = computed(() => auth.tenantId || localStorage.getItem('tenant_id'))
  const currentTenant = ref(null)

  // Construir query params com tenant_id automaticamente
  const buildTenantParams = (additionalParams = {}) => {
    const params = new URLSearchParams()
    
    // Adicionar tenant_id apenas se não for super_admin
    if (!auth.isSuperAdmin && tenantId.value) {
      params.append('tenant_id', tenantId.value)
    }

    // Adicionar outros parâmetros
    Object.entries(additionalParams).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        params.append(key, value)
      }
    })

    return params.toString()
  }

  // GET com isolamento automático de tenant
  const getTenantScoped = async (endpoint, params = {}) => {
    try {
      const queryString = buildTenantParams(params)
      const url = queryString ? `${endpoint}?${queryString}` : endpoint

      const response = await api.get(url)
      return response.data
    } catch (err) {
      console.error(`Erro ao buscar ${endpoint}:`, err)
      throw err
    }
  }

  // POST com tenant_id automático
  const postTenantScoped = async (endpoint, data) => {
    try {
      const payload = { ...data }
      
      // Adicionar tenant_id se não for super_admin e dados não têm tenant_id
      if (!auth.isSuperAdmin && !payload.tenant_id && tenantId.value) {
        payload.tenant_id = tenantId.value
      }

      const response = await api.post(endpoint, payload)
      return response.data
    } catch (err) {
      console.error(`Erro ao enviar para ${endpoint}:`, err)
      throw err
    }
  }

  // PUT com tenant_id automático
  const putTenantScoped = async (endpoint, data) => {
    try {
      const payload = { ...data }
      
      if (!auth.isSuperAdmin && !payload.tenant_id && tenantId.value) {
        payload.tenant_id = tenantId.value
      }

      const response = await api.put(endpoint, payload)
      return response.data
    } catch (err) {
      console.error(`Erro ao atualizar ${endpoint}:`, err)
      throw err
    }
  }

  // Obter tenant atual
  const fetchCurrentTenant = async () => {
    if (!tenantId.value) return null

    try {
      const response = await api.get(`/api/tenants/${tenantId.value}`)
      currentTenant.value = response.data.data || response.data
      return currentTenant.value
    } catch (err) {
      console.error('Erro ao buscar tenant atual:', err)
      return null
    }
  }

  // Validar acesso ao tenant
  const canAccessTenant = (targetTenantId) => {
    if (auth.isSuperAdmin) return true
    return tenantId.value === targetTenantId
  }

  return {
    tenantId,
    currentTenant,
    buildTenantParams,
    getTenantScoped,
    postTenantScoped,
    putTenantScoped,
    fetchCurrentTenant,
    canAccessTenant
  }
}
