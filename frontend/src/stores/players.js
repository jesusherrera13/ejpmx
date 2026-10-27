// src/stores/players.js
import { defineStore } from 'pinia'
import axiosClient from '@/axios'
import { downloadFile } from '@/utils/download'

export const usePlayersStore = defineStore('players', {
  state: () => ({
    players: [],
    loading: false,
    error: null
  }),

  actions: {
    async deleteItem(id) {
      this.loading = true
      try {
        await axiosClient.delete(`/players/${id}`)
        this.players = this.players.filter(item => item.id !== id)
        return true
      } catch (err) {
        console.error('Error deleting player:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchPlayers() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/players/')
        this.players = Array.isArray(response.data) ? response.data : (response.data.results || [])
      } catch (err) {
        console.error('Error fetching players:', err)
        this.error = 'Could not load players data.'
      } finally {
        this.loading = false
      }
    },

    async createPlayer(playerData) {
      this.loading = true
      try {
        const response = await axiosClient.post('/players/', playerData)
        this.players.unshift(response.data)
        return true
      } catch (err) {
        console.error('Error creating player:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updatePlayer(id, playerData) {
      this.loading = true
      try {
        const response = await axiosClient.put(`/players/${id}/`, playerData)
        const index = this.players.findIndex(p => p.id === id)
        if (index !== -1) {
          this.players[index] = response.data
        }
        return true
      } catch (err) {
        console.error('Error updating player:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    // Descarga la plantilla de Excel para la carga masiva
    async downloadTemplate() {
      await downloadFile('/players/template', 'ejpmx_plantilla_jugadores.xlsx')
    },

    // Sube un Excel (APELLIDO 1 | APELLIDO 2 | NOMBRE | CURP | FECHA DE NACIMIENTO); crea o actualiza por CURP
    async importFile(file) {
      this.loading = true
      try {
        const formData = new FormData()
        formData.append('file', file)
        const response = await axiosClient.post('/players/import', formData)
        await this.fetchPlayers()
        return response.data
      } catch (err) {
        console.error('Error importing players:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})