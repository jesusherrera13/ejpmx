import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const useTournamentsStore = defineStore('tournaments', {
  state: () => ({
    tournaments: [],
    loading: false,
    error: null
  }),

  actions: {
    async fetchTournaments() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/tournaments/')
        // Adaptar la respuesta según devuelva Django (response.data o response.data.results si usas paginación)
        this.tournaments = Array.isArray(response.data) ? response.data : (response.data.results || [])
      } catch (err) {
        console.error('Error al obtener la lista de torneos:', err)
        this.error = 'No se pudo cargar la lista de torneos.'
      } finally {
        this.loading = false
      }
    },

    async createTournament(newData) {
      this.loading = true
      try {
        const response = await axiosClient.post('/tournaments/', newData)
        // Insertamos el nuevo torneo al inicio del arreglo local
        this.tournaments.unshift(response.data)
        return true
      } catch (err) {
        console.error('Error al crear torneo:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateNew(id, newData) {
      this.loading = true
      try {
        const response = await axiosClient.put(`/tournaments/${id}/`, newData)
        // Actualizamos los datos en el arreglo local
        const index = this.tournaments.findIndex(u => u.id === id)
        if (index !== -1) {
          this.tournaments[index] = response.data
        }
        return true
      } catch (err) {
        console.error('Error al actualizar torneo:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})