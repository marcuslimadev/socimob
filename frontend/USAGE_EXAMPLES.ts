// Exemplos de uso dos novos composables e componentes

// ============================================
// 1. USAR RoleGuard PARA PROTEGER CONTEÚDO
// ============================================

// Em templates:
/*
<role-guard roles="admin">
  <button @click="importProperties">Importar Imóveis</button>
</role-guard>

<role-guard :roles="['admin', 'super_admin']">
  <div>Conteúdo para admin e super admin</div>
</role-guard>

<role-guard permission="import_properties">
  <div>Conteúdo com permissão específica</div>
</role-guard>
*/

// ============================================
// 2. USAR useTenantIsolation PARA DADOS ISOLADOS
// ============================================

import { useTenantIsolation } from '@/composables/useTenantIsolation'

export function exemplo1() {
  const { getTenantScoped, postTenantScoped, tenantId, canAccessTenant } = useTenantIsolation()

  // GET com tenant_id automático
  const fetchUserProperties = async () => {
    try {
      const data = await getTenantScoped('/api/properties', { 
        type: 'casa',
        min_price: 100000 
      })
      // Retorna apenas propriedades do tenant atual
      return data
    } catch (err) {
      console.error('Erro:', err)
    }
  }

  // POST com tenant_id automático
  const createPropertyInTenant = async () => {
    const propertyData = {
      titulo: 'Casa Moderna',
      endereco: 'Rua A 123',
      cidade: 'São Paulo',
      tipo: 'casa',
      valor: 500000
      // tenant_id é adicionado automaticamente!
    }

    try {
      const result = await postTenantScoped('/api/properties', propertyData)
      return result
    } catch (err) {
      console.error('Erro:', err)
    }
  }

  // Verificar acesso
  const canAccess = canAccessTenant(someTenantId)
}

// ============================================
// 3. USAR useSecurity PARA VALIDAÇÃO DE ACESSO
// ============================================

import { useSecurity } from '@/composables/useSecurity'

export function exemplo2() {
  const { 
    hasPermission, 
    canEditProperty, 
    canImportProperties,
    validateResourceAccess 
  } = useSecurity()

  // Verificar permissão
  if (hasPermission('import_properties')) {
    console.log('Usuário pode importar')
  }

  // Verificar acesso a recurso
  const property = { id: 1, tenant_id: 5, titulo: 'Casa' }
  if (canEditProperty(property)) {
    console.log('Pode editar esta propriedade')
  }

  // Validação completa
  const access = validateResourceAccess(property, 'manage_properties')
  if (!access.allowed) {
    console.error('Acesso negado:', access.reason)
  }
}

// ============================================
// 4. USAR usePropertyImport PARA IMPORTAÇÃO
// ============================================

import { usePropertyImport } from '@/composables/usePropertyImport'

export function exemplo3() {
  const { 
    importProperties, 
    downloadTemplate,
    importProgress,
    importedCount,
    failedCount
  } = usePropertyImport()

  const handleFileImport = async (file) => {
    const success = await importProperties(file)
    if (success) {
      console.log(`Importados: ${importedCount.value}, Falhados: ${failedCount.value}`)
    }
  }

  // Download template
  const downloadCSV = () => {
    downloadTemplate()
  }
}

// ============================================
// 5. USAR useUsers COM ISOLAMENTO DE TENANT
// ============================================

import { useUsers, ROLES, ROLE_LABELS } from '@/composables/useUsers'

export function exemplo4() {
  const { 
    fetchUsers, 
    createUser, 
    changeUserRole,
    countByRole
  } = useUsers()

  // Listar usuários (automático por tenant)
  const loadUsers = async () => {
    const users = await fetchUsers({ role: 'admin' })
    return users
  }

  // Criar usuário no tenant (tenant_id automático)
  const addUser = async () => {
    const newUser = await createUser({
      name: 'João Silva',
      email: 'joao@example.com',
      password: 'senha123',
      role: ROLES.USER
    })
    return newUser
  }

  // Mudar role
  const promoteToAdmin = async (userId) => {
    await changeUserRole(userId, ROLES.ADMIN)
  }

  // Stats
  console.log(countByRole.value) // { super_admin: 1, admin: 2, user: 5, client: 0 }
}

