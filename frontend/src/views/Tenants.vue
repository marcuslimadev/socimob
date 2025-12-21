<template>
  <div class="min-h-screen app-container p-4">
    <!-- Header -->
    <header class="rounded-2xl shadow-2xl bg-gradient-to-r from-primary-700 via-accent-500 to-sunshine-500 text-white mb-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-black tracking-tighter uppercase">🏢 Gerenciar Empresas</h1>
            <p class="text-white/85 mt-1 font-semibold">Imobiliárias cadastradas no sistema</p>
          </div>
          <div class="flex gap-3">
            <router-link to="/super-admin" class="bauhaus-button px-4 py-2 text-sm">
              ← Voltar
            </router-link>
            <button @click="showModal = true" class="bauhaus-button px-4 py-2 text-sm bg-sunshine-500 text-ink hover:brightness-105">
              + Nova Empresa
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
      <!-- Filters -->
      <div class="bauhaus-card p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Buscar por nome ou email..."
            class="px-4 py-3 bauhaus-input"
          />
          <select v-model="statusFilter" class="px-4 py-3 bauhaus-input bg-white">
            <option value="">Todos os status</option>
            <option value="active">Ativos</option>
            <option value="inactive">Inativos</option>
          </select>
          <div class="text-sm font-bold text-ink/70 flex items-center justify-end uppercase tracking-wide">
            Mostrando {{ filteredTenants.length }} de {{ tenants.length }} empresas
          </div>
        </div>
      </div>

      <!-- Tenants List -->
      <div v-if="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-slate-200 border-t-slate-900"></div>
        <p class="mt-4 text-slate-900 font-bold uppercase tracking-wide">Carregando...</p>
      </div>

      <div v-else-if="filteredTenants.length === 0" class="bauhaus-card p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-primary-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
        <p class="mt-4 text-ink/70 font-medium">Nenhuma empresa encontrada</p>
        <button @click="showModal = true" class="mt-6 px-6 py-3 bauhaus-button text-sm">
          Cadastrar primeira empresa
        </button>
      </div>

      <div v-else class="grid grid-cols-1 gap-6">
        <div v-for="tenant in filteredTenants" :key="tenant.id" class="bauhaus-card hover:-translate-y-1 transition-all p-6 group">
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-3">
                <h3 class="text-xl font-black text-ink uppercase tracking-tight">{{ tenant.name }}</h3>
                <span :class="['px-2 py-1 rounded-md text-xs font-bold uppercase tracking-wide border', tenant.is_active ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-rose-100 text-rose-900 border-rose-200']">
                  {{ tenant.is_active ? 'Ativo' : 'Inativo' }}
                </span>
              </div>
              
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p class="text-ink/60 font-bold uppercase text-xs">Email</p>
                  <p class="font-medium text-ink">{{ tenant.contact_email || tenant.email }}</p>
                </div>
                <div>
                  <p class="text-ink/60 font-bold uppercase text-xs">Telefone</p>
                  <p class="font-medium text-ink">{{ tenant.contact_phone || tenant.phone || 'Não informado' }}</p>
                </div>
                <div>
                  <p class="text-ink/60 font-bold uppercase text-xs">Domínio</p>
                  <p class="font-medium text-ink">{{ tenant.domain || 'Não configurado' }}</p>
                </div>
                <div>
                  <p class="text-ink/60 font-bold uppercase text-xs">Cadastrado em</p>
                  <p class="font-medium text-ink">{{ formatDate(tenant.created_at) }}</p>
                </div>
              </div>

              <div class="mt-4 flex gap-4 text-sm">
                <div class="flex items-center gap-2">
                  <div class="p-1 bg-primary-50 border border-primary-100 rounded-md">
                    <svg class="w-4 h-4 text-primary-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                  </div>
                  <span class="text-ink/80 font-semibold">{{ tenant.users_count || 0 }} usuários</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="p-1 bg-sunshine-50 border border-sunshine-100 rounded-md">
                    <svg class="w-4 h-4 text-sunshine-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                  </div>
                  <span class="text-ink/80 font-semibold">{{ tenant.properties_count || 0 }} imóveis</span>
                </div>
              </div>
            </div>

            <div class="flex gap-2">
              <button @click="editTenant(tenant)" class="px-4 py-2 bauhaus-button text-xs">
                Editar
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-ink/80 flex items-center justify-center p-4 z-50 backdrop-blur-sm" @click="closeModal">
      <div class="bauhaus-card shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="flex justify-between items-center p-8 border-b border-ink/5">
          <h2 class="text-2xl font-black text-ink uppercase tracking-tight">{{ editingTenant ? 'Editar' : 'Nova' }} Empresa</h2>
          <button @click="closeModal" class="text-ink/50 hover:text-ink transition">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="saveTenant" class="p-8 space-y-6">
          <div>
            <label class="block text-sm font-bold text-ink mb-2 uppercase tracking-wide">Nome da Empresa *</label>
            <input v-model="form.name" type="text" required class="w-full px-4 py-3 bauhaus-input" />
          </div>

          <div class="grid grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-bold text-ink mb-2 uppercase tracking-wide">Email *</label>
              <input v-model="form.contact_email" type="email" required class="w-full px-4 py-3 bauhaus-input" />
            </div>
            <div>
              <label class="block text-sm font-bold text-ink mb-2 uppercase tracking-wide">Telefone</label>
              <input v-model="form.contact_phone" type="tel" class="w-full px-4 py-3 bauhaus-input" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-bold text-ink mb-2 uppercase tracking-wide">Domínio *</label>
            <input v-model="form.domain" type="text" required placeholder="exemplo.com" class="w-full px-4 py-3 bauhaus-input" />
          </div>

          <div class="border-t-2 border-slate-100 pt-6 mt-6">
            <h3 class="text-lg font-black text-ink mb-4 uppercase tracking-tight">Acesso Inicial</h3>
            <div class="grid grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-bold text-ink mb-2 uppercase tracking-wide">
                  {{ editingTenant ? 'Nova Senha (opcional)' : 'Senha Inicial *' }}
                </label>
                <input
                  v-model="form.admin_password"
                  type="password"
                  :required="!editingTenant"
                  placeholder="••••••••"
                  class="w-full px-4 py-3 bauhaus-input"
                />
              </div>
              <div>
                <label class="block text-sm font-bold text-ink mb-2 uppercase tracking-wide">Tipo de Acesso</label>
                <select v-model="form.admin_role" class="w-full px-4 py-3 bauhaus-input bg-white">
                  <option value="admin">Administrador</option>
                  <option value="user">Usuário</option>
                </select>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-bold text-ink mb-2 uppercase tracking-wide">Status</label>
            <select v-model="form.status" class="w-full px-4 py-3 bauhaus-input bg-white">
              <option value="active">Ativo</option>
              <option value="inactive">Inativo</option>
            </select>
          </div>

          <div class="flex justify-end gap-3 pt-6 border-t border-ink/5">
            <button type="button" @click="closeModal" class="px-6 py-3 bauhaus-button bauhaus-ghost text-sm">
              Cancelar
            </button>
            <button type="submit" class="px-8 py-3 bauhaus-button text-sm">
              {{ editingTenant ? 'Atualizar' : 'Cadastrar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const tenants = ref<any[]>([])
const loading = ref(true)
const searchQuery = ref('')
const statusFilter = ref('')
const showModal = ref(false)
const editingTenant = ref<any>(null)

const form = ref({
  name: '',
  contact_email: '',
  contact_phone: '',
  domain: '',
  status: 'active',
  admin_password: '',
  admin_role: 'admin'
})

const filteredTenants = computed(() => {
  let result = tenants.value

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(t => 
      t.name.toLowerCase().includes(query) || 
      (t.contact_email && t.contact_email.toLowerCase().includes(query)) ||
      (t.domain && t.domain.toLowerCase().includes(query))
    )
  }

  if (statusFilter.value) {
    result = result.filter(t => {
      const isActive = statusFilter.value === 'active'
      return t.is_active === isActive
    })
  }

  return result
})

async function loadTenants() {
  try {
    loading.value = true
    const response = await api.get('/api/super-admin/tenants')
    // Handle pagination structure
    if (response.data.data && response.data.data.data) {
      tenants.value = response.data.data.data
    } else if (response.data.data && Array.isArray(response.data.data)) {
      tenants.value = response.data.data
    } else {
      tenants.value = []
    }
  } catch (error: any) {
    console.error('Erro ao carregar empresas:', error)
    if (error.response?.status !== 404) {
      alert('Erro ao carregar empresas')
    }
    tenants.value = []
  } finally {
    loading.value = false
  }
}

function editTenant(tenant: any) {
  editingTenant.value = tenant
  form.value = { 
    ...tenant,
    status: tenant.is_active ? 'active' : 'inactive',
    admin_password: '',
    admin_role: 'admin'
  }
  showModal.value = true
}

async function saveTenant() {
  try {
    const payload = {
      ...form.value,
      is_active: form.value.status === 'active',
      contact_email: form.value.contact_email || null,
      contact_phone: form.value.contact_phone || null,
      domain: form.value.domain || null,
      admin_password: form.value.admin_password || null,
      admin_role: form.value.admin_role || 'admin'
    }

    if (editingTenant.value) {
      await api.put(`/api/super-admin/tenants/${editingTenant.value.id}`, payload)
    } else {
      await api.post('/api/super-admin/tenants', payload)
    }
    closeModal()
    loadTenants()
  } catch (error) {
    console.error('Erro ao salvar empresa:', error)
    alert('Erro ao salvar empresa')
  }
}

function closeModal() {
  showModal.value = false
  editingTenant.value = null
  form.value = {
    name: '',
    contact_email: '',
    contact_phone: '',
    domain: '',
    status: 'active',
    admin_password: '',
    admin_role: 'admin'
  }
}

function formatDate(date: string) {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('pt-BR')
}

onMounted(() => {
  loadTenants()
})
</script>
