// src/stores/teams.js
import { defineStore } from 'pinia'
import axiosClient from '@/axios'
import { downloadFile } from '@/utils/download'

export const useTeamsStore = defineStore('teams', {
  state: () => ({
    teams: [],
    loading: false,
    error: null
  }),

  actions: {
    async deleteItem(id) {
      this.loading = true
      try {
        await axiosClient.delete(`/teams/${id}`)
        this.teams = this.teams.filter(item => item.id !== id)
        return true
      } catch (err) {
        console.error('Error deleting team:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchTeams() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/teams/')
        this.teams = Array.isArray(response.data) ? response.data : (response.data.results || [])
      } catch (err) {
        console.error('Error fetching teams:', err)
        this.error = 'Could not load teams data.'
      } finally {
        this.loading = false
      }
    },

    async createTeam(teamData) {
      this.loading = true
      try {
        const response = await axiosClient.post('/teams/', teamData)
        this.teams.unshift(response.data)
        return true
      } catch (err) {
        console.error('Error creating team:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateTeam(id, teamData) {
      this.loading = true
      try {
        const response = await axiosClient.put(`/teams/${id}/`, teamData)
        const index = this.teams.findIndex(t => t.id === id)
        if (index !== -1) {
          this.teams[index] = response.data
        }
        return true
      } catch (err) {
        console.error('Error updating team:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    // Descarga la plantilla de Excel para la carga masiva
    async downloadTemplate() {
      await downloadFile('/teams/template', 'ejpmx_plantilla_equipos.xlsx')
    },

    // Sube un Excel (Nombre | Nombre Corto | Abreviación); crea o actualiza por nombre
    async importFile(file) {
      this.loading = true
      try {
        const formData = new FormData()
        formData.append('file', file)
        const response = await axiosClient.post('/teams/import', formData)
        await this.fetchTeams()
        return response.data
      } catch (err) {
        console.error('Error importing teams:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})