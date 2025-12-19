<template>
  <div class="min-h-screen bg-gray-50">
    <Navbar />
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Conversas WhatsApp</h1>
        <p class="text-gray-600 mt-1">Gerencie os atendimentos em tempo real</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Lista de Conversas -->
        <div class="lg:col-span-1 bg-white rounded-lg shadow">
          <div class="p-4 border-b bg-gradient-to-r from-green-600 to-green-700">
            <h2 class="font-semibold text-white flex items-center">
              <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
              </svg>
              Conversas Ativas
            </h2>
            <p class="text-green-100 text-sm mt-1">{{ conversas.length }} conversas</p>
          </div>
          
          <div v-if="loading" class="p-8 text-center">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600 mx-auto"></div>
            <p class="text-gray-600 mt-3 text-sm">Carregando...</p>
          </div>
          
          <div v-else-if="conversas.length === 0" class="p-8 text-center text-gray-500">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <p class="font-medium">Nenhuma conversa ativa</p>
            <p class="text-sm mt-1">As conversas aparecerão aqui</p>
          </div>
          
          <div v-else class="overflow-y-auto" style="max-height: calc(100vh - 300px)">
            <div
              v-for="conversa in conversas"
              :key="conversa.id"
              @click="selecionarConversa(conversa.id)"
              class="p-4 border-b hover:bg-gray-50 cursor-pointer transition"
              :class="{ 'bg-green-50 border-l-4 border-l-green-600': conversaAtiva?.id === conversa.id }"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center space-x-2">
                  <div class="bg-green-100 p-2 rounded-full">
                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                    </svg>
                  </div>
                  <span class="font-medium text-gray-900">{{ conversa.lead_nome || conversa.telefone }}</span>
                </div>
                <span class="text-xs text-gray-500">{{ formatarHora(conversa.ultima_atividade || conversa.updated_at) }}</span>
              </div>
              <p class="text-sm text-gray-600 truncate ml-10">{{ conversa.ultima_mensagem || 'Nova conversa' }}</p>
              <div class="flex items-center justify-between mt-2 ml-10">
                <span class="text-xs px-2 py-1 rounded-full" :class="getStageClass(conversa.stage)">
                  {{ formatStage(conversa.stage) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Área de Chat -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow flex flex-col" style="height: calc(100vh - 200px)">
          <div v-if="!conversaAtiva" class="flex-1 flex flex-col items-center justify-center text-gray-400">
            <svg class="w-24 h-24 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <p class="text-lg font-medium">Selecione uma conversa</p>
            <p class="text-sm">Escolha uma conversa à esquerda para começar</p>
          </div>

          <template v-else>
            <!-- Header da Conversa -->
            <div class="p-4 border-b bg-gradient-to-r from-green-600 to-green-700 flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="bg-white/20 p-2 rounded-full">
                  <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold text-white">{{ conversaAtiva.lead_nome || conversaAtiva.telefone }}</h3>
                  <p class="text-sm text-green-100">{{ formatStage(conversaAtiva.stage) }}</p>
                </div>
              </div>
              <button
                @click="conversasStore.conversaAtiva = null"
                class="text-white hover:bg-white/20 rounded-full p-2 transition lg:hidden"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Mensagens -->
            <div v-if="loadingMensagens" class="flex-1 flex items-center justify-center">
              <div class="text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mx-auto"></div>
                <p class="text-gray-600 mt-3">Carregando mensagens...</p>
              </div>
            </div>

            <div v-else class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50" ref="mensagensContainer">
              <div v-if="mensagens.length === 0" class="text-center py-12 text-gray-400">
                <svg class="w-16 h-16 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                <p>Nenhuma mensagem ainda</p>
              </div>

              <div
                v-for="mensagem in mensagens"
                :key="mensagem.id"
                class="flex"
                :class="mensagem.direction === 'outgoing' ? 'justify-end' : 'justify-start'"
              >
                <div
                  class="max-w-xs lg:max-w-md px-4 py-3 rounded-lg shadow-sm"
                  :class="mensagem.direction === 'outgoing' 
                    ? 'bg-green-600 text-white rounded-br-none' 
                    : 'bg-white text-gray-900 rounded-bl-none border border-gray-200'"
                >
                  <!-- Ícone de áudio se for message_type audio -->
                  <div v-if="mensagem.message_type === 'audio'" class="flex items-center space-x-2 mb-2 pb-2 border-b" :class="mensagem.direction === 'outgoing' ? 'border-green-400' : 'border-gray-300'">
                    <svg class="w-5 h-5" :class="mensagem.direction === 'outgoing' ? 'text-green-100' : 'text-blue-500'" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-xs font-semibold" :class="mensagem.direction === 'outgoing' ? 'text-green-100' : 'text-blue-600'">
                      🎤 Áudio Transcrito
                    </span>
                  </div>
                  
                  <!-- Transcrição do áudio se existir -->
                  <div v-if="mensagem.transcription" class="mb-2 p-2 rounded" :class="mensagem.direction === 'outgoing' ? 'bg-green-700 bg-opacity-50' : 'bg-blue-50'">
                    <p class="text-xs font-medium mb-1" :class="mensagem.direction === 'outgoing' ? 'text-green-100' : 'text-gray-600'">
                      Transcrição:
                    </p>
                    <p class="text-sm italic" :class="mensagem.direction === 'outgoing' ? 'text-white' : 'text-gray-800'">
                      "{{ mensagem.transcription }}"
                    </p>
                  </div>
                  
                  <!-- Conteúdo da mensagem -->
                  <p class="text-sm whitespace-pre-wrap break-words">{{ mensagem.content }}</p>
                  
                  <div class="flex items-center justify-end mt-1 space-x-1">
                    <span class="text-xs opacity-75">
                      {{ formatarHora(mensagem.sent_at) }}
                    </span>
                    <svg v-if="mensagem.direction === 'outgoing'" class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                  </div>
                </div>
              </div>
            </div>

            <!-- Input de Mensagem -->
            <div class="p-4 border-t bg-white">
              <form @submit.prevent="enviarMensagem" class="flex space-x-2">
                <input
                  v-model="novaMensagem"
                  type="text"
                  placeholder="Digite sua mensagem..."
                  class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                  :disabled="enviando"
                />
                <button
                  type="submit"
                  :disabled="!novaMensagem.trim() || enviando"
                  class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center font-medium"
                >
                  <svg v-if="enviando" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ enviando ? 'Enviando...' : 'Enviar' }}
                </button>
              </form>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useConversasStore } from '../stores/conversas'
