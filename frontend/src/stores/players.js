// src/stores/players.js
import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const usePlayersStore = defineStore('players', {
  state: () => ({
    players: [],
    loading: false,
    error: null
  }),

  actions: {
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
    }
  }
})