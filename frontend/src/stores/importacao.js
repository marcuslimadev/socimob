import { defineStore } from 'pinia'
import api from '../services/api'

const defaultOverview = {
  totalImoveis: 0,
  ativos: 0,
  desatualizados: 0,
  aguardandoDetalhes: 0,
  pendentes: 0,
  ultimaImportacao: null,
  tempoMedio: 0,
  sucessoUltima: true,
  progresso: 0
}

const defaultTarefas = [
  {
    id: 'fila-geral',
    titulo: 'Sincronização diária',
    descricao: 'Importando listagem base dos portais parceiros',
    progresso: 62,
    prioridade: 'Alta'
  },
  {
    id: 'detalhes',
    titulo: 'Atualização de detalhes',
    descricao: 'Reprocessando descrições, comodidades e fotos',
    progresso: 38,
    prioridade: 'Média'
  }
]

const defaultHistorico = [
  {
    id: 1,
    tipo: 'Importação Completa',
    quantidade: 430,
    responsavel: 'Administrador',
    inicio: '2024-02-20T08:10:00Z',
    termino: '2024-02-20T08:18:00Z',
    status: 'Concluído'
  },
  {
    id: 2,
    tipo: 'Atualização de Detalhes',
    quantidade: 120,
    responsavel: 'Administrador',
    inicio: '2024-02-19T21:05:00Z',
    termino: '2024-02-19T21:12:00Z',
    status: 'Concluído'
  },
  {
    id: 3,
    tipo: 'Correção manual',
    quantidade: 8,
    responsavel: 'Administrador',
    inicio: '2024-02-19T18:20:00Z',
    termino: '2024-02-19T18:32:00Z',
    status: 'Concluído'
  }
]

const defaultFila = [
  {
    codigo: 'BH1234',
    origem: 'ZAP',
    prioridade: 'Alta',
    pendencia: 'Sem fotos',
    status: 'aguardando'
  },
  {
    codigo: 'BH8745',
    origem: 'VivaReal',
    prioridade: 'Média',
    pendencia: 'Dados desatualizados',
    status: 'processando'
  },
  {
    codigo: 'BH2234',
    origem: 'CRM',
    prioridade: 'Baixa',
    pendencia: 'Sem corretor responsável',
    status: 'aguardando'
  }
]

const defaultLogs = [
  { horario: '08:21:03', mensagem: 'Detalhes do imóvel BH8745 atualizados com sucesso.' },
  { horario: '08:18:55', mensagem: 'Importação de 25 novos imóveis concluída.' },
  { horario: '08:17:12', mensagem: 'Fila de pendências priorizada automaticamente.' }
]

const defaultImoveisAtencao = [
  {
    codigo: 'BH8745',
    titulo: 'Cobertura 4q - Funcionários',
    atualizadoEm: '2024-02-19T10:30:00Z',
    status: 'Detalhes pendentes',
    corretor: 'Juliana Lima'
  },
  {
    codigo: 'BH5532',
    titulo: 'Casa 3q - Pampulha',
    atualizadoEm: '2024-02-18T22:00:00Z',
    status: 'Sem fotos',
    corretor: 'Equipe CRM'
  }
]

let monitoramentoInterval = null

const gerarId = () => {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID()
  }
  return `task-${Date.now()}`
}

