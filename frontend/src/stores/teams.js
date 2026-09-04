// src/stores/teams.js
import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const useTeamsStore = defineStore('teams', {
  state: () => ({
    teams: [],
    loading: false,
    error: null
  }),

  actions: {
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
    }
  }
})