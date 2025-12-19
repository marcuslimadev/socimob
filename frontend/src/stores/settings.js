import { defineStore } from 'pinia'
import api from '../services/api'

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    items: {},
    loading: false,
    saving: false
  }),

  actions: {
    async fetchSettings() {
      this.loading = true
      try {
        const response = await api.get('/api/settings')
        this.items = response.data.data || response.data || {}
      } catch (error) {
        console.error('Erro ao carregar configurações', error)
      } finally {
        this.loading = false
      }
    },

    async saveSettings(payload) {
      this.saving = true
      try {
        await api.put('/api/settings', { settings: payload })
        this.items = { ...this.items, ...payload }
      } catch (error) {
        console.error('Erro ao salvar configurações', error)
        throw error
      } finally {
        this.saving = false
      }
    }
  }
})
