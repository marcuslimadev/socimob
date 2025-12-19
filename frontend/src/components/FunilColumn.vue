<template>
  <div class="flex-shrink-0 w-80">
    <!-- Header da Coluna do Funil -->
    <div 
      class="rounded-t-lg p-4 shadow-lg"
      :class="getHeaderColor(status.color)"
    >
      <div class="flex items-center justify-between">
        <div>
          <h3 class="font-bold text-white text-lg flex items-center gap-2">
            <span>{{ status.icon }}</span>
            <span>{{ status.label }}</span>
          </h3>
          <p class="text-white/80 text-sm mt-1">{{ leads.length }} lead{{ leads.length !== 1 ? 's' : '' }}</p>
        </div>
        <span class="bg-white/20 backdrop-blur-sm text-white font-bold text-lg px-3 py-1 rounded-full">
          {{ leads.length }}
        </span>
      </div>
    </div>

    <!-- Área de Drop -->
    <div
      class="bg-gray-100 rounded-b-lg p-4 min-h-[calc(100vh-300px)] max-h-[calc(100vh-300px)] overflow-y-auto"
      :class="{ 'bg-blue-50 ring-2 ring-blue-400': isDragOver }"
      @drop="handleDrop"
      @dragover.prevent="isDragOver = true"
      @dragleave="isDragOver = false"
    >
      <!-- Cards dos Leads -->
      <div class="space-y-3">
        <LeadCard
          v-for="lead in leads"
          :key="lead.id"
          :lead="lead"
          @dragstart="$emit('dragstart', lead)"
          @dragend="handleDragEnd"
          @view="$emit('view', lead)"
          @edit="$emit('edit', lead)"
        />
      </div>

      <!-- Empty State -->
      <div
        v-if="leads.length === 0"
        class="flex flex-col items-center justify-center py-12 text-gray-400"
      >
        <div class="text-5xl mb-3">{{ status.icon }}</div>
        <p class="text-sm font-medium">Nenhum lead nesta etapa</p>
        <p class="text-xs mt-1">Arraste cards para cá</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits } from 'vue'
import LeadCard from './LeadCard.vue'

defineProps({
  status: {
    type: Object,
    required: true
  },
  leads: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['dragstart', 'drop', 'view', 'edit'])

const isDragOver = ref(false)

const handleDrop = (event) => {
  event.preventDefault()
  isDragOver.value = false
  emit('drop', event)
}

const handleDragEnd = () => {
  isDragOver.value = false
}

const getHeaderColor = (color) => {
  const colors = {
    'blue': 'bg-gradient-to-r from-blue-500 to-blue-600',
    'yellow': 'bg-gradient-to-r from-yellow-500 to-yellow-600',
    'green': 'bg-gradient-to-r from-green-500 to-green-600',
    'purple': 'bg-gradient-to-r from-purple-500 to-purple-600',
    'emerald': 'bg-gradient-to-r from-emerald-500 to-emerald-600',
    'red': 'bg-gradient-to-r from-red-500 to-red-600'
  }
  return colors[color] || 'bg-gradient-to-r from-gray-500 to-gray-600'
}
</script>

<style scoped>
/* Custom scrollbar para área de leads */
.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
