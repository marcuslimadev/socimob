<template>
  <div class="min-h-screen bg-slate-50">
    <header class="bg-slate-900 shadow-lg">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-white">👥 Gerenciar Usuários</h1>
            <p class="text-slate-400 mt-1">Administração de acesso ao sistema</p>
          </div>
          <div class="flex gap-3">
            <router-link to="/super-admin" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg transition">
              ← Voltar
            </router-link>
            <button @click="showCreateModal = true" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg transition font-semibold">
              + Novo Usuário
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Filtros -->
      <div class="bg-white border border-slate-200 rounded-3xl shadow-sm mb-6 p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Buscar por nome ou email..." 
            class="px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-slate-900 focus:border-transparent focus:outline-none"
          >
          <select v-model="roleFilter" class="px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-slate-900 focus:outline-none bg-white">
            <option value="">Todos os perfis</option>
            <option value="super_admin">Super Admin</option>
            <option value="admin">Admin</option>
            <option value="user">Usuário</option>
            <option value="client">Cliente</option>
          </select>
          <select v-model="statusFilter" class="px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-slate-900 focus:outline-none bg-white">
            <option value="">Todos os status</option>
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
          </select>
        </div>
      </div>

      <!-- Lista de Usuários -->
      <div class="grid grid-cols-1 gap-6">
        <div v-if="loading" class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-slate-900"></div>
          <p class="mt-4 text-slate-600">Carregando...</p>
        </div>
        
        <div v-else-if="filteredUsers.length === 0" class="bg-white border border-slate-200 rounded-3xl shadow-sm p-12 text-center">
          <p class="text-slate-600">Nenhum usuário encontrado.</p>
        </div>

        <div v-else class="bg-white border border-slate-200 rounded-3xl shadow-sm hover:shadow-md transition p-6" v-for="user in filteredUsers" :key="user.id">
          <div class="flex justify-between items-start">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-700 font-bold text-lg">
                {{ getInitials(user.name) }}
              </div>
              <div>
                <h3 class="text-xl font-bold text-slate-900">{{ user.name }}</h3>
                <p class="text-slate-500">{{ user.email }}</p>
              </div>
            </div>
          </div>

          <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="flex flex-col">
              <span class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-1">Perfil</span>
              <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium w-fit', getRoleBadgeClass(user.role)]">
                {{ getRoleLabel(user.role) }}
              </span>
            </div>
            
            <div class="flex flex-col">
              <span class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-1">Status</span>
              <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium w-fit', user.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800']">
                {{ user.is_active ? 'Ativo' : 'Inativo' }}
              </span>
            </div>
            
            <div class="flex flex-col" v-if="user.tenant">
              <span class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-1">Imobiliária</span>
              <span class="text-slate-900 font-medium">{{ user.tenant.name }}</span>
            </div>

            <div class="flex flex-col">
              <span class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-1">Criado em</span>
              <span class="text-slate-900 font-medium">{{ formatDate(user.created_at) }}</span>
            </div>
          </div>

          <div class="mt-6 flex gap-3 border-t border-slate-100 pt-4">
            <button @click="editUser(user)" class="px-4 py-2 text-slate-600 hover:bg-slate-50 rounded-xl transition font-medium text-sm">
              ✏️ Editar
            </button>
            <button 
              @click="toggleUserStatus(user)" 
              :class="['px-4 py-2 rounded-xl transition font-medium text-sm', user.is_active ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50']"
            >
              {{ user.is_active ? '🚫 Desativar' : '✅ Ativar' }}
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- Modal Criar/Editar Usuário -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 backdrop-blur-sm" @click="closeModal">
      <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="flex justify-between items-center p-8 border-b border-slate-100">
          <h2 class="text-2xl font-bold text-slate-900">{{ editingUser ? 'Editar' : 'Novo' }} Usuário</h2>
          <button @click="closeModal" class="text-slate-400 hover:text-slate-600 transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="saveUser" class="p-8 space-y-6">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Nome Completo *</label>
            <input v-model="form.name" type="text" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-slate-900 focus:border-transparent focus:outline-none transition">
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Email *</label>
            <input v-model="form.email" type="email" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-slate-900 focus:border-transparent focus:outline-none transition">
          </div>

          <div v-if="!editingUser">
            <label class="block text-sm font-medium text-slate-700 mb-2">Senha *</label>
            <input v-model="form.password" type="password" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-slate-900 focus:border-transparent focus:outline-none transition">
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Perfil *</label>
            <select v-model="form.role" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-slate-900 focus:border-transparent focus:outline-none transition bg-white">
              <option value="super_admin">Super Admin</option>
              <option value="admin">Admin</option>
              <option value="user">Usuário</option>
              <option value="client">Cliente</option>
            </select>
          </div>

          <div v-if="form.role !== 'super_admin'">
            <label class="block text-sm font-medium text-slate-700 mb-2">Imobiliária</label>
            <select v-model="form.tenant_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-slate-900 focus:border-transparent focus:outline-none transition bg-white">
              <option value="">Selecione...</option>
              <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                {{ tenant.name }}
              </option>
            </select>
          </div>

          <div class="flex items-center">
            <input v-model="form.is_active" type="checkbox" id="is_active" class="w-5 h-5 text-slate-900 border-slate-300 rounded focus:ring-slate-900">
            <label for="is_active" class="ml-2 block text-sm text-slate-900">Usuário ativo</label>
          </div>

          <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
            <button type="button" @click="closeModal" class="px-6 py-3 border border-slate-200 text-slate-700 rounded-2xl hover:bg-slate-50 font-medium transition">
              Cancelar
            </button>
            <button type="submit" class="px-8 py-3 bg-slate-900 text-white rounded-2xl hover:bg-slate-800 font-semibold shadow-lg shadow-slate-200 transition">
              {{ editingUser ? 'Atualizar' : 'Criar' }}
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

