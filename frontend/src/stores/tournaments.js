// src/stores/tournaments.js
import { defineStore } from 'pinia'
import axiosClient from '@/axios'
import { downloadFile } from '@/utils/download'

export const useTournamentsStore = defineStore('tournaments', {
  state: () => ({
    tournaments: [],
    loading: false,
    error: null
  }),

  actions: {
    async deleteItem(id) {
      this.loading = true
      try {
        await axiosClient.delete(`/tournaments/${id}`)
        this.tournaments = this.tournaments.filter(item => item.id !== id)
        return true
      } catch (err) {
        console.error('Error deleting tournament:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchTournaments() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/tournaments/')
        this.tournaments = Array.isArray(response.data) ? response.data : (response.data.results || [])
      } catch (err) {
        console.error('Error fetching tournaments:', err)
        this.error = 'Could not load tournaments data.'
      } finally {
        this.loading = false
      }
    },

    async fetchTournament(id) {
      const response = await axiosClient.get(`/tournaments/${id}`)
      return response.data
    },

    async createTournament(tournamentData) {
      this.loading = true
      try {
        const response = await axiosClient.post('/tournaments/', tournamentData)
        this.tournaments.unshift(response.data)
        return true
      } catch (err) {
        console.error('Error creating tournament:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateTournament(id, tournamentData) {
      this.loading = true
      try {
        const response = await axiosClient.put(`/tournaments/${id}/`, tournamentData)
        const index = this.tournaments.findIndex(t => t.id === id)
        if (index !== -1) {
          this.tournaments[index] = response.data
        }
        return true
      } catch (err) {
        console.error('Error updating tournament:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    // Descarga la plantilla de Excel para la carga masiva
    async downloadTemplate() {
      await downloadFile('/tournaments/template', 'ejpmx_plantilla_torneos.xlsx')
    },

    // Sube un Excel (Nombre de Torneo); crea o actualiza por nombre
    async importFile(file) {
      this.loading = true
      try {
        const formData = new FormData()
        formData.append('file', file)
        const response = await axiosClient.post('/tournaments/import', formData)
        await this.fetchTournaments()
        return response.data
      } catch (err) {
        console.error('Error importing tournaments:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})