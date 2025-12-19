<template>
  <div class="min-h-screen bg-slate-50">
    <!-- Header -->
    <header class="bg-slate-900 border-b border-slate-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase">💳 Gerenciar Assinaturas</h1>
            <p class="text-slate-400 mt-1 font-medium">Controle de planos e pagamentos</p>
          </div>
          <router-link to="/super-admin" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-none border border-slate-700 transition font-bold uppercase text-sm tracking-wide">
            ← Voltar
          </router-link>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Cards de Resumo -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-colors duration-300">
          <div class="flex items-center">
            <div class="p-3 bg-slate-100 border border-slate-200">
              <span class="text-2xl">💰</span>
            </div>
            <div class="ml-4">
              <p class="text-sm font-bold text-slate-500 uppercase tracking-wide">Receita Total</p>
              <p class="text-2xl font-black text-slate-900">R$ {{ formatMoney(stats.total_revenue) }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-colors duration-300">
          <div class="flex items-center">
            <div class="p-3 bg-slate-100 border border-slate-200">
              <span class="text-2xl">✅</span>
            </div>
            <div class="ml-4">
              <p class="text-sm font-bold text-slate-500 uppercase tracking-wide">Ativas</p>
              <p class="text-2xl font-black text-slate-900">{{ stats.active_subscriptions }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-colors duration-300">
          <div class="flex items-center">
            <div class="p-3 bg-slate-100 border border-slate-200">
              <span class="text-2xl">⏳</span>
            </div>
            <div class="ml-4">
              <p class="text-sm font-bold text-slate-500 uppercase tracking-wide">Pendentes</p>
              <p class="text-2xl font-black text-slate-900">{{ stats.pending_subscriptions }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-colors duration-300">
          <div class="flex items-center">
            <div class="p-3 bg-slate-100 border border-slate-200">
              <span class="text-2xl">❌</span>
            </div>
            <div class="ml-4">
              <p class="text-sm font-bold text-slate-500 uppercase tracking-wide">Canceladas</p>
              <p class="text-2xl font-black text-slate-900">{{ stats.cancelled_subscriptions }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filtros -->
      <div class="bg-white border-2 border-slate-200 rounded-none p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Buscar por imobiliária..." 
            class="px-4 py-3 border-2 border-slate-200 rounded-none focus:ring-0 focus:border-slate-900 focus:outline-none font-medium text-slate-900 placeholder-slate-400"
          >
          <select v-model="statusFilter" class="px-4 py-3 border-2 border-slate-200 rounded-none focus:ring-0 focus:border-slate-900 focus:outline-none bg-white font-medium text-slate-900">
            <option value="">Todos os status</option>
            <option value="active">Ativas</option>
            <option value="pending">Pendentes</option>
            <option value="cancelled">Canceladas</option>
            <option value="expired">Expiradas</option>
          </select>
          <select v-model="planFilter" class="px-4 py-3 border-2 border-slate-200 rounded-none focus:ring-0 focus:border-slate-900 focus:outline-none bg-white font-medium text-slate-900">
            <option value="">Todos os planos</option>
            <option value="basic">Básico</option>
            <option value="professional">Profissional</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
      </div>

      <!-- Lista de Assinaturas -->
      <div class="space-y-6">
        <div v-if="loading" class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-slate-200 border-t-slate-900"></div>
          <p class="mt-4 text-slate-900 font-bold uppercase tracking-wide">Carregando...</p>
        </div>
        
        <div v-else-if="filteredSubscriptions.length === 0" class="bg-white border-2 border-slate-200 rounded-none p-12 text-center">
          <p class="text-slate-500 font-medium">Nenhuma assinatura encontrada.</p>
        </div>

        <div v-else class="bg-white border-2 border-slate-200 rounded-none hover:border-slate-900 transition-colors p-6 group" v-for="subscription in filteredSubscriptions" :key="subscription.id">
          <div class="flex justify-between items-start mb-6 pb-6 border-b-2 border-slate-100">
            <div class="flex items-center gap-4 flex-wrap">
              <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight">{{ subscription.tenant?.name || 'Tenant não encontrado' }}</h3>
              <span :class="['px-2 py-1 rounded-none text-xs font-bold uppercase tracking-wide border', getStatusClasses(subscription.status)]">
                {{ getStatusLabel(subscription.status) }}
              </span>
              <span :class="['px-2 py-1 rounded-none text-xs font-bold uppercase tracking-wide border', getPlanClasses(subscription.plan)]">
                {{ getPlanLabel(subscription.plan) }}
              </span>
            </div>
            <div>
              <button @click="viewDetails(subscription)" class="px-4 py-2 bg-slate-100 hover:bg-slate-900 hover:text-white text-slate-900 border border-slate-200 rounded-none transition font-bold uppercase text-xs tracking-wide">
                Ver Detalhes
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="space-y-3">
              <div class="flex justify-between text-sm">
                <strong class="text-slate-900 font-bold uppercase">Valor Mensal:</strong>
                <span class="text-emerald-700 font-black">R$ {{ formatMoney(subscription.amount) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <strong class="text-slate-900 font-bold uppercase">Forma de Pagamento:</strong>
                <span class="text-slate-600 font-medium">{{ subscription.payment_method || 'Não informado' }}</span>
              </div>
            </div>

            <div class="space-y-3">
              <div class="flex justify-between text-sm">
                <strong class="text-slate-900 font-bold uppercase">Início:</strong>
                <span class="text-slate-600 font-medium">{{ formatDate(subscription.start_date) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <strong class="text-slate-900 font-bold uppercase">Próximo Vencimento:</strong>
                <span :class="['font-medium', isExpiringSoon(subscription.next_billing_date) ? 'text-rose-600 font-bold' : 'text-slate-600']">
                  {{ formatDate(subscription.next_billing_date) }}
                </span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t-2 border-slate-100">
            <div class="space-y-2">
              <div class="text-xs font-bold text-slate-500 uppercase tracking-wide">Usuários</div>
              <div class="h-2 bg-slate-100 w-full">
                <div 
                  class="h-full bg-slate-900 transition-all duration-300" 
                  :style="{width: getLimitPercentage(subscription.users_count, subscription.users_limit) + '%'}"
                ></div>
              </div>
              <div class="text-xs text-slate-500 text-right font-medium">{{ subscription.users_count || 0 }} / {{ subscription.users_limit || '∞' }}</div>
            </div>

            <div class="space-y-2">
              <div class="text-xs font-bold text-slate-500 uppercase tracking-wide">Imóveis</div>
              <div class="h-2 bg-slate-100 w-full">
                <div 
                  class="h-full bg-slate-900 transition-all duration-300" 
                  :style="{width: getLimitPercentage(subscription.properties_count, subscription.properties_limit) + '%'}"
                ></div>
              </div>
              <div class="text-xs text-slate-500 text-right font-medium">{{ subscription.properties_count || 0 }} / {{ subscription.properties_limit || '∞' }}</div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const subscriptions = ref<any[]>([])
const loading = ref(true)
const searchQuery = ref('')
const statusFilter = ref('')
const planFilter = ref('')

const stats = ref({
  total_revenue: 0,
  active_subscriptions: 0,
  pending_subscriptions: 0,
  cancelled_subscriptions: 0
})

const filteredSubscriptions = computed(() => {
  let result = subscriptions.value

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(s => 
      s.tenant?.name.toLowerCase().includes(query)
    )
  }

  if (statusFilter.value) {
    result = result.filter(s => s.status === statusFilter.value)
  }

  if (planFilter.value) {
    result = result.filter(s => s.plan === planFilter.value)
  }

  return result
})

async function loadSubscriptions() {
  try {
    loading.value = true
    const response = await api.get('/api/super-admin/subscriptions')
    subscriptions.value = response.data.subscriptions || []
    
    // Calcular estatísticas
    stats.value = {
      total_revenue: subscriptions.value
        .filter(s => s.status === 'active')
        .reduce((sum, s) => sum + parseFloat(s.amount || 0), 0),
      active_subscriptions: subscriptions.value.filter(s => s.status === 'active').length,
      pending_subscriptions: subscriptions.value.filter(s => s.status === 'pending').length,
      cancelled_subscriptions: subscriptions.value.filter(s => s.status === 'cancelled').length
    }
  } catch (error) {
    console.error('Erro ao carregar assinaturas:', error)
    // alert('Erro ao carregar assinaturas') // Suppress alert for now or handle gracefully
    subscriptions.value = [] // Ensure empty array on error
  } finally {
    loading.value = false
  }
}

function viewDetails(subscription: any) {
  alert(`Detalhes da assinatura ${subscription.id}`)
}

function getStatusLabel(status: string) {
  const labels: any = {
    active: 'Ativa',
    pending: 'Pendente',
    cancelled: 'Cancelada',
    expired: 'Expirada'
  }
  return labels[status] || status
}

function getStatusClasses(status: string) {
  const classes: any = {
    active: 'bg-emerald-100 text-emerald-900 border-emerald-200',
    pending: 'bg-amber-100 text-amber-900 border-amber-200',
    cancelled: 'bg-rose-100 text-rose-900 border-rose-200',
    expired: 'bg-slate-200 text-slate-600 border-slate-300'
  }
  return classes[status] || 'bg-slate-100 text-slate-900 border-slate-200'
}

function getPlanLabel(plan: string) {
  const labels: any = {
    basic: 'Básico',
    professional: 'Profissional',
    enterprise: 'Enterprise'
  }
  return labels[plan] || plan
}

function getPlanClasses(plan: string) {
  const classes: any = {
    basic: 'bg-blue-50 text-blue-900 border-blue-200',
    professional: 'bg-purple-50 text-purple-900 border-purple-200',
    enterprise: 'bg-orange-50 text-orange-900 border-orange-200'
  }
  return classes[plan] || 'bg-slate-50 text-slate-900 border-slate-200'
}

function formatMoney(value: any) {
  const num = parseFloat(value || 0)
  return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(date: string) {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('pt-BR')
}

function isExpiringSoon(date: string) {
  if (!date) return false
  const daysUntil = Math.ceil((new Date(date).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24))
  return daysUntil <= 7 && daysUntil >= 0
}

function getLimitPercentage(current: number, limit: number) {
  if (!limit || limit === 0) return 0
  return Math.min((current / limit) * 100, 100)
}

onMounted(() => {
  loadSubscriptions()
})
</script>