const users = ref<any[]>([])
const tenants = ref<any[]>([])
const loading = ref(true)
const searchQuery = ref('')
const roleFilter = ref('')
const statusFilter = ref('')
const showCreateModal = ref(false)
const editingUser = ref<any>(null)

const form = ref({
  name: '',
  email: '',
  password: '',
  role: 'user',
  tenant_id: '',
  is_active: true
})

const filteredUsers = computed(() => {
  let result = users.value

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(u => 
      u.name.toLowerCase().includes(query) ||
      u.email.toLowerCase().includes(query)
    )
  }

  if (roleFilter.value) {
    result = result.filter(u => u.role === roleFilter.value)
  }

  if (statusFilter.value !== '') {
    result = result.filter(u => u.is_active === (statusFilter.value === '1'))
  }

  return result
})

async function loadUsers() {
  try {
    loading.value = true
    const response = await api.get('/super-admin/users')
    users.value = response.data.users || []
  } catch (error) {
    console.error('Erro ao carregar usuários:', error)
    alert('Erro ao carregar usuários')
  } finally {
    loading.value = false
  }
}

async function loadTenants() {
  try {
    const response = await api.get('/super-admin/tenants')
    // Handle pagination structure
    if (response.data.data && response.data.data.data) {
      tenants.value = response.data.data.data
    } else if (response.data.data && Array.isArray(response.data.data)) {
      tenants.value = response.data.data
    } else {
      tenants.value = []
    }
  } catch (error) {
    console.error('Erro ao carregar tenants:', error)
  }
}

function editUser(user: any) {
  editingUser.value = user
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role,
    tenant_id: user.tenant_id || '',
    is_active: Boolean(user.is_active)
  }
  showCreateModal.value = true
}

async function toggleUserStatus(user: any) {
  if (!confirm(`Deseja ${user.is_active ? 'desativar' : 'ativar'} este usuário?`)) {
    return
  }

  try {
    await api.put(`/super-admin/users/${user.id}`, {
      ...user,
      is_active: !user.is_active
    })
    loadUsers()
  } catch (error) {
    console.error('Erro ao alterar status:', error)
    alert('Erro ao alterar status do usuário')
  }
}

