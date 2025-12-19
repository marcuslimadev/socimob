<template>
  <div
    class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow p-4 border-l-4"
    :class="getStatusBorder(lead.status)"
  >
    <!-- Header do Card -->
    <div class="flex items-start justify-between mb-3 gap-3">
      <div class="flex-1">
        <h3 class="font-semibold text-gray-900 text-sm mb-1">
          {{ lead.nome || 'Sem nome' }}
        </h3>
        <p class="text-xs text-gray-500 flex items-center">
          <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
          </svg>
          {{ formatPhone(lead.telefone) }}
        </p>
      </div>
      
      <div class="flex items-center gap-2">
        <!-- Status Badge -->
        <span
          class="px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap"
          :class="getStatusClass(lead.status)"
        >
          {{ formatStatus(lead.status) }}
        </span>

        <!-- Drag handle -->
        <button
          type="button"
          class="p-1 text-gray-400 hover:text-gray-600 rounded cursor-move"
          draggable="true"
          title="Arraste para mover"
          @dragstart="$emit('dragstart', lead)"
          @dragend="$emit('dragend')"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h.01M12 6h.01M16 6h.01M8 12h.01M12 12h.01M16 12h.01M8 18h.01M12 18h.01M16 18h.01" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Orçamento -->
    <div class="mb-3 bg-gray-50 rounded p-2">
      <p class="text-xs text-gray-600 mb-1">💰 Orçamento</p>
      <p class="text-sm font-semibold text-gray-900">
        R$ {{ formatMoney(lead.budget_min) }} - R$ {{ formatMoney(lead.budget_max) }}
      </p>
    </div>

    <!-- Preferências -->
    <div class="grid grid-cols-3 gap-2 mb-3">
      <div v-if="lead.quartos" class="bg-blue-50 rounded px-2 py-1 text-center">
        <p class="text-xs text-blue-600 font-medium">🛏️ {{ lead.quartos }}</p>
      </div>
      <div v-if="lead.suites" class="bg-purple-50 rounded px-2 py-1 text-center">
        <p class="text-xs text-purple-600 font-medium">🚿 {{ lead.suites }}</p>
      </div>
      <div v-if="lead.garagem" class="bg-green-50 rounded px-2 py-1 text-center">
        <p class="text-xs text-green-600 font-medium">🚗 {{ lead.garagem }}</p>
      </div>
    </div>

    <!-- Localização -->
    <div v-if="lead.city" class="flex items-center text-xs text-gray-600 mb-3">
      <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
      </svg>
      {{ lead.city }}
    </div>

    <!-- Origem e Data -->
    <div class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t border-gray-100">
      <span class="flex items-center">
        <svg v-if="lead.origem === 'whatsapp'" class="w-3 h-3 mr-1 text-green-600" fill="currentColor" viewBox="0 0 24 24">
          <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
        {{ lead.origem || 'Manual' }}
      </span>
      <span>{{ formatDate(lead.created_at) }}</span>
    </div>

    <!-- Ações -->
    <div class="flex gap-2 mt-3 pt-3 border-t border-gray-100">
      <button
        @click.stop="$emit('view', lead)"
        class="flex-1 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded transition"
      >
        Ver Detalhes
      </button>
      <button
        @click.stop="$emit('edit', lead)"
        class="flex-1 px-3 py-1.5 text-xs font-medium text-green-600 bg-green-50 hover:bg-green-100 rounded transition"
      >
        Editar
      </button>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue'

defineProps({
  lead: {
    type: Object,
    required: true
  }
})

defineEmits(['dragstart', 'dragend', 'view', 'edit'])

const formatPhone = (phone) => {
  if (!phone) return ''
  const cleaned = phone.replace(/\D/g, '')
  if (cleaned.length === 13) {
    return `+${cleaned.slice(0, 2)} (${cleaned.slice(2, 4)}) ${cleaned.slice(4, 9)}-${cleaned.slice(9)}`
  }
  return phone
}

const formatMoney = (value) => {
  if (!value) return '0'
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

const formatDate = (date) => {
  if (!date) return ''
  const d = new Date(date)
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

const formatStatus = (status) => {
  const labels = {
    'novo': 'Novo',
    'em_atendimento': 'Atendimento',
    'qualificado': 'Qualificado',
    'convertido': 'Convertido'
  }
  return labels[status] || status
}

const getStatusClass = (status) => {
  const classes = {
    'novo': 'bg-blue-100 text-blue-700',
    'em_atendimento': 'bg-yellow-100 text-yellow-700',
    'qualificado': 'bg-green-100 text-green-700',
    'convertido': 'bg-purple-100 text-purple-700'
  }
  return classes[status] || 'bg-gray-100 text-gray-700'
}

const getStatusBorder = (status) => {
  const borders = {
    'novo': 'border-blue-400',
    'em_atendimento': 'border-yellow-400',
    'qualificado': 'border-green-400',
    'convertido': 'border-purple-400'
  }
  return borders[status] || 'border-gray-300'
}
</script>
