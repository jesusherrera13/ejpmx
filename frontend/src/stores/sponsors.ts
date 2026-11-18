import { defineStore } from 'pinia'
import axiosClient from '@/axios'

export interface Sponsor {
  id: number
  name: string
  contact_name: string | null
  email: string | null
  phone: string | null
  website: string | null
  logo: string | null
  logo_url: string | null
  notes: string | null
  is_active: boolean
  advertisings_count?: number
}

// FormData para enviar el logo (archivo) junto con los demás campos
const toFormData = (data: Record<string, unknown>) => {
  const form = new FormData()
  for (const [key, value] of Object.entries(data)) {
    if (value === undefined || value === null) continue
    if (key === 'logo' && !(value instanceof File)) continue
    if (typeof value === 'boolean') form.append(key, value ? '1' : '0')
    else if (value instanceof File) form.append(key, value)
    else if (typeof value !== 'object') form.append(key, String(value))
  }
  return form
}

export const useSponsorsStore = defineStore('sponsors', {
  state: () => ({
    sponsors: [] as Sponsor[],
    loading: false,
    error: null as string | null,
  }),

  getters: {
    activeSponsors: state => state.sponsors.filter(s => s.is_active),
  },

  actions: {
    async fetchSponsors () {
      this.loading = true
      this.error = null
      try {
        const response = await axiosClient.get('/sponsors')
        this.sponsors = Array.isArray(response.data) ? response.data : []
      } catch (error) {
        console.error('Error al obtener los patrocinadores:', error)
        this.error = 'No se pudieron cargar los patrocinadores.'
      } finally {
        this.loading = false
      }
    },

    async createSponsor (data: Record<string, unknown>) {
      this.loading = true
      try {
        const response = await axiosClient.post('/sponsors', toFormData(data))
        const sponsor = response.data.data
        this.sponsors = [...this.sponsors, sponsor].sort((a, b) => a.name.localeCompare(b.name))
        return sponsor
      } finally {
        this.loading = false
      }
    },

    async updateSponsor (id: number, data: Record<string, unknown>) {
      this.loading = true
      try {
        const form = toFormData(data)
        form.append('_method', 'PUT') // archivos: Laravel recibe el PUT como POST
        const response = await axiosClient.post(`/sponsors/${id}`, form)
        const index = this.sponsors.findIndex(s => s.id === id)
        if (index !== -1) this.sponsors[index] = response.data.data
        return response.data.data
      } finally {
        this.loading = false
      }
    },

    async deleteItem (id: number) {
      this.loading = true
      try {
        await axiosClient.delete(`/sponsors/${id}`)
        this.sponsors = this.sponsors.filter(s => s.id !== id)
        return true
      } finally {
        this.loading = false
      }
    },
  },
})
