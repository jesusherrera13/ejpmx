import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const useRolesStore = defineStore('Roles', {
  state: () => ({
    roles: [],
    loading: false,
    error: null
  }),

  actions: {
    async fetchRoles() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/role')
        this.roles = Array.isArray(response.data) ? response.data : (response.data.results || [])
      } catch (err) {
        console.error('Error al obtener la lista de roles:', err)
        this.error = 'No se pudo cargar la lista de roles.'
      } finally {
        this.loading = false
      }
    },

    async createRol(rolData) {
      this.loading = true
      try {
        const response = await axiosClient.post('/role', rolData)
        this.roles.unshift(response.data)
        return true
      } catch (err) {
        console.error('Error al crear perfil:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateRol(id, rolData) {
      this.loading = true
      try {
        const response = await axiosClient.put(`/role${id}/`, rolData)
        const index = this.roles.findIndex(p => p.id === id)
        if (index !== -1) {
          this.roles[index] = response.data
        }
        return true
      } catch (err) {
        console.error('Error al actualizar perfil:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})