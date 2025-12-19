import { defineStore } from 'pinia'
import api from '../services/api'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    stats: {
      totalLeads: 0,
      conversasAtivas: 0,
      leadsHoje: 0,
      taxaConversao: 0
    },
    atividadesRecentes: [],
    loading: false
  }),

  actions: {
    async fetchStats() {
      this.loading = true
      try {
        const response = await api.get('/api/dashboard/stats')
        const data = response.data.data || response.data
        this.stats = {
          totalLeads: data.leads?.total || 0,
          conversasAtivas: data.conversas?.ativas || 0,
          leadsHoje: data.conversas?.hoje || 0,
          taxaConversao: 0
        }
      } catch (error) {
        console.error('Erro ao buscar estatísticas', error)
      } finally {
        this.loading = false
      }
    },

    async fetchAtividades() {
      try {
        const response = await api.get('/api/dashboard/atividades')
        this.atividadesRecentes = response.data.data || response.data
      } catch (error) {
        console.error('Erro ao buscar atividades', error)
      }
    }
  }
})
