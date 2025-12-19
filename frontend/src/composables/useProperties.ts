// Composable para gerenciar propriedades (imóveis)
import { ref, computed } from 'vue'
import api from '@/services/api.ts'

export function useProperties() {
  const properties = ref([])
  const loading = ref(false)
  const error = ref(null)
  const tenantId = ref(localStorage.getItem('tenant_id') || null)
  const filters = ref({
    search: '',
    tipo: '',
    cidade: '',
    minValue: null,
    maxValue: null
  })

  // Listar propriedades do tenant
  const fetchProperties = async (searchFilters = null) => {
    loading.value = true
    error.value = null

    try {
      const params = new URLSearchParams()

      const filterData = searchFilters || filters.value
      
      if (filterData.search) params.append('search', filterData.search)
      if (filterData.tipo) params.append('tipo', filterData.tipo)
      if (filterData.cidade) params.append('cidade', filterData.cidade)
      if (filterData.minValue) params.append('min_value', filterData.minValue)
      if (filterData.maxValue) params.append('max_value', filterData.maxValue)
      if (filterData.is_active !== undefined) params.append('is_active', filterData.is_active)

      const queryString = params.toString()
      const url = queryString ? `/api/properties?${queryString}` : '/api/properties'

      const response = await api.get(url)
      properties.value = response.data.data || response.data
      return properties.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao carregar propriedades'
      return []
    } finally {
      loading.value = false
    }
  }

  // Obter propriedade por ID
  const getProperty = async (propertyId) => {
    loading.value = true
    error.value = null

    try {
      const response = await api.get(`/api/properties/${propertyId}`)
      return response.data.data || response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao carregar propriedade'
      return null
    } finally {
      loading.value = false
    }
  }

  // Criar propriedade
  const createProperty = async (propertyData) => {
    loading.value = true
    error.value = null

    try {
      if (tenantId.value && !propertyData.tenant_id) {
        propertyData.tenant_id = tenantId.value
      }

      const response = await api.post('/api/properties', propertyData)
      const newProperty = response.data.data || response.data
      properties.value.push(newProperty)
      return newProperty
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao criar propriedade'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Atualizar propriedade
  const updateProperty = async (propertyId, propertyData) => {
    loading.value = true
    error.value = null

    try {
      const response = await api.put(`/api/properties/${propertyId}`, propertyData)
      const updatedProperty = response.data.data || response.data

      const index = properties.value.findIndex(p => p.id === propertyId)
      if (index !== -1) {
        properties.value[index] = updatedProperty
      }

      return updatedProperty
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao atualizar propriedade'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Deletar propriedade
  const deleteProperty = async (propertyId) => {
    loading.value = true
    error.value = null

    try {
      await api.delete(`/api/properties/${propertyId}`)
      properties.value = properties.value.filter(p => p.id !== propertyId)
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro ao deletar propriedade'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Filtrar propriedades localmente
  const filteredProperties = computed(() => {
    return properties.value.filter(prop => {
      if (filters.value.search) {
        const searchLower = filters.value.search.toLowerCase()
        const matchesSearch = 
          prop.titulo?.toLowerCase().includes(searchLower) ||
          prop.endereco?.toLowerCase().includes(searchLower) ||
          prop.cidade?.toLowerCase().includes(searchLower)
        
        if (!matchesSearch) return false
      }

      if (filters.value.tipo && prop.tipo !== filters.value.tipo) return false
      if (filters.value.cidade && prop.cidade !== filters.value.cidade) return false

      if (filters.value.minValue && prop.valor < filters.value.minValue) return false
      if (filters.value.maxValue && prop.valor > filters.value.maxValue) return false

      return true
    })
  })

  // Agrupar por tipo
  const groupedByType = computed(() => {
    const grouped = {}
    properties.value.forEach(prop => {
      if (!grouped[prop.tipo]) {
        grouped[prop.tipo] = []
      }
      grouped[prop.tipo].push(prop)
    })
    return grouped
  })

  // Agrupar por cidade
  const groupedByCity = computed(() => {
    const grouped = {}
    properties.value.forEach(prop => {
      if (!grouped[prop.cidade]) {
        grouped[prop.cidade] = []
      }
      grouped[prop.cidade].push(prop)
    })
    return grouped
  })

  // Estatísticas
  const statistics = computed(() => ({
    total: properties.value.length,
    byType: Object.fromEntries(
      Object.entries(groupedByType.value).map(([type, props]) => [type, props.length])
    ),
    byCity: Object.fromEntries(
      Object.entries(groupedByCity.value).map(([city, props]) => [city, props.length])
    ),
    averagePrice: properties.value.length > 0
      ? Math.round(properties.value.reduce((sum, p) => sum + (p.valor || 0), 0) / properties.value.length)
      : 0,
    minPrice: properties.value.length > 0
      ? Math.min(...properties.value.map(p => p.valor || 0))
      : 0,
    maxPrice: properties.value.length > 0
      ? Math.max(...properties.value.map(p => p.valor || 0))
      : 0
  }))

  return {
    properties,
    loading,
    error,
    tenantId,
    filters,
    fetchProperties,
    getProperty,
    createProperty,
    updateProperty,
    deleteProperty,
    filteredProperties,
    groupedByType,
    groupedByCity,
    statistics
  }
}