async function saveUser() {
  try {
    const data = { ...form.value }
    
    if (editingUser.value) {
      if (!data.password) {
        delete data.password
      }
      await api.put(`/super-admin/users/${editingUser.value.id}`, data)
    } else {
      await api.post('/super-admin/users', data)
    }
    
    closeModal()
    loadUsers()
  } catch (error) {
    console.error('Erro ao salvar usuário:', error)
    alert('Erro ao salvar usuário')
  }
}

function closeModal() {
  showCreateModal.value = false
  editingUser.value = null
  form.value = {
    name: '',
    email: '',
    password: '',
    role: 'user',
    tenant_id: '',
    is_active: true
  }
}

function getInitials(name: string) {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2)
}

function getRoleBadgeClass(role: string) {
  const classes: any = {
    super_admin: 'bg-slate-800 text-white',
    admin: 'bg-slate-200 text-slate-800',
    user: 'bg-slate-100 text-slate-600',
    client: 'bg-slate-50 text-slate-500'
  }
  return classes[role] || 'bg-gray-100 text-gray-800'
}

function getRoleLabel(role: string) {
  const labels: any = {
    super_admin: 'Super Admin',
    admin: 'Admin',
    user: 'Usuário',
    client: 'Cliente'
  }
  return labels[role] || role
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('pt-BR')
}

onMounted(() => {
  loadUsers()
  loadTenants()
})
</script>

<style scoped>
.users-page {
  min-height: 100vh;
  background: #f5f5f5;
}

.header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 20px 0;
  margin-bottom: 30px;
}

.header h1 {
  color: white;
  margin: 0;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.actions {
  display: flex;
  gap: 10px;
}

.filters {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
}

.search-input {
  flex: 1;
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.filter-select {
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  min-width: 150px;
}

.users-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.user-card {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.user-header {
  display: flex;
  align-items: center;
  gap: 15px;
}

.user-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: bold;
  flex-shrink: 0;
}

.user-info h3 {
  margin: 0 0 4px 0;
  color: #333;
  font-size: 18px;
}

.user-email {
  margin: 0;
  color: #666;
  font-size: 14px;
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.role-badge, .status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.role-badge.super_admin {
  background: #e3f2fd;
  color: #1565c0;
}

.role-badge.admin {
  background: #f3e5f5;
  color: #6a1b9a;
}

.role-badge.user {
  background: #e8f5e9;
  color: #2e7d32;
}

.role-badge.client {
  background: #fff3e0;
  color: #e65100;
}

.status-badge.active {
  background: #d4edda;
  color: #155724;
}

.status-badge.inactive {
  background: #f8d7da;
  color: #721c24;
}

.tenant-info {
  font-size: 14px;
  color: #666;
}

.tenant-info strong {
  color: #333;
}

.user-meta {
  font-size: 12px;
  color: #999;
}

.user-actions {
  display: flex;
  gap: 8px;
  padding-top: 10px;
  border-top: 1px solid #eee;
}

.loading, .empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 40px;
  color: #666;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #eee;
}

.modal-header h2 {
  margin: 0;
  color: #333;
}

.btn-close {
  background: none;
  border: none;
  font-size: 32px;
  cursor: pointer;
  color: #999;
  padding: 0;
  width: 32px;
  height: 32px;
  line-height: 1;
}

.btn-close:hover {
  color: #333;
}

.modal-body {
  padding: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #333;
  font-weight: 500;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: auto;
  cursor: pointer;
}

.form-control {
  width: 100%;
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: #667eea;
}

.modal-footer {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.3s;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #5a6268;
}

.btn-warning {
  background: #ffc107;
  color: #333;
}

.btn-warning:hover {
  background: #e0a800;
}

.btn-success {
  background: #28a745;
  color: white;
}

.btn-success:hover {
  background: #218838;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}
</style>
