import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const useUsersStore = defineStore('users', {
  state: () => ({
    users: [],
    loading: false,
    error: null
  }),

  actions: {
    async fetchUsers() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/user')
        // Adaptar la respuesta según devuelva Django (response.data o response.data.results si usas paginación)
        this.users = Array.isArray(response.data) ? response.data : (response.data.results || [])
      } catch (err) {
        console.error('Error al obtener la lista de usuarios:', err)
        this.error = 'No se pudo cargar la lista de usuarios.'
      } finally {
        this.loading = false
      }
    },

    async createUser(userData) {
      this.loading = true
      try {
        const response = await axiosClient.post('/user', userData)
        // Insertamos el nuevo usuario al inicio del arreglo local
        this.users.unshift(response.data)
        return true
      } catch (err) {
        console.error('Error al crear usuario:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateUser(id, userData) {
      this.loading = true
      try {
        const response = await axiosClient.put(`/user${id}/`, userData)
        // Actualizamos los datos en el arreglo local
        const index = this.users.findIndex(u => u.id === id)
        if (index !== -1) {
          this.users[index] = response.data
        }
        return true
      } catch (err) {
        console.error('Error al actualizar usuario:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})