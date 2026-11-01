// src/stores/tournamentTeams.js
// Equipos inscritos en un torneo
import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const useTournamentTeamsStore = defineStore('tournamentTeams', {
  state: () => ({
    teams: [],
    loading: false,
    error: null
  }),

  actions: {
    async fetchTeams(tournamentId) {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get(`/tournaments/${tournamentId}/teams`)
        this.teams = response.data
      } catch (err) {
        console.error('Error fetching tournament teams:', err)
        this.error = 'Could not load tournament teams.'
      } finally {
        this.loading = false
      }
    },

    async addTeams(tournamentId, teamIds) {
      this.loading = true
      try {
        const response = await axiosClient.post(`/tournaments/${tournamentId}/teams`, { team_ids: teamIds })
        this.teams = response.data
        return true
      } catch (err) {
        console.error('Error adding tournament teams:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    // Roster del equipo en el torneo: { roster, available }
    async fetchRoster(tournamentId, teamId) {
      const response = await axiosClient.get(`/tournaments/${tournamentId}/teams/${teamId}/players`)
      return response.data
    },

    async addRosterPlayers(tournamentId, teamId, playerIds) {
      const response = await axiosClient.post(`/tournaments/${tournamentId}/teams/${teamId}/players`, { player_ids: playerIds })
      this.setRosterCount(teamId, response.data.roster.length)
      return response.data
    },

    async removeRosterPlayer(tournamentId, teamId, playerId) {
      const response = await axiosClient.delete(`/tournaments/${tournamentId}/teams/${teamId}/players/${playerId}`)
      this.setRosterCount(teamId, response.data.roster.length)
      return response.data
    },

    setRosterCount(teamId, count) {
      const team = this.teams.find(item => item.id === teamId)
      if (team) team.roster_count = count
    },

    async removeTeam(tournamentId, teamId) {
      this.loading = true
      try {
        await axiosClient.delete(`/tournaments/${tournamentId}/teams/${teamId}`)
        this.teams = this.teams.filter(team => team.id !== teamId)
        return true
      } catch (err) {
        console.error('Error removing tournament team:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})
