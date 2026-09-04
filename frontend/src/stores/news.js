import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const useNewsStore = defineStore('news', {
  state: () => ({
    news: [],
    loading: false,
    error: null
  }),

  actions: {
    async fetchNews() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/news/')
        // Adaptar la respuesta según devuelva Django (response.data o response.data.results si usas paginación)
        this.news = Array.isArray(response.data) ? response.data : (response.data.results || [])
      } catch (err) {
        console.error('Error al obtener la lista de noticias:', err)
        this.error = 'No se pudo cargar la lista de noticias.'
      } finally {
        this.loading = false
      }
    },

    async createNew(newData) {
      this.loading = true
      try {
        const response = await axiosClient.post('/news/', newData)
        // Insertamos la nueva noticia al inicio del arreglo local
        this.news.unshift(response.data)
        return true
      } catch (err) {
        console.error('Error al crear noticia:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateNew(id, newData) {
      this.loading = true
      try {
        const response = await axiosClient.put(`/news/${id}/`, newData)
        // Actualizamos los datos en el arreglo local
        const index = this.news.findIndex(u => u.id === id)
        if (index !== -1) {
          this.news[index] = response.data
        }
        return true
      } catch (err) {
        console.error('Error al actualizar noticia:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})