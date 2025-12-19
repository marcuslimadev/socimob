<template>
  <div class="flex-shrink-0 w-80">
    <!-- Header da Coluna -->
    <div 
      class="rounded-t-lg p-4 shadow-lg"
      :class="state === 'SEM_ESTADO' ? 'bg-gradient-to-r from-gray-500 to-gray-600' : 'bg-gradient-to-r from-blue-500 to-blue-600'"
    >
      <div class="flex items-center justify-between">
        <div>
          <h3 class="font-bold text-white text-lg">
            {{ state === 'SEM_ESTADO' ? '📍 Sem Estado Definido' : state }}
          </h3>
          <p class="text-blue-100 text-sm">{{ leads.length }} lead{{ leads.length !== 1 ? 's' : '' }}</p>
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
        <svg class="w-16 h-16 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <p class="text-sm font-medium">Nenhum lead neste estado</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits } from 'vue'
import LeadCard from './LeadCard.vue'

defineProps({
  state: {
    type: String,
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
