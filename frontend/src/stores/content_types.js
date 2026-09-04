import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const useContentTypesStore = defineStore('ContentTypes', {
  state: () => ({
    contentTypes: [],
    loading: false,
    error: null
  }),

  actions: {
    async fetchContentTypes() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/content-type')
        this.contentTypes = Array.isArray(response.data) ? response.data : (response.data.results || [])
      } catch (err) {
        console.error('Error al obtener la lista de tipos de contenido:', err)
        this.error = 'No se pudo cargar la lista de tipos de contenido.'
      } finally {
        this.loading = false
      }
    },

    async createContentType(contentTypeData) {
      this.loading = true
      try {
        const response = await axiosClient.post('/content-type', contentTypeData)
        this.contentTypes.unshift(response.data)
        return true
      } catch (err) {
        console.error('Error al crear tipo de contenido:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateContentType(id, contentTypeData) {
      this.loading = true
      try {
        const response = await axiosClient.put(`/content-type/${id}/`, contentTypeData)
        const index = this.contentTypes.findIndex(p => p.id === id)
        if (index !== -1) {
          this.contentTypes[index] = response.data
        }
        return true
      } catch (err) {
        console.error('Error al actualizar tipo de contenido:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})