import Navbar from '../components/Navbar.vue'

const conversasStore = useConversasStore()
const novaMensagem = ref('')
const mensagensContainer = ref(null)
const enviando = ref(false)

const conversas = computed(() => conversasStore.conversas)
const conversaAtiva = computed(() => conversasStore.conversaAtiva)
const mensagens = computed(() => conversasStore.mensagens)
const loading = computed(() => conversasStore.loading)
const loadingMensagens = ref(false)

let conversasInterval = null

onMounted(() => {
  conversasStore.fetchConversas()
  
  // Atualizar conversas a cada 30 segundos (tempo real)
  conversasInterval = setInterval(() => {
    conversasStore.fetchConversas()
    if (conversaAtiva.value) {
      conversasStore.selecionarConversa(conversaAtiva.value.id)
    }
  }, 30000)
})

onUnmounted(() => {
  if (conversasInterval) {
    clearInterval(conversasInterval)
  }
})

const selecionarConversa = async (id) => {
  loadingMensagens.value = true
  try {
    await conversasStore.selecionarConversa(id)
    await nextTick()
    scrollToBottom()
  } finally {
    loadingMensagens.value = false
  }
}

const enviarMensagem = async () => {
  if (!novaMensagem.value.trim() || enviando.value) return
  
  enviando.value = true
  try {
    const success = await conversasStore.enviarMensagem(
      conversaAtiva.value.id,
      novaMensagem.value
    )
    
    if (success) {
      novaMensagem.value = ''
      await nextTick()
      scrollToBottom()
    } else {
      alert('Erro ao enviar mensagem. Tente novamente.')
    }
  } finally {
    enviando.value = false
  }
}

const scrollToBottom = () => {
  if (mensagensContainer.value) {
    setTimeout(() => {
      mensagensContainer.value.scrollTop = mensagensContainer.value.scrollHeight
    }, 100)
  }
}

const formatarHora = (data) => {
  if (!data) return ''
  return new Date(data).toLocaleTimeString('pt-BR', { 
    hour: '2-digit', 
    minute: '2-digit' 
  })
}

const formatStage = (stage) => {
  const stages = {
    'boas_vindas': 'Boas-vindas',
    'coleta_inicial': 'Coleta de Dados',
    'orcamento': 'Definindo Orçamento',
    'localizacao': 'Localização',
    'preferencias': 'Preferências',
    'busca_imoveis': 'Buscando Imóveis',
    'apresentacao_imoveis': 'Apresentação',
    'interesse': 'Interesse',
    'agendamento': 'Agendamento',
    'finalizado': 'Finalizado'
  }
  return stages[stage] || stage || 'Aguardando'
}

const getStageClass = (stage) => {
  const classes = {
    'boas_vindas': 'bg-blue-100 text-blue-700',
    'coleta_inicial': 'bg-purple-100 text-purple-700',
    'orcamento': 'bg-yellow-100 text-yellow-700',
    'localizacao': 'bg-orange-100 text-orange-700',
    'preferencias': 'bg-pink-100 text-pink-700',
    'busca_imoveis': 'bg-indigo-100 text-indigo-700',
    'apresentacao_imoveis': 'bg-cyan-100 text-cyan-700',
    'interesse': 'bg-green-100 text-green-700',
    'agendamento': 'bg-teal-100 text-teal-700',
    'finalizado': 'bg-gray-100 text-gray-700'
  }
  return classes[stage] || 'bg-gray-100 text-gray-700'
}
</script>
