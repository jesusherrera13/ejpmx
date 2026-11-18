import { defineStore } from 'pinia'
import axiosClient from '@/axios'

// Publicaciones del portal (sin sesión): solo las publicadas en el panel de administración
export interface PublicBlog {
  id: number
  title: string
  slug: string
  type: 'article' | 'video' | string | null
  excerpt?: string
  content?: string
  image_url: string | null
  video_url: string | null
  created_at: string
}

export const usePublicBlogsStore = defineStore('publicBlogs', {
  state: () => ({
    posts: [] as PublicBlog[],
    loading: false,
    loaded: false,
    error: null as string | null,
  }),

  getters: {
    articles: state => state.posts.filter(p => p.type === 'article'),
    videos: state => state.posts.filter(p => p.type === 'video'),
  },

  actions: {
    async fetchLatest (limit = 30) {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/public/blogs', { params: { limit } })
        this.posts = Array.isArray(response.data) ? response.data : []
        this.loaded = true
      } catch (error) {
        console.error('Error al obtener las publicaciones:', error)
        this.error = 'No se pudieron cargar las publicaciones.'
      } finally {
        this.loading = false
      }
    },

    async fetchOne (id: number | string): Promise<PublicBlog> {
      const response = await axiosClient.get(`/public/blogs/${id}`)
      return response.data
    },
  },
})
