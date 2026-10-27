// src/stores/notifications.js
// Toast global de la app: notify.success('...'), notify.error('...')
import { defineStore } from 'pinia'

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    show: false,
    message: '',
    color: 'success',
    icon: 'mdi-check-circle',
    timeout: 4000
  }),

  actions: {
    notify(message, { color = 'success', icon = 'mdi-check-circle', timeout = 4000 } = {}) {
      // Si ya hay un toast visible, se reemplaza por el nuevo
      this.show = false
      this.message = message
      this.color = color
      this.icon = icon
      this.timeout = timeout
      this.show = true
    },

    success(message) {
      this.notify(message)
    },

    error(message) {
      this.notify(message, { color: 'error', icon: 'mdi-alert-circle', timeout: 6000 })
    },

    warning(message) {
      this.notify(message, { color: 'warning', icon: 'mdi-alert', timeout: 6000 })
    }
  }
})
