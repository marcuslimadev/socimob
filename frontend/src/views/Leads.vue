<template>
  <div class="min-h-screen bg-gray-50">
    <Navbar />
    
    <div class="px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">🎯 Funil de Vendas</h1>
          <p class="text-gray-600 mt-1">Kanban Board - Arraste os cards entre as etapas do funil</p>
        </div>
        
        <!-- Filtros -->
        <div class="flex items-center space-x-4">
          <button
            @click="toggleView"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
          >
            <svg v-if="viewMode === 'kanban'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
            </svg>
            {{ viewMode === 'kanban' ? 'Lista' : 'Kanban' }}
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mx-auto"></div>
        <p class="text-gray-600 mt-4">Carregando leads...</p>
      </div>

      <!-- Kanban Board View -->
      <div v-else-if="viewMode === 'kanban'" class="overflow-x-auto pb-4">
        <div class="flex gap-4 min-w-max">
          <FunilColumn
            v-for="statusFunil in statusDoFunil"
            :key="statusFunil.key"
            :status="statusFunil"
            :leads="getLeadsByStatus(statusFunil.key)"
            @dragstart="handleDragStart"
            @drop="handleDrop($event, statusFunil.key)"
            @view="verDetalhes"
            @edit="editarLead"
          />
          
          <!-- Empty State -->
          <div v-if="leads.length === 0" class="w-full text-center py-12">
            <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Nenhum lead encontrado</h3>
            <p class="text-gray-600">Os leads capturados via WhatsApp aparecerão aqui</p>
          </div>
        </div>
      </div>

      <!-- Table View (Original) -->
      <div v-else>
        <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-sm text-gray-600">Selecione clientes para aplicar ações em massa.</p>
            <p v-if="hasSelection" class="text-sm font-medium text-gray-800">
              {{ selectedCount }} cliente{{ selectedCount === 1 ? '' : 's' }} selecionado{{ selectedCount === 1 ? '' : 's' }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              @click="handleBulkDeleteConversations"
              :disabled="!hasSelection || bulkDeletingConversations"
              class="px-4 py-2 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ bulkDeletingConversations ? 'Excluindo conversas...' : 'Excluir conversas' }}
            </button>
            <button
              @click="handleBulkDeleteLeads"
              :disabled="!hasSelection || bulkDeletingLeads"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ bulkDeletingLeads ? 'Excluindo clientes...' : 'Excluir clientes' }}
            </button>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3">
                  <input
                    type="checkbox"
                    :checked="allSelected"
                    @change="toggleSelectAll($event.target.checked)"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    aria-label="Selecionar todos os clientes"
                  />
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Nome
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Telefone
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Estado
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Orçamento
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status Funil
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Ações
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="lead in leads" :key="lead.id" class="hover:bg-gray-50">
                <td class="px-4 py-4">
                  <input
                    type="checkbox"
                    :checked="isLeadSelected(lead.id)"
                    @change="toggleLeadSelection(lead.id, $event.target.checked)"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    :aria-label="`Selecionar ${lead.nome || lead.telefone}`"
                  />
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ lead.nome || 'Sem nome' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ lead.telefone }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ lead.state || '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">R$ {{ formatarMoeda(lead.budget_min) }} - R$ {{ formatarMoeda(lead.budget_max) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                    :class="getStatusClass(lead.status)"
                  >
                    {{ formatStatus(lead.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex flex-wrap items-center gap-3">
                    <button
                      @click="verDetalhes(lead)"
                      class="text-blue-600 hover:text-blue-900"
                    >
                      Ver
                    </button>
                    <button
                      @click="editarLead(lead)"
                      class="text-green-600 hover:text-green-900"
                    >
                      Editar
                    </button>
                    <button
                      @click="handleDeleteConversations(lead.id)"
                      :disabled="isDeletingConversations(lead.id)"
                      class="text-orange-600 hover:text-orange-800 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {{ isDeletingConversations(lead.id) ? 'Removendo...' : 'Excluir conversas' }}
                    </button>
                    <button
                      @click="handleDeleteLead(lead.id)"
                      :disabled="isDeletingLead(lead.id)"
                      class="text-red-600 hover:text-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {{ isDeletingLead(lead.id) ? 'Excluindo...' : 'Excluir' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="!loading && leads.length === 0" class="text-center py-8 text-gray-500">
            Nenhum lead encontrado
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <LeadDetailsModal
      :is-open="showDetailsModal"
      :lead-id="selectedLeadId"
      @close="showDetailsModal = false"
      @edit="handleEditFromDetails"
      @delete="handleDeleteLeadFromDetails"
      @delete-conversations="handleDeleteConversationsFromDetails"
    />

    <LeadEditModal
      :is-open="showEditModal"
      :lead="selectedLead"
      @close="showEditModal = false"
      @saved="handleLeadSaved"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useLeadsStore } from '../stores/leads'
import api from '../services/api'
import Navbar from '../components/Navbar.vue'
import FunilColumn from '../components/FunilColumn.vue'
import LeadDetailsModal from '../components/LeadDetailsModal.vue'
import LeadEditModal from '../components/LeadEditModal.vue'

const leadsStore = useLeadsStore()

const leads = computed(() => leadsStore.leads)
const loading = computed(() => leadsStore.loading)

const viewMode = ref('kanban') // 'kanban' ou 'table'
const draggedLead = ref(null)
const selectedLeads = ref(new Set())
const deletingLeadIds = ref(new Set())
const deletingConversationIds = ref(new Set())
const bulkDeletingLeads = ref(false)
const bulkDeletingConversations = ref(false)

// Auto-refresh
let autoRefreshInterval = null

// Modals
const showDetailsModal = ref(false)
const showEditModal = ref(false)
const selectedLeadId = ref(null)
const selectedLead = ref(null)

// Status do Funil de Vendas (ordem correta do processo)
const statusDoFunil = [
  { key: 'novo', label: 'Novo Lead', icon: '🆕', color: 'blue' },
  { key: 'em_atendimento', label: 'Em Atendimento', icon: '💬', color: 'yellow' },
  { key: 'qualificado', label: 'Qualificado', icon: '✅', color: 'green' },
  { key: 'proposta', label: 'Proposta', icon: '📋', color: 'purple' },
  { key: 'fechado', label: 'Fechado', icon: '🎉', color: 'emerald' },
  { key: 'perdido', label: 'Perdido', icon: '❌', color: 'red' }
]

onMounted(() => {
  leadsStore.fetchLeads()
  
  // Auto-refresh a cada 30 segundos (tempo real)
  autoRefreshInterval = setInterval(() => {
    leadsStore.fetchLeads()
  }, 30000)
})

onUnmounted(() => {
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval)
  }
})

