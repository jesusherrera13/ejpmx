import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export type AdStatus = 'active' | 'scheduled' | 'expired' | 'inactive'

export interface Advertising {
  id: number
  title: string
  content: string | null
  content_type_id: number
  content_type?: { id: number, name: string, slug: string }
  sponsor_id: number
  sponsor?: { id: number, name: string, logo_url: string | null }
  image: string | null
  image_url: string | null
  video_url: string | null
  link_url: string | null
  starts_at: string | null
  ends_at: string | null
  is_permanent: boolean
  is_active: boolean
  status: AdStatus
  created_at: string
}

// Filtros del módulo: fecha en que está vigente, tipo de publicación y patrocinador
export interface AdFilters {
  date?: string | null
  content_type_id?: number | null
  sponsor_id?: number | null
}

const toFormData = (data: Record<string, unknown>) => {
  const form = new FormData()
  for (const [key, value] of Object.entries(data)) {
    if (value === undefined) continue
    if (key === 'image' && !(value instanceof File)) continue
    // Las fechas vacías se mandan vacías para poder quitarlas al editar
    if (value === null) {
      if (key === 'starts_at' || key === 'ends_at' || key === 'video_url' || key === 'link_url' || key === 'content') form.append(key, '')
      continue
    }
    if (typeof value === 'boolean') form.append(key, value ? '1' : '0')
    else if (value instanceof File) form.append(key, value)
    else if (typeof value !== 'object') form.append(key, String(value))
  }
  return form
}

export const useAdvertisingStore = defineStore('advertising', {
  state: () => ({
    ads: [] as Advertising[],
    loading: false,
    error: null as string | null,
  }),

  actions: {
    async fetchAds (filters: AdFilters = {}) {
      this.loading = true
      this.error = null
      try {
        const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== null && v !== undefined && v !== ''))
        const response = await axiosClient.get('/advertising', { params })
        this.ads = Array.isArray(response.data) ? response.data : []
      } catch (error) {
        console.error('Error al obtener la publicidad:', error)
        this.error = 'No se pudo cargar la publicidad.'
      } finally {
        this.loading = false
      }
    },

    async createAd (data: Record<string, unknown>) {
      this.loading = true
      try {
        const response = await axiosClient.post('/advertising', toFormData(data))
        this.ads.unshift(response.data.data)
        return response.data.data
      } finally {
        this.loading = false
      }
    },

    async updateAd (id: number, data: Record<string, unknown>) {
      this.loading = true
      try {
        const form = toFormData(data)
        form.append('_method', 'PUT')
        const response = await axiosClient.post(`/advertising/${id}`, form)
        const index = this.ads.findIndex(a => a.id === id)
        if (index !== -1) this.ads[index] = response.data.data
        return response.data.data
      } finally {
        this.loading = false
      }
    },

    async deleteItem (id: number) {
      this.loading = true
      try {
        await axiosClient.delete(`/advertising/${id}`)
        this.ads = this.ads.filter(a => a.id !== id)
        return true
      } finally {
        this.loading = false
      }
    },
  },
})
