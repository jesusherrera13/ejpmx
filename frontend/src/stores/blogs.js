// src/stores/blog.js
import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export const useBlogsStore = defineStore('blogs', {
  state: () => ({
    posts: [],
    loading: false,
    error: null
  }),

  actions: {
    async fetchPosts() {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/blog')
        this.posts = Array.isArray(response.data) 
          ? response.data 
          : (response.data.data || response.data.results || [])
      } catch (err) {
        console.error('Error fetching posts:', err)
        this.error = 'No se pudieron cargar las publicaciones del blog.'
      } finally {
        this.loading = false
      }
    },

    async createPost(postData) {
      this.loading = true
      try {
        const formData = new FormData()
        for (const key in postData) {
          if (postData[key] !== null && postData[key] !== undefined) {
            let val = postData[key]
            if (typeof val === 'boolean') val = val ? 1 : 0
            formData.append(key, val)
          }
        }

        const response = await axiosClient.post('/blog', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        
        const newPost = response.data.data || response.data
        this.posts.unshift(newPost)
        return true
      } catch (err) {
        console.error('Error creating post:', err)
        throw err
      } finally {
        this.loading = false
      }
    },

    async updatePost(id, postData) {
      this.loading = true
      try {
        const formData = new FormData()
        formData.append('_method', 'PUT') // Engaño a Laravel para peticiones PUT con archivos
        
        for (const key in postData) {
          if (postData[key] !== null && postData[key] !== undefined) {
            // Si la imagen es un string (URL), significa que no se seleccionó un archivo nuevo
            if (key === 'image' && !(postData[key] instanceof File)) {
              continue
            }
            let val = postData[key]
            if (typeof val === 'boolean') val = val ? 1 : 0
            formData.append(key, val)
          }
        }

        // Enviamos mediante POST por el FormData
        const response = await axiosClient.post(`/blog/${id}`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        
        const updatedPost = response.data.data || response.data
        const index = this.posts.findIndex(p => p.id === id)
        if (index !== -1) {
          this.posts[index] = updatedPost
        }
        return true
      } catch (err) {
        console.error('Error updating post:', err)
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})