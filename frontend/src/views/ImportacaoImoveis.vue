<template>
  <div class="min-h-screen bg-slate-50">
    <Navbar />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <header class="bg-slate-900 rounded-3xl p-8 text-white shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div>
            <p class="text-sm uppercase tracking-[0.3em] text-white/80 mb-2">Central de Importação</p>
            <h1 class="text-3xl md:text-4xl font-bold mb-2">Importação e Monitoramento de Imóveis</h1>
            <p class="text-base md:text-lg text-white/80 max-w-2xl">
              Gerencie a sincronização completa com os portais parceiros, acompanhe o status da fila e resolva pendências em um único lugar.
            </p>
          </div>
          <div class="bg-white/20 rounded-2xl px-6 py-4 min-w-[240px] text-center">
            <p class="text-sm uppercase tracking-widest text-white/70">Última importação</p>
            <p class="text-2xl font-bold">
              {{ overview.ultimaImportacao ? formatarData(overview.ultimaImportacao) : '—' }}
            </p>
            <p class="text-white/80 text-sm">Tempo médio: {{ overview.tempoMedio }} min</p>
          </div>
        </div>
      </header>

      <transition name="fade">
        <div
          v-if="feedback"
          class="mt-6 bg-white border border-emerald-200 text-emerald-700 px-5 py-3 rounded-2xl shadow-sm flex items-center gap-3"
        >
          <span>✅</span>
          <p class="text-sm font-medium">{{ feedback }}</p>
        </div>
      </transition>

      <!-- Cards Overview -->
      <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mt-8">
        <div
          v-for="card in overviewCards"
          :key="card.label"
          class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-4"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-slate-500">{{ card.label }}</p>
              <p class="text-3xl font-bold text-slate-900">{{ card.value }}</p>
            </div>
            <span :class="['text-2xl', card.iconColor]">{{ card.icon }}</span>
          </div>
          <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
            <div
              class="h-2 rounded-full"
              :class="card.barColor"
              :style="{ width: `${card.progress}%` }"
            ></div>
          </div>
          <p class="text-xs text-slate-500 flex items-center gap-2">
            <span :class="card.chipColor" class="px-2 py-0.5 rounded-full text-[11px] font-semibold">{{ card.chip }}</span>
            {{ card.description }}
          </p>
        </div>
      </section>

      <!-- Forms -->
      <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-10">
        <div class="bg-white rounded-3xl border border-slate-100 shadow-lg p-6">
          <div class="flex items-center justify-between mb-6">
            <div>
              <p class="text-sm uppercase tracking-[0.4em] text-slate-400">Importação geral</p>
              <h2 class="text-2xl font-semibold text-slate-900">Imóveis e disponibilidade</h2>
            </div>
            <span class="text-3xl text-indigo-500">📦</span>
          </div>

          <div class="space-y-4">
            <label class="block text-sm font-medium text-slate-600">Origem dos dados</label>
            <select
              v-model="formularioBasico.origem"
              class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="imoapp">IMO App (API oficial)</option>
              <option value="zap">ZAP+</option>
              <option value="vivareal">VivaReal</option>
              <option value="crm">CRM Exclusiva Lar</option>
            </select>

            <label class="block text-sm font-medium text-slate-600 mt-4">Período</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <input
                type="date"
                v-model="formularioBasico.periodoInicial"
                class="rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
              <input
                type="date"
                v-model="formularioBasico.periodoFinal"
                class="rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
              <label class="flex items-center gap-3 text-sm text-slate-600">
                <input type="checkbox" v-model="formularioBasico.incluirDetalhes" class="rounded text-indigo-600" />
                Importar detalhes completos
              </label>
              <label class="flex items-center gap-3 text-sm text-slate-600">
                <input type="checkbox" v-model="formularioBasico.processarMidia" class="rounded text-indigo-600" />
                Processar mídias e plantas
              </label>
            </div>

            <label class="flex items-center gap-3 text-sm text-slate-600">
              <input type="checkbox" v-model="formularioBasico.atualizarExistentes" class="rounded text-indigo-600" />
              Atualizar imóveis que já existem no CRM
            </label>

            <button
              class="w-full mt-6 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-2xl shadow-lg transition disabled:opacity-60"
              :disabled="importing"
              @click="importacaoBasica"
            >
              <span v-if="importing" class="flex items-center justify-center gap-2">
                <span class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></span>
                Agendando importação...
              </span>
              <span v-else>Iniciar importação completa</span>
            </button>
          </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-100 shadow-lg p-6">
          <div class="flex items-center justify-between mb-6">
            <div>
              <p class="text-sm uppercase tracking-[0.4em] text-slate-400">Refinamento</p>
              <h2 class="text-2xl font-semibold text-slate-900">Detalhes e conteúdo</h2>
            </div>
            <span class="text-3xl text-emerald-500">🛠️</span>
          </div>

          <div class="space-y-4">
            <label class="block text-sm font-medium text-slate-600">Prioridade</label>
            <select
              v-model="formularioDetalhes.prioridade"
              class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
              <option value="todos">Todos os imóveis pendentes</option>
              <option value="novos">Apenas novos importados</option>
              <option value="desatualizados">Somente desatualizados</option>
            </select>

            <label class="flex items-center gap-3 text-sm text-slate-600">
              <input type="checkbox" v-model="formularioDetalhes.atualizarFotos" class="rounded text-emerald-600" />
              Atualizar fotos e mídias 360º
            </label>
            <label class="flex items-center gap-3 text-sm text-slate-600">
              <input type="checkbox" v-model="formularioDetalhes.reprocessarTour360" class="rounded text-emerald-600" />
              Reprocessar tours e vídeos
            </label>

            <textarea
              v-model="formularioDetalhes.observacoes"
              rows="4"
              placeholder="Observações ou instruções para a importação de detalhes"
              class="w-full rounded-2xl border border-slate-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            ></textarea>

            <button
              class="w-full mt-6 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 rounded-2xl shadow-lg transition disabled:opacity-60"
              :disabled="importing"
              @click="importacaoDetalhes"
            >
              <span v-if="importing" class="flex items-center justify-center gap-2">
                <span class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></span>
                Enviando requisição...
              </span>
              <span v-else>Atualizar detalhes e mídias</span>
            </button>
          </div>
        </div>
      </section>

      <!-- Monitoramento -->
      <section class="mt-12 grid grid-cols-1 xl:grid-cols-3 gap-8">
        <div class="xl:col-span-2 bg-white rounded-3xl border border-slate-100 shadow-lg p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold text-slate-900">Monitoramento em tempo real</h3>
            <span class="text-sm text-slate-500">Atualiza automaticamente</span>
          </div>
          <div class="space-y-4">
            <div
              v-for="tarefa in tarefasAtivas"
              :key="tarefa.id"
              class="border border-slate-100 rounded-2xl p-4 hover:border-indigo-200 transition"
            >
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-semibold text-slate-900">{{ tarefa.titulo }}</p>
                  <p class="text-sm text-slate-500">{{ tarefa.descricao }}</p>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-indigo-50 text-indigo-600">{{ tarefa.prioridade }}</span>
              </div>
              <div class="mt-4">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                  <span>Progresso</span>
                  <span>{{ tarefa.progresso }}%</span>
                </div>
                <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-2 bg-indigo-600 rounded-full" :style="{ width: `${tarefa.progresso}%` }"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-100 shadow-lg p-6">
          <h3 class="text-xl font-semibold text-slate-900 mb-4">Logs recentes</h3>
          <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
            <p
              v-for="log in logs"
              :key="log.horario + log.mensagem"
              class="text-sm text-slate-600 bg-slate-50 rounded-2xl px-4 py-3"
            >
              <span class="text-xs text-slate-400 block">{{ log.horario }}</span>
              {{ log.mensagem }}
            </p>
          </div>
        </div>
      </section>

      <!-- Fila e histórico -->
      <section class="mt-12 grid grid-cols-1 xl:grid-cols-2 gap-8">
        <div class="bg-white rounded-3xl border border-slate-100 shadow-lg p-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
              <h3 class="text-xl font-semibold text-slate-900">Fila e pendências</h3>
              <p class="text-sm text-slate-500">Itens aguardando correções ou reprocessamento.</p>
            </div>
            <div class="flex gap-3">
              <select v-model="filtros.status" class="rounded-2xl border border-slate-200 px-3 py-2 text-sm">
                <option value="todos">Todos os status</option>
                <option value="aguardando">Aguardando</option>
                <option value="processando">Processando</option>
              </select>
              <select v-model="filtros.origem" class="rounded-2xl border border-slate-200 px-3 py-2 text-sm">
                <option value="todas">Todas as origens</option>
                <option value="ZAP">ZAP</option>
                <option value="VivaReal">VivaReal</option>
                <option value="CRM">CRM</option>
              </select>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="text-left text-xs font-semibold text-slate-500 uppercase">
                  <th class="py-3">Imóvel</th>
                  <th class="py-3">Origem</th>
                  <th class="py-3">Pendência</th>
                  <th class="py-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in filaFiltrada"
                  :key="item.codigo"
                  class="border-t border-slate-100 text-sm text-slate-700"
                >
                  <td class="py-3 font-semibold">{{ item.codigo }}</td>
                  <td class="py-3">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-slate-100">{{ item.origem }}</span>
                  </td>
                  <td class="py-3">
                    <p class="text-xs text-slate-500">{{ item.pendencia }}</p>
                    <span
                      class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                      :class="item.status === 'aguardando' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700'"
                    >
                      {{ item.status === 'aguardando' ? 'Aguardando' : 'Processando' }}
                    </span>
                  </td>
                  <td class="py-3 text-right">
                    <button
                      class="text-indigo-600 hover:text-indigo-800 text-sm font-semibold"
                      @click="sincronizar(item.codigo)"
                    >
                      Reprocessar
                    </button>
                  </td>
                </tr>
                <tr v-if="filaFiltrada.length === 0">
                  <td colspan="4" class="text-center py-6 text-slate-500 text-sm">Nenhum item encontrado.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-100 shadow-lg p-6">
          <h3 class="text-xl font-semibold text-slate-900 mb-4">Histórico de importações</h3>
          <div class="space-y-4 max-h-[360px] overflow-y-auto pr-2">
            <div
              v-for="registro in historico"
              :key="registro.id"
              class="border border-slate-100 rounded-2xl p-4"
            >
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-semibold text-slate-900">{{ registro.tipo }}</p>
                  <p class="text-xs text-slate-500">{{ formatarData(registro.inicio) }}</p>
                </div>
                <span class="text-sm font-semibold text-slate-600">{{ registro.quantidade }} imóveis</span>
              </div>
              <div class="flex items-center justify-between mt-2 text-xs text-slate-500">
                <span>Responsável: {{ registro.responsavel }}</span>
                <span :class="registro.status === 'Concluído' ? 'text-emerald-500' : 'text-amber-500'">{{ registro.status }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Gerenciamento manual -->
      <section class="mt-12">
        <div class="bg-white rounded-3xl border border-slate-100 shadow-lg p-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
              <h3 class="text-xl font-semibold text-slate-900">Imóveis sob atenção</h3>
              <p class="text-sm text-slate-500">Use ações rápidas para garantir que as informações fiquem completas.</p>
            </div>
            <div class="text-sm text-slate-500">
              Atualizado automaticamente a cada 45 segundos
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <article
              v-for="imovel in imoveisAtencao"
              :key="imovel.codigo"
              class="border border-slate-100 rounded-2xl p-4 hover:border-indigo-200 transition"
            >
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm text-slate-400">Código</p>
                  <p class="text-lg font-semibold text-slate-900">{{ imovel.codigo }}</p>
                </div>
                <span class="text-sm font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-700">{{ imovel.corretor }}</span>
              </div>
              <p class="text-sm text-slate-600 mt-2">{{ imovel.titulo }}</p>
              <p class="text-xs text-slate-400 mt-1">Atualizado em {{ formatarData(imovel.atualizadoEm) }}</p>
              <div class="flex items-center justify-between mt-4">
                <span class="text-xs font-semibold px-3 py-1 rounded-full bg-amber-100 text-amber-700">{{ imovel.status }}</span>
                <button
                  class="text-indigo-600 text-sm font-semibold hover:text-indigo-800"
                  @click="sincronizar(imovel.codigo)"
                >
                  Sincronizar agora
                </button>
              </div>
            </article>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue'
import Navbar from '../components/Navbar.vue'
import { useImportacaoStore } from '../stores/importacao'

const importacaoStore = useImportacaoStore()

const overview = computed(() => importacaoStore.overview)
const tarefasAtivas = computed(() => importacaoStore.tarefasAtivas)
const historico = computed(() => importacaoStore.historico)
const filaPendencias = computed(() => importacaoStore.filaPendencias)
const logs = computed(() => importacaoStore.logs)
const imoveisAtencao = computed(() => importacaoStore.imoveisAtencao)
const importing = computed(() => importacaoStore.importing)

const filtros = reactive({
  status: 'todos',
  origem: 'todas'
})

const formularioBasico = reactive({
  origem: 'imoapp',
  periodoInicial: new Date().toISOString().slice(0, 10),
  periodoFinal: new Date().toISOString().slice(0, 10),
  incluirDetalhes: true,
  processarMidia: true,
  atualizarExistentes: true
})

const formularioDetalhes = reactive({
  prioridade: 'todos',
  atualizarFotos: true,
  reprocessarTour360: false,
  observacoes: ''
})

const overviewCards = computed(() => [
  {
    label: 'Total de imóveis',
    value: overview.value.totalImoveis,
    icon: '🏢',
    iconColor: 'text-indigo-500',
    progress: overview.value.progresso,
    barColor: 'bg-indigo-500',
    chip: 'Base',
    chipColor: 'bg-indigo-50 text-indigo-600',
    description: 'Inventário sincronizado'
  },
  {
    label: 'Ativos publicados',
    value: overview.value.ativos,
    icon: '🚀',
    iconColor: 'text-emerald-500',
    progress: overview.value.totalImoveis ? Math.round((overview.value.ativos / overview.value.totalImoveis) * 100) : 0,
    barColor: 'bg-emerald-500',
    chip: 'Publicados',
    chipColor: 'bg-emerald-50 text-emerald-600',
    description: 'Disponíveis nos portais'
  },
  {
    label: 'Desatualizados',
    value: overview.value.desatualizados,
    icon: '⏱️',
    iconColor: 'text-amber-500',
    progress: overview.value.totalImoveis ? Math.round((overview.value.desatualizados / overview.value.totalImoveis) * 100) : 0,
    barColor: 'bg-amber-500',
    chip: 'Pendentes',
    chipColor: 'bg-amber-50 text-amber-600',
    description: 'Precisam de revisão'
  },
  {
    label: 'Detalhes pendentes',
    value: overview.value.aguardandoDetalhes,
    icon: '🧩',
    iconColor: 'text-purple-500',
    progress: overview.value.totalImoveis ? Math.round((overview.value.aguardandoDetalhes / overview.value.totalImoveis) * 100) : 0,
    barColor: 'bg-purple-500',
    chip: 'Conteúdo',
    chipColor: 'bg-purple-50 text-purple-600',
    description: 'Sem detalhes completos'
  }
])

const filaFiltrada = computed(() => {
  return filaPendencias.value.filter(item => {
    const statusValido = filtros.status === 'todos' || item.status === filtros.status
    const origemValida = filtros.origem === 'todas' || item.origem === filtros.origem
    return statusValido && origemValida
  })
})

const feedback = ref('')

const importacaoBasica = async () => {
  await importacaoStore.iniciarImportacaoBasica({ ...formularioBasico })
  feedback.value = 'Importação completa enviada para processamento.'
  setTimeout(() => (feedback.value = ''), 4000)
}

const importacaoDetalhes = async () => {
  await importacaoStore.iniciarImportacaoDetalhes({ ...formularioDetalhes })
  feedback.value = 'Atualização de detalhes adicionada à fila.'
  setTimeout(() => (feedback.value = ''), 4000)
}

const sincronizar = (codigo) => {
  importacaoStore.sincronizarImovel(codigo)
}

const formatarData = (data) => {
  if (!data) return '—'
  return new Date(data).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(async () => {
  await importacaoStore.refreshAll()
  importacaoStore.startAutoRefresh()
})

onBeforeUnmount(() => {
  importacaoStore.stopAutoRefresh()
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
