// src/stores/tournamentTeams.js
// Equipos inscritos en un torneo
import { defineStore } from 'pinia'
import axiosClient from '@/axios'
import { downloadFile } from '@/utils/download'

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

    // Equipos del propietario del torneo que aún no están inscritos
    async fetchAvailableTeams(tournamentId) {
      const response = await axiosClient.get(`/tournaments/${tournamentId}/teams/available`)
      return response.data
    },

    // Deja inscritos exactamente estos equipos: agrega los nuevos y retira los que falten
    async syncTeams(tournamentId, teamIds) {
      this.loading = true
      try {
        const response = await axiosClient.put(`/tournaments/${tournamentId}/teams`, { team_ids: teamIds })
        this.teams = response.data
        return true
      } catch (err) {
        console.error('Error saving tournament teams:', err)
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

    // Vista previa de la carga del roster por Excel (columna CURP): { rows: [{ row, data, player_id, action, errors }] }
    async previewRosterFile(tournamentId, teamId, file) {
      const formData = new FormData()
      formData.append('file', file)
      const response = await axiosClient.post(`/tournaments/${tournamentId}/teams/${teamId}/players/import/preview`, formData)
      return response.data
    },

    // Agrega al roster los jugadores del archivo: { message, created, errors, roster, available }
    async importRosterFile(tournamentId, teamId, file) {
      const formData = new FormData()
      formData.append('file', file)
      const response = await axiosClient.post(`/tournaments/${tournamentId}/teams/${teamId}/players/import`, formData)
      this.setRosterCount(teamId, response.data.roster.length)
      return response.data
    },

    // Jugadores de la temporada: los de los rosters de todos los equipos inscritos
    async fetchPlayers(tournamentId) {
      const response = await axiosClient.get(`/tournaments/${tournamentId}/players`)
      return response.data
    },

    // format: 'pdf' o 'xlsx'
    async exportPlayers(tournamentId, format, filename) {
      await downloadFile(`/tournaments/${tournamentId}/players/export/${format}`, filename)
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