// ============================================
// 6. USAR useTenant PARA GERENCIAR TENANTS
// ============================================

import { useTenant } from '@/composables/useTenant'

export function exemplo5() {
  const { 
    fetchTenants, 
    createTenant,
    updateTenant,
    toggleTenantStatus
  } = useTenant()

  // Super admin lista todos os tenants
  const loadAllTenants = async () => {
    const tenants = await fetchTenants()
    return tenants
  }

  // Criar novo tenant
  const addTenant = async () => {
    const newTenant = await createTenant({
      name: 'Empresa XYZ',
      domain: 'xyzimobiliaria.com.br',
      slug: 'xyz',
      contact_email: 'admin@xyz.com'
    })
    return newTenant
  }

  // Desativar tenant
  const deactivate = async (tenantId) => {
    await toggleTenantStatus(tenantId, false)
  }
}

// ============================================
// 7. USAR useProperties COM FILTROS E STATS
// ============================================

import { useProperties } from '@/composables/useProperties'

export function exemplo6() {
  const { 
    fetchProperties,
    statistics,
    groupedByCity,
    filteredProperties,
    createProperty
  } = useProperties()

  // Carregar com filtros
  const loadProperties = async () => {
    await fetchProperties({
      city: 'São Paulo',
      type: 'casa',
      minValue: 300000,
      maxValue: 800000
    })
  }

  // Acessar estatísticas
  const showStats = () => {
    console.log(statistics.value)
    // {
    //   total: 42,
    //   byType: { casa: 20, apartamento: 15, terreno: 7 },
    //   byCity: { 'São Paulo': 25, 'Rio de Janeiro': 17 },
    //   averagePrice: 450000,
    //   minPrice: 150000,
    //   maxPrice: 1500000
    // }
  }

  // Agrupar por cidade
  const citiesData = groupedByCity.value
}

// ============================================
// 8. USAR useAuth PARA AUTENTICAÇÃO
// ============================================

import { useAuth } from '@/composables/useAuth'

export function exemplo7() {
  const {
    login,
    logout,
    checkAuth,
    isAuthenticated,
    isSuperAdmin,
    isAdmin,
    user,
    tenantId
  } = useAuth()

  // Login
  const doLogin = async () => {
    const success = await login('admin@example.com', 'senha123')
    if (success) {
      console.log('Usuário:', user.value.name)
      console.log('Tenant:', tenantId.value)
    }
  }

  // Verificar autenticação
  const validateAuth = async () => {
    const isValid = await checkAuth()
    if (!isValid) {
      console.log('Token inválido')
    }
  }

  // Logout
  const doLogout = async () => {
    await logout() // Redireciona para /login
  }

  // Getters
  if (isAuthenticated.value && isSuperAdmin.value) {
    console.log('Super admin autenticado')
  }
}

// ============================================
// 9. USAR EM COMPONENTES VUE
// ============================================

// Example.vue
/*
<template>
  <div>
    <!-- Proteger por role -->
    <role-guard roles="admin">
      <h2>Painel do Admin</h2>
      <property-import-enhanced />
    </role-guard>

    <!-- Listar propriedades com filtros -->
    <div v-if="!loading">
      <div v-for="prop in properties" :key="prop.id">
        <h3>{{ prop.titulo }}</h3>
        <p>{{ prop.endereco }}, {{ prop.cidade }}</p>
        <p>R$ {{ prop.valor.toLocaleString() }}</p>
        
        <!-- Só mostrar botão de editar se puder -->
        <button v-if="canEditProperty(prop)" @click="editProperty(prop.id)">
          Editar
        </button>
      </div>
    </div>

    <!-- Importação de imóveis -->
    <role-guard permission="import_properties">
      <property-import-enhanced />
    </role-guard>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useProperties } from '@/composables/useProperties'
import { useSecurity } from '@/composables/useSecurity'
import RoleGuard from '@/components/RoleGuard.vue'
import PropertyImportEnhanced from '@/components/PropertyImportEnhanced.vue'

const { properties, loading, fetchProperties } = useProperties()
const { canEditProperty } = useSecurity()

const handleImport = () => {
  fetchProperties()
}

onMounted(() => {
  fetchProperties()
})
</script>
*/

export default {}
