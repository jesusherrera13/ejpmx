// src/stores/games.js
// Rol de juegos (calendario) de un torneo
import { defineStore } from 'pinia'
import axiosClient from '@/axios'
import { downloadFile } from '@/utils/download'

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

    // data: { away_team_id, home_team_id, scheduled_at: 'YYYY-MM-DD HH:mm' }
    async updateGame(tournamentId, gameId, data) {
      const response = await axiosClient.put(`/tournaments/${tournamentId}/games/${gameId}`, data)
      // Se recarga sin vaciar la tabla: al cambiar equipos puede cambiar quién descansa
      const schedule = await axiosClient.get(`/tournaments/${tournamentId}/games`)
      this.games = schedule.data.games
      this.byes = schedule.data.byes
      return response.data
    },

    // { game (con batting_lines), players, positions }
    async fetchBoxscore(tournamentId, gameId) {
      const response = await axiosClient.get(`/tournaments/${tournamentId}/games/${gameId}/boxscore`)
      return response.data
    },

    // Guarda el box score y recarga el calendario para reflejar el marcador
    async saveBoxscore(tournamentId, gameId, data) {
      const response = await axiosClient.put(`/tournaments/${tournamentId}/games/${gameId}/boxscore`, data)
      const schedule = await axiosClient.get(`/tournaments/${tournamentId}/games`)
      this.games = schedule.data.games
      this.byes = schedule.data.byes
      return response.data
    },

    // Tabla de posiciones a una fecha ('YYYY-MM-DD'; sin fecha, la del último partido jugado):
    // { date, first_date, last_date, games_played, games_total, standings }
    async fetchStandings(tournamentId, date = null) {
      const response = await axiosClient.get(`/tournaments/${tournamentId}/standings`, { params: date ? { date } : {} })
      return response.data
    },

    // Bateo de los jugadores a una fecha ('YYYY-MM-DD'; sin fecha, hoy): { date, games, players }
    async fetchBattingStats(tournamentId, date = null) {
      const response = await axiosClient.get(`/tournaments/${tournamentId}/stats/batting`, { params: date ? { date } : {} })
      return response.data
    },

    // format: 'pdf' o 'xlsx'; la tabla de posiciones a la fecha
    async exportStandings(tournamentId, format, date, filename) {
      await downloadFile(`/tournaments/${tournamentId}/standings/export/${format}?date=${date}`, filename)
    },

    // format: 'pdf' o 'xlsx'; con teamId solo los jugadores de ese equipo
    async exportBattingStats(tournamentId, format, date, teamId, filename) {
      const team = teamId ? `&team_id=${teamId}` : ''
      await downloadFile(`/tournaments/${tournamentId}/stats/batting/export/${format}?date=${date}${team}`, filename)
    },

    // format: 'xlsx' o 'pdf'; view: 'games' (partidos) o 'series'
    async exportCalendar(tournamentId, format, view, filename) {
      await downloadFile(`/tournaments/${tournamentId}/games/export/${format}?view=${view}`, filename)
    },

    // Calendario calculado con las opciones, sin guardarlo
    async preview(tournamentId, options) {
      this.loading = true
      try {
        const response = await axiosClient.post(`/tournaments/${tournamentId}/games/preview`, options)
        return response.data
      } catch (err) {
        console.error('Error previewing calendar:', err)
        throw err
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
