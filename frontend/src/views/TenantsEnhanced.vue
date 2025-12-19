<template>
  <role-guard roles="super_admin">
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      <navbar />

      <!-- Header -->
      <header class="bg-gradient-to-r from-indigo-600 to-purple-600 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div class="flex justify-between items-center">
            <div>
              <h1 class="text-4xl font-bold text-white">🏢 Gerenciar Empresas</h1>
              <p class="text-indigo-100 mt-2">Imobiliárias e tenants do sistema</p>
            </div>
            <button
              @click="showModal = true"
              class="px-6 py-3 bg-white hover:bg-gray-100 text-indigo-600 rounded-lg font-bold transition shadow-lg"
            >
              ➕ Nova Empresa
            </button>
          </div>
        </div>
      </header>

      <!-- Main Content -->
      <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Alerts -->
        <div v-if="success" class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-lg">
          ✅ {{ success }}
        </div>
        <div v-if="error" class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg">
          ❌ {{ error }}
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar por nome ou email..."
              class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
            <select
              v-model="statusFilter"
              class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">Todos</option>
              <option :value="1">Ativos</option>
              <option :value="0">Inativos</option>
            </select>
            <div class="text-sm text-gray-600 flex items-center gap-2">
              📊 {{ filteredTenants.length }} de {{ tenants.length }} empresas
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
          <p class="mt-4 text-gray-600">Carregando empresas...</p>
        </div>

        <!-- Tenants Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="tenant in filteredTenants"
            :key="tenant.id"
            class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition border-t-4"
            :class="tenant.is_active ? 'border-t-green-500' : 'border-t-red-500'"
          >
            <!-- Header -->
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="text-lg font-bold text-gray-900">{{ tenant.name }}</h3>
                <p class="text-sm text-gray-500">{{ tenant.domain }}</p>
              </div>
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-bold',
                  tenant.is_active
                    ? 'bg-green-100 text-green-800'
                    : 'bg-red-100 text-red-800'
                ]"
              >
                {{ tenant.is_active ? '🟢 Ativo' : '🔴 Inativo' }}
              </span>
            </div>

            <!-- Info -->
            <div class="text-sm text-gray-600 space-y-2 mb-4 pb-4 border-b">
              <p><strong>📧 Email:</strong> {{ tenant.contact_email }}</p>
              <p><strong>🔗 Slug:</strong> {{ tenant.slug }}</p>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
              <button
                @click="editTenant(tenant)"
                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-3 rounded-lg transition text-sm"
              >
                ✏️ Editar
              </button>
              <button
                @click="toggleTenantStatus(tenant)"
                :class="[
                  'flex-1 font-bold py-2 px-3 rounded-lg transition text-sm',
                  tenant.is_active
                    ? 'bg-orange-600 hover:bg-orange-700 text-white'
                    : 'bg-green-600 hover:bg-green-700 text-white'
                ]"
              >
                {{ tenant.is_active ? '⏸️ Desativar' : '▶️ Ativar' }}
              </button>
              <button
                @click="deleteTenant(tenant.id)"
                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-3 rounded-lg transition text-sm"
              >
                🗑️ Deletar
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && filteredTenants.length === 0" class="text-center py-12 bg-white rounded-xl shadow-lg">
          <div class="text-6xl mb-4">🏢</div>
          <p class="text-xl text-gray-600 mb-4">Nenhuma empresa encontrada</p>
          <button
            @click="showModal = true"
            class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold"
          >
            Criar Primeira Empresa
          </button>
        </div>
      </main>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8">
          <h2 class="text-2xl font-bold text-gray-900 mb-6">
            {{ editingTenant ? '✏️ Editar Empresa' : '➕ Nova Empresa' }}
          </h2>

          <form @submit.prevent="saveTenant" class="space-y-4">
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-2">Nome da Empresa</label>
              <input
                v-model="formData.name"
                type="text"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <div>
              <label class="block text-sm font-bold text-gray-700 mb-2">Domain</label>
              <input
                v-model="formData.domain"
                type="text"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <div>
              <label class="block text-sm font-bold text-gray-700 mb-2">Slug</label>
              <input
                v-model="formData.slug"
                type="text"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <div>
              <label class="block text-sm font-bold text-gray-700 mb-2">Email de Contato</label>
              <input
                v-model="formData.contact_email"
                type="email"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <div class="flex items-center gap-2">
              <input
                v-model="formData.is_active"
                type="checkbox"
                id="is_active"
                class="w-4 h-4 text-indigo-600"
              />
              <label for="is_active" class="text-sm font-bold text-gray-700">Ativo</label>
            </div>

            <div class="flex gap-3 pt-4">
              <button
                type="button"
                @click="showModal = false"
                class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-bold"
              >
                Cancelar
              </button>
              <button
                type="submit"
                :disabled="loading"
                class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 text-white rounded-lg font-bold transition"
              >
                {{ loading ? '⏳...' : '✅ Salvar' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </role-guard>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Navbar from '@/components/Navbar.vue'
import RoleGuard from '@/components/RoleGuard.vue'
import { useTenant } from '@/composables/useTenant'

const showModal = ref(false)
const editingTenant = ref(null)
const searchQuery = ref('')
const statusFilter = ref('')

const {
  tenants,
  loading,
  error: tenantError,
  fetchTenants,
  createTenant,
  updateTenant,
  deleteTenant: deleteTenantAPI,
  toggleTenantStatus: toggleAPI
} = useTenant()

const error = ref('')
const success = ref('')

const formData = ref({
  name: '',
  domain: '',
  slug: '',
  contact_email: '',
  is_active: true
})

const resetForm = () => {
  formData.value = {
    name: '',
    domain: '',
    slug: '',
    contact_email: '',
    is_active: true
  }
  editingTenant.value = null
}

const filteredTenants = computed(() => {
  return tenants.value.filter(tenant => {
    const matchesSearch =
      tenant.name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      tenant.domain?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      tenant.contact_email?.toLowerCase().includes(searchQuery.value.toLowerCase())

    const matchesStatus =
      statusFilter.value === '' || tenant.is_active === (statusFilter.value === '1')

    return matchesSearch && matchesStatus
  })
})

const editTenant = (tenant) => {
  editingTenant.value = tenant
  formData.value = { ...tenant }
  showModal.value = true
}

const saveTenant = async () => {
  error.value = ''
  success.value = ''

  try {
    if (editingTenant.value) {
      await updateTenant(editingTenant.value.id, formData.value)
      success.value = 'Empresa atualizada com sucesso!'
    } else {
      await createTenant(formData.value)
      success.value = 'Empresa criada com sucesso!'
    }

    showModal.value = false
    resetForm()
    setTimeout(() => (success.value = ''), 3000)
  } catch (err) {
    error.value = err.message || 'Erro ao salvar empresa'
  }
}

const toggleTenantStatus = async (tenant) => {
  try {
    await toggleAPI(tenant.id, !tenant.is_active)
    success.value = `Empresa ${!tenant.is_active ? 'ativada' : 'desativada'} com sucesso!`
    setTimeout(() => (success.value = ''), 3000)
  } catch (err) {
    error.value = err.message || 'Erro ao alterar status'
  }
}

const deleteTenant = async (tenantId) => {
  if (confirm('Tem certeza que deseja deletar esta empresa?')) {
    try {
      await deleteTenantAPI(tenantId)
      success.value = 'Empresa deletada com sucesso!'
      setTimeout(() => (success.value = ''), 3000)
    } catch (err) {
      error.value = err.message || 'Erro ao deletar empresa'
    }
  }
}

onMounted(() => {
  fetchTenants()
})
</script>
