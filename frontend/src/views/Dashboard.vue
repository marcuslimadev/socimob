<template>
  <div class="min-h-screen bg-gray-50">
    <Navbar />
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-600 mt-1">Visão geral do sistema SOCIMOB</p>
      </div>

      <!-- Configurações do atendimento -->
      <section class="bg-white border border-slate-200 rounded-3xl shadow-sm p-6 mb-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-indigo-500">Atendimento inteligente</p>
            <h2 class="text-2xl font-semibold text-slate-900 mt-1">Personalize a apresentação da IA</h2>
            <p class="text-slate-500 text-sm mt-2">
              Esse nome aparece nas mensagens automáticas do atendimento virtual nas primeiras interações.
            </p>
          </div>
          <div class="w-full md:w-auto flex flex-col gap-3 md:flex-row md:items-center">
            <input
              v-model="iaName"
              type="text"
              class="w-full md:w-64 rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="Ex: Teresa"
              :disabled="settingsLoading"
            />
            <button
              class="px-6 py-3 rounded-2xl font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition disabled:opacity-60"
              :disabled="settingsSaving || settingsLoading"
              @click="salvarConfiguracoesIA"
            >
              {{ settingsSaving ? 'Salvando...' : 'Salvar alterações' }}
            </button>
          </div>
        </div>
        <div class="mt-3 space-y-1">
          <p class="text-xs text-slate-500">Ajuste disponível apenas para administradores.</p>
          <p v-if="settingsSuccess" class="text-sm text-emerald-600">{{ settingsSuccess }}</p>
          <p v-if="settingsError" class="text-sm text-red-600">{{ settingsError }}</p>
        </div>
      </section>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <StatCard
          title="Total de Leads"
          :value="stats.totalLeads"
          icon="users"
          color="blue"
        />
        <StatCard
          title="Conversas Ativas"
          :value="stats.conversasAtivas"
          icon="chat"
          color="green"
        />
        <StatCard
          title="Leads Hoje"
          :value="stats.leadsHoje"
          icon="calendar"
          color="purple"
        />
        <StatCard
          title="Taxa de Conversão"
          :value="`${stats.taxaConversao}%`"
          icon="chart"
          color="orange"
        />
      </div>

      <!-- Importação CTA -->
      <section class="bg-white border border-indigo-100 rounded-3xl shadow-sm p-6 mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div class="flex-1 space-y-3">
            <p class="text-xs uppercase tracking-[0.4em] text-indigo-500">Central de importação</p>
            <h2 class="text-2xl font-semibold text-slate-900">Monitoramento direto no CRM</h2>
            <p class="text-slate-600">
              Consulte o andamento da sincronização de imóveis e acesse a nova página completa de importação sem sair do dashboard.
            </p>
            <div>
              <p class="text-sm text-slate-500">Progresso atual</p>
              <div class="w-full bg-slate-100 h-3 rounded-full mt-2 overflow-hidden">
                <div
                  class="h-3 rounded-full bg-gradient-to-r from-indigo-500 via-sky-500 to-emerald-500 transition-all duration-500"
                  :style="{ width: `${importOverview.progresso || 0}%` }"
                ></div>
              </div>
              <p class="text-xs text-slate-500 mt-1">
                Última importação: {{ importOverview.ultimaImportacao ? formatarData(importOverview.ultimaImportacao) : '—' }}
              </p>
            </div>
          </div>
          <router-link
            to="/importacao"
            class="flex items-center justify-center px-6 py-3 bg-indigo-600 text-white font-semibold rounded-2xl shadow hover:bg-indigo-700 transition"
          >
            Abrir central
          </router-link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
          <div class="p-4 rounded-2xl bg-indigo-50 text-indigo-900">
            <p class="text-sm text-indigo-600">Imóveis sincronizados</p>
            <p class="text-2xl font-semibold">{{ importOverview.totalImoveis || 0 }}</p>
            <p class="text-xs text-indigo-500 mt-1">Base disponível no CRM</p>
          </div>
          <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-900">
            <p class="text-sm text-emerald-600">Ativos publicados</p>
            <p class="text-2xl font-semibold">{{ importOverview.ativos || 0 }}</p>
            <p class="text-xs text-emerald-500 mt-1">Atualizados nos portais</p>
          </div>
          <div class="p-4 rounded-2xl bg-amber-50 text-amber-900">
            <p class="text-sm text-amber-600">Pendências</p>
            <p class="text-2xl font-semibold">{{ importOverview.pendentes || importOverview.aguardandoDetalhes || importOverview.desatualizados || 0 }}</p>
            <p class="text-xs text-amber-500 mt-1">Sem coordenadas atualizadas</p>
          </div>
        </div>
      </section>

      <!-- Atividades Recentes -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Atividades Recentes</h2>
        
        <div v-if="loading" class="text-center py-8">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="atividade in atividadesRecentes"
            :key="atividade.id"
            class="flex items-start space-x-4 p-4 hover:bg-gray-50 rounded-lg transition"
          >
            <div class="flex-shrink-0">
              <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                <span class="text-blue-600 font-semibold">
                  {{ atividade.tipo === 'novo_lead' ? '👤' : '💬' }}
                </span>
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900">
                {{ atividade.descricao }}
              </p>
              <p class="text-sm text-gray-500 mt-1">
                {{ formatarData(atividade.created_at) }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, computed, ref } from 'vue'
import { useDashboardStore } from '../stores/dashboard'
import Navbar from '../components/Navbar.vue'
import StatCard from '../components/StatCard.vue'
import { useImportacaoStore } from '../stores/importacao'
import { useSettingsStore } from '../stores/settings'

const dashboardStore = useDashboardStore()
const importacaoStore = useImportacaoStore()
const settingsStore = useSettingsStore()

const stats = computed(() => dashboardStore.stats)
const atividadesRecentes = computed(() => dashboardStore.atividadesRecentes)
const loading = computed(() => dashboardStore.loading)
const importOverview = computed(() => importacaoStore.overview)
const settingsLoading = computed(() => settingsStore.loading)
const settingsSaving = computed(() => settingsStore.saving)

const iaName = ref('Teresa')
const settingsSuccess = ref('')
const settingsError = ref('')

let dashboardInterval

onMounted(async () => {
  await settingsStore.fetchSettings()
  iaName.value = settingsStore.items.ai_name || 'Teresa'
  await dashboardStore.fetchStats()
  await dashboardStore.fetchAtividades()
  await importacaoStore.fetchOverview()

  // Atualizar stats a cada 5 segundos (tempo real)
  dashboardInterval = setInterval(() => {
    dashboardStore.fetchStats()
    dashboardStore.fetchAtividades()
  }, 5000)
})

onBeforeUnmount(() => {
  if (dashboardInterval) {
    clearInterval(dashboardInterval)
  }
})

const formatarData = (data) => {
  return new Date(data).toLocaleString('pt-BR')
}

const salvarConfiguracoesIA = async () => {
  settingsSuccess.value = ''
  settingsError.value = ''

  const nome = iaName.value?.trim()

  if (!nome) {
    settingsError.value = 'Informe um nome para a atendente virtual.'
    return
  }

  try {
    await settingsStore.saveSettings({ ai_name: nome })
    settingsSuccess.value = 'Nome atualizado com sucesso!'
  } catch (error) {
    settingsError.value = 'Não foi possível salvar agora. Tente novamente.'
  }
}
</script>