watch(leads, (novosLeads) => {
  const disponiveis = new Set(novosLeads.map(lead => lead.id))
  const filtrados = [...selectedLeads.value].filter(id => disponiveis.has(id))
  if (filtrados.length !== selectedLeads.value.size) {
    selectedLeads.value = new Set(filtrados)
  }
})

const selectedCount = computed(() => selectedLeads.value.size)
const hasSelection = computed(() => selectedCount.value > 0)
const allSelected = computed(() => leads.value.length > 0 && selectedCount.value === leads.value.length)

const updateSetValue = (setRef, id, shouldAdd) => {
  const novoSet = new Set(setRef.value)
  if (shouldAdd) {
    novoSet.add(id)
  } else {
    novoSet.delete(id)
  }
  setRef.value = novoSet
}

const toggleLeadSelection = (leadId, checked = null) => {
  const alreadySelected = selectedLeads.value.has(leadId)
  const shouldSelect = checked === null ? !alreadySelected : checked
  updateSetValue(selectedLeads, leadId, shouldSelect)
}

const toggleSelectAll = (checked) => {
  if (!checked) {
    selectedLeads.value = new Set()
    return
  }
  selectedLeads.value = new Set(leads.value.map(lead => lead.id))
}

const isLeadSelected = (leadId) => selectedLeads.value.has(leadId)
const isDeletingLead = (leadId) => deletingLeadIds.value.has(leadId)
const isDeletingConversations = (leadId) => deletingConversationIds.value.has(leadId)

const clearSelection = () => {
  selectedLeads.value = new Set()
}

// Agrupar leads por status do funil
const getLeadsByStatus = (status) => {
  return leads.value.filter(lead => lead.status === status)
}

const deleteConversationsByLeadIds = async (ids) => {
  if (!ids.length) return
  await api.delete('/api/conversas', {
    data: { lead_ids: ids }
  })
}

const handleDeleteConversations = async (leadId) => {
  if (!leadId) return
  if (!confirm('Deseja realmente excluir o histórico de conversas deste cliente?')) return

  updateSetValue(deletingConversationIds, leadId, true)
  try {
    await deleteConversationsByLeadIds([leadId])
    alert('Conversas excluídas com sucesso.')
  } catch (error) {
    console.error('Erro ao excluir conversas do lead', error)
    alert('Erro ao excluir conversas. Tente novamente.')
  } finally {
    updateSetValue(deletingConversationIds, leadId, false)
  }
}