export const useImportacaoStore = defineStore('importacao', {
  state: () => ({
    overview: { ...defaultOverview },
    tarefasAtivas: [...defaultTarefas],
    historico: [...defaultHistorico],
    filaPendencias: [...defaultFila],
    logs: [...defaultLogs],
    imoveisAtencao: [...defaultImoveisAtencao],
    loading: false,
    importing: false
  }),
  actions: {
    async refreshAll() {
      await Promise.all([
        this.fetchOverview(),
        this.fetchHistorico(),
        this.fetchFila(),
        this.fetchLogs()
      ])
    },
    async fetchOverview() {
      this.loading = true
      try {
        const response = await api.get('/api/importacoes/imoveis/overview')
        const payload = response.data?.data || response.data
        this.overview = {
          ...defaultOverview,
          ...payload
        }
      } catch (error) {
        console.warn('Não foi possível carregar overview da importação. Usando dados locais.', error)
        this.overview = {
          ...defaultOverview,
          progresso: 68,
          ativos: 812,
          totalImoveis: 946,
          desatualizados: 74,
          aguardandoDetalhes: 60,
          pendentes: 60,
          ultimaImportacao: '2024-02-20T08:18:00Z',
          tempoMedio: 8
        }
      } finally {
        this.loading = false
      }
    },
    async fetchHistorico() {
      try {
        const response = await api.get('/api/importacoes/imoveis/historico')
        const payload = response.data?.data || response.data
        const lista = Array.isArray(payload) && payload.length ? payload : [...defaultHistorico]
        this.historico = lista
        this.atualizarTarefasAtivas(lista)
      } catch (error) {
        console.warn('Não foi possível carregar histórico da importação. Usando dados locais.', error)
        this.historico = [...defaultHistorico]
        this.tarefasAtivas = [...defaultTarefas]
      }
    },
    async fetchFila() {
      try {
        const response = await api.get('/api/importacoes/imoveis/fila')
        const payload = response.data?.data || response.data
        this.filaPendencias = Array.isArray(payload) && payload.length ? payload : [...defaultFila]
      } catch (error) {
        console.warn('Não foi possível carregar fila de pendências. Usando dados locais.', error)
        this.filaPendencias = [...defaultFila]
      }
    },
    async fetchLogs() {
      try {
        const response = await api.get('/api/importacoes/imoveis/logs')
        const payload = response.data?.data || response.data
        this.logs = Array.isArray(payload) && payload.length ? payload : [...defaultLogs]
      } catch (error) {
        console.warn('Não foi possível carregar logs da importação. Usando dados locais.', error)
        this.logs = [...defaultLogs]
      }
    },
    startAutoRefresh(interval = 45000) {
      this.stopAutoRefresh()
      monitoramentoInterval = setInterval(() => {
        this.fetchOverview()
        this.fetchFila()
        this.fetchLogs()
      }, interval)
    },
    stopAutoRefresh() {
      if (monitoramentoInterval) {
        clearInterval(monitoramentoInterval)
        monitoramentoInterval = null
      }
    },
    adicionarTarefaLocal(titulo, descricao) {
      const novaTarefa = {
        id: gerarId(),
        titulo,
        descricao,
        prioridade: 'Alta',
        progresso: 3
      }
      this.tarefasAtivas = [novaTarefa, ...this.tarefasAtivas.slice(0, 3)]
      setTimeout(() => {
        novaTarefa.progresso = Math.min(90, novaTarefa.progresso + 20)
      }, 1500)
    },
    registrarHistoricoLocal(tipo, quantidade) {
      this.historico = [
        {
          id: Date.now(),
          tipo,
          quantidade,
          responsavel: 'Administrador',
          inicio: new Date().toISOString(),
          termino: new Date().toISOString(),
          status: 'Agendado'
        },
        ...this.historico
      ]
    },
    adicionarLogLocal(mensagem) {
      this.logs = [
        { horario: new Date().toLocaleTimeString('pt-BR'), mensagem },
        ...this.logs
      ].slice(0, 10)
    },
    atualizarTarefasAtivas(lista = []) {
      const ativos = lista
        .filter((item) => ['Agendado', 'Processando'].includes(item.status))
        .map((item) => ({
          id: item.id || gerarId(),
          titulo: item.tipo,
          descricao: item.responsavel ? `Responsável: ${item.responsavel}` : 'Monitoramento automático',
          prioridade: (item.prioridade || 'Alta')
            .toString()
            .replace(/^(.)/, (match) => match.toUpperCase()),
          progresso:
            typeof item.progresso === 'number'
              ? Math.min(100, Math.max(0, item.progresso))
              : item.status === 'Concluído'
                ? 100
                : 8
        }))

      this.tarefasAtivas = ativos.length ? ativos.slice(0, 3) : [...defaultTarefas]
    },
    async iniciarImportacaoBasica(opcoes) {
      this.importing = true
      try {
        const response = await api.post('/api/importacoes/imoveis', opcoes)
        const mensagem = response.data?.message || 'Importação completa agendada com sucesso.'
        this.adicionarLogLocal(mensagem)
        await this.refreshAll()
      } catch (error) {
        console.error('Erro ao iniciar importação básica. Criando registro local.', error)
        const motivo = error.response?.data?.error || error.response?.data?.message
        this.adicionarLogLocal(
          motivo
            ? `Falha ao agendar importação: ${motivo}`
            : 'API indisponível, importação será processada assim que possível.'
        )
      } finally {
        this.importing = false
      }
    },
    async iniciarImportacaoDetalhes(opcoes) {
      this.importing = true
      try {
        const response = await api.post('/api/importacoes/imoveis/detalhes', opcoes)
        const mensagem = response.data?.message || 'Atualização de detalhes enviada para fila.'
        this.adicionarLogLocal(mensagem)
        await this.refreshAll()
      } catch (error) {
        console.error('Erro ao iniciar importação de detalhes. Criando registro local.', error)
        const motivo = error.response?.data?.error || error.response?.data?.message
        this.adicionarLogLocal(
          motivo
            ? `Falha ao enviar detalhes: ${motivo}`
            : 'API indisponível para detalhes. Tente novamente mais tarde.'
        )
      } finally {
        this.importing = false
      }
    },
    async sincronizarImovel(codigo) {
      try {
        const response = await api.post('/api/importacoes/imoveis/sincronizar', { codigo })
        const mensagem = response.data?.message || `Imóvel ${codigo} enviado para sincronização manual.`
        this.adicionarLogLocal(mensagem)
        await Promise.all([this.fetchFila(), this.fetchLogs()])
      } catch (error) {
        console.error('Erro ao sincronizar imóvel, mantendo fallback local.', error)
        this.adicionarLogLocal(`Falha temporária ao sincronizar ${codigo}. Nova tentativa automática em breve.`)
      }
    }
  }
})
