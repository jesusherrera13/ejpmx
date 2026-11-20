// src/stores/tournamentTeams.js
// Equipos inscritos en un torneo
import { defineStore } from 'pinia'
import axiosClient from '@/axios'
import { downloadFile } from '@/utils/download'
import { appendImportOptions } from '@/utils/importOptions'

export const useTournamentTeamsStore = defineStore('tournamentTeams', {
  state: () => ({
    teams: [],
    fieldingPositions: [],
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

    // Posición del jugador en el roster (null la quita): { player_id, fielding_position_id }
    async updateRosterPosition(tournamentId, teamId, playerId, fieldingPositionId) {
      const response = await axiosClient.put(`/tournaments/${tournamentId}/teams/${teamId}/players/${playerId}`, { fielding_position_id: fieldingPositionId })
      return response.data
    },

    // Catálogo de posiciones (P, C, 1B... DH, MGR, CH, BAT, STF); se pide una vez
    async fetchFieldingPositions() {
      if (!this.fieldingPositions.length) {
        const response = await axiosClient.get('/fielding-positions')
        this.fieldingPositions = response.data
      }
      return this.fieldingPositions
    },

    async removeRosterPlayer(tournamentId, teamId, playerId) {
      const response = await axiosClient.delete(`/tournaments/${tournamentId}/teams/${teamId}/players/${playerId}`)
      this.setRosterCount(teamId, response.data.roster.length)
      return response.data
    },

    // Vista previa de la carga del roster por Excel: { rows: [{ row, data, player_id, action, errors }] }
    // Busca por la columna CURP o, con options.match_by_name, por NOMBRE_COMPLETO
    async previewRosterFile(tournamentId, teamId, file, options = {}) {
      const formData = new FormData()
      formData.append('file', file)
      appendImportOptions(formData, options)
      const response = await axiosClient.post(`/tournaments/${tournamentId}/teams/${teamId}/players/import/preview`, formData)
      return response.data
    },

    // Agrega al roster los jugadores del archivo: { message, created, errors, roster, available }
    async importRosterFile(tournamentId, teamId, file, options = {}) {
      const formData = new FormData()
      formData.append('file', file)
      appendImportOptions(formData, options)
      const response = await axiosClient.post(`/tournaments/${tournamentId}/teams/${teamId}/players/import`, formData)
      this.setRosterCount(teamId, response.data.roster.length)
      return response.data
    },

    // Jugadores de la temporada: los de los rosters de todos los equipos inscritos
    async fetchPlayers(tournamentId) {
      const response = await axiosClient.get(`/tournaments/${tournamentId}/players`)
      return response.data
    },

    // format: 'pdf' o 'xlsx'; con teamIds, solo los jugadores de esos equipos
    async exportPlayers(tournamentId, format, filename, teamIds = []) {
      const query = teamIds.map(id => `team_ids[]=${id}`).join('&')
      await downloadFile(`/tournaments/${tournamentId}/players/export/${format}${query ? `?${query}` : ''}`, filename)
    },

    // PDF de los equipos inscritos; type: 'roster' o 'stats' (roster con su bateo en el torneo).
    // Con teamId (solo roster), únicamente el de ese equipo
    async exportTeamsPdf(tournamentId, type, filename, teamId = null) {
      await downloadFile(`/tournaments/${tournamentId}/teams/export/${type}${teamId ? `?team_id=${teamId}` : ''}`, filename)
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