const handleBulkDeleteConversations = async () => {
  if (!hasSelection.value) return
  if (!confirm('Deseja realmente excluir as conversas dos clientes selecionados?')) return

  bulkDeletingConversations.value = true
  const ids = Array.from(selectedLeads.value)
  try {
    await deleteConversationsByLeadIds(ids)
    alert('Conversas removidas para os clientes selecionados.')
  } catch (error) {
    console.error('Erro ao excluir conversas em massa', error)
    alert('Erro ao excluir conversas. Tente novamente.')
  } finally {
    bulkDeletingConversations.value = false
  }
}

const handleDeleteLead = async (leadId) => {
  if (!leadId) return
  if (!confirm('Tem certeza que deseja excluir este cliente? Esta ação é irreversível.')) return

  updateSetValue(deletingLeadIds, leadId, true)
  try {
    await leadsStore.deleteLead(leadId)
    if (selectedLeads.value.has(leadId)) {
      updateSetValue(selectedLeads, leadId, false)
    }
    if (selectedLeadId.value === leadId) {
      showDetailsModal.value = false
      selectedLeadId.value = null
    }
    alert('Cliente excluído com sucesso.')
  } catch (error) {
    console.error('Erro ao excluir lead', error)
    alert('Erro ao excluir o cliente. Tente novamente.')
  } finally {
    updateSetValue(deletingLeadIds, leadId, false)
  }
}

const handleBulkDeleteLeads = async () => {
  if (!hasSelection.value) return
  if (!confirm('Tem certeza que deseja excluir todos os clientes selecionados?')) return

  bulkDeletingLeads.value = true
  const ids = Array.from(selectedLeads.value)
  try {
    await leadsStore.deleteLeads(ids)
    if (selectedLeadId.value && ids.includes(selectedLeadId.value)) {
      showDetailsModal.value = false
      selectedLeadId.value = null
    }
    clearSelection()
    alert('Clientes selecionados excluídos com sucesso.')
  } catch (error) {
    console.error('Erro ao excluir leads em massa', error)
    alert('Erro ao excluir os clientes selecionados.')
  } finally {
    bulkDeletingLeads.value = false
  }
}

// Drag and Drop handlers
const handleDragStart = (lead) => {
  draggedLead.value = lead
}

const handleDrop = async (event, targetStatus) => {
  event.preventDefault()
  
  if (!draggedLead.value) return
  
  const leadId = draggedLead.value.id
  const oldStatus = draggedLead.value.status
  
  if (oldStatus === targetStatus) {
    draggedLead.value = null
    return
  }

  try {
    // Atualizar no backend
    await leadsStore.updateLeadStatus(leadId, targetStatus)
    
    // Recarregar leads para atualizar o board
    await leadsStore.fetchLeads()
    
    console.log(`✅ Lead movido: ${oldStatus} → ${targetStatus}`)
  } catch (error) {
    console.error('❌ Erro ao mover lead:', error)
    alert('Erro ao mover o lead. Tente novamente.')
  } finally {
    draggedLead.value = null
  }
}

// View toggle
const toggleView = () => {
  viewMode.value = viewMode.value === 'kanban' ? 'table' : 'kanban'
}

// Formatação
const formatarMoeda = (valor) => {
  if (!valor) return '0'
  return new Intl.NumberFormat('pt-BR').format(valor)
}

const getStatusClass = (status) => {
  const classes = {
    'novo': 'bg-blue-100 text-blue-800',
    'em_atendimento': 'bg-yellow-100 text-yellow-800',
    'qualificado': 'bg-green-100 text-green-800',
    'proposta': 'bg-purple-100 text-purple-800',
    'fechado': 'bg-emerald-100 text-emerald-800',
    'perdido': 'bg-red-100 text-red-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const formatStatus = (status) => {
  const labels = {
    'novo': 'Novo',
    'em_atendimento': 'Em Atendimento',
    'qualificado': 'Qualificado',
    'proposta': 'Proposta',
    'fechado': 'Fechado',
    'perdido': 'Perdido'
  }
  return labels[status] || status
}

// Ações
const verDetalhes = (lead) => {
  selectedLeadId.value = lead.id
  showDetailsModal.value = true
}

const editarLead = (lead) => {
  selectedLead.value = lead
  showEditModal.value = true
}

const handleDeleteLeadFromDetails = async (leadId) => {
  await handleDeleteLead(leadId)
}

const handleDeleteConversationsFromDetails = async (leadId) => {
  await handleDeleteConversations(leadId)
}

const handleEditFromDetails = (lead) => {
  showDetailsModal.value = false
  setTimeout(() => {
    selectedLead.value = lead
    showEditModal.value = true
  }, 300)
}

const handleLeadSaved = async () => {
  await leadsStore.fetchLeads()
}
</script>
