import { defineStore } from 'pinia'
import api from '../services/api'

export const useLeadsStore = defineStore('leads', {
  state: () => ({
    leads: [],
    loading: false,
    filtros: {
      status: 'todos',
      corretor: null
    }
  }),

  getters: {
    leadsFiltrados: (state) => {
      let filtered = state.leads
      
      if (state.filtros.status !== 'todos') {
        filtered = filtered.filter(lead => lead.status === state.filtros.status)
      }
      
      if (state.filtros.corretor) {
        filtered = filtered.filter(lead => lead.corretor_id === state.filtros.corretor)
      }
      
      return filtered
    }
  },

  actions: {
    async fetchLeads() {
      this.loading = true
      try {
        const response = await api.get('/api/leads')
        this.leads = response.data.data || response.data
      } catch (error) {
        console.error('Erro ao buscar leads', error)
      } finally {
        this.loading = false
      }
    },

    async atualizarLead(id, dados) {
      try {
        const response = await api.put(`/api/leads/${id}`, dados)
        const leadAtualizado = response.data.data || response.data
        
        // Atualizar na lista
        const index = this.leads.findIndex(l => l.id === id)
        if (index !== -1) {
          this.leads[index] = leadAtualizado
        }
        
        return true
      } catch (error) {
        console.error('Erro ao atualizar lead', error)
        return false
      }
    },

    async updateLeadState(id, newState) {
      try {
        const response = await api.patch(`/api/leads/${id}/state`, {
          state: newState
        })
        
        const leadAtualizado = response.data.data || response.data
        
        // Atualizar na lista local
        const index = this.leads.findIndex(l => l.id === id)
        if (index !== -1) {
          this.leads[index] = { ...this.leads[index], state: newState }
        }
        
        return true
      } catch (error) {
        console.error('❌ Erro ao atualizar estado do lead:', error)
        throw error
      }
    },

    async updateLeadStatus(id, newStatus) {
      try {
        const response = await api.patch(`/api/leads/${id}/status`, {
          status: newStatus
        })
        
        const leadAtualizado = response.data.data || response.data
        
        // Atualizar na lista local
        const index = this.leads.findIndex(l => l.id === id)
        if (index !== -1) {
          this.leads[index] = { ...this.leads[index], status: newStatus }
        }
        
        return true
      } catch (error) {
        console.error('❌ Erro ao atualizar status do funil:', error)
        throw error
      }
    },

    async deleteLead(id) {
      try {
        await api.delete(`/api/leads/${id}`)
        this.leads = this.leads.filter(lead => lead.id !== id)
      } catch (error) {
        console.error('Erro ao excluir lead', error)
        throw error
      }
    },

    async deleteLeads(ids) {
      try {
        await api.delete('/api/leads', {
          data: { ids }
        })
        this.leads = this.leads.filter(lead => !ids.includes(lead.id))
      } catch (error) {
        console.error('Erro ao excluir leads', error)
        throw error
      }
    }
  }
})
