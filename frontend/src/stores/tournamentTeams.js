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
