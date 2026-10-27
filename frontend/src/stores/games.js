// src/stores/games.js
// Rol de juegos (calendario) de un torneo
import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const useGamesStore = defineStore('games', {
  state: () => ({
    games: [],
    // jornada => equipo que descansa
    byes: {},
    loading: false,
    error: null
  }),

  actions: {
    async fetchGames(tournamentId) {
      this.loading = true
      this.error = null
      // No mostrar el calendario de otro torneo mientras carga
      this.games = []
      this.byes = {}
      try {
        const response = await axiosClient.get(`/tournaments/${tournamentId}/games`)
        this.games = response.data.games
        this.byes = response.data.byes
      } catch (err) {
        console.error('Error fetching games:', err)
        this.error = 'Could not load the calendar.'
      } finally {
        this.loading = false
      }
    },

    // options: { start_date, rounds, games_per_series, rest_days }
    async generate(tournamentId, options) {
      this.loading = true
      try {
        const response = await axiosClient.post(`/tournaments/${tournamentId}/games/generate`, options)
        this.games = response.data.games
        this.byes = response.data.byes
        return true
      } catch (err) {
        console.error('Error generating calendar:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async clear(tournamentId) {
      this.loading = true
      try {
        await axiosClient.delete(`/tournaments/${tournamentId}/games`)
        this.games = []
        this.byes = {}
        return true
      } catch (err) {
        console.error('Error clearing calendar:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})
