/**
 * plugins/vuetify.ts
 *
 * Framework documentation: https://vuetifyjs.com`
 */

// Composables
import { createVuetify } from 'vuetify'
// Styles
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

// Paleta tomada del logo de El Juego Perfecto MX.
// Base gris/blanco; los oscuros (brand-dark / brand-surface) se usan en barras y avatares de equipo.
export default createVuetify({
  theme: {
    defaultTheme: 'ejp',
    themes: {
      ejp: {
        dark: false,
        colors: {
          'background': '#F4F4F6',
          'surface': '#FFFFFF',
          'primary': '#7B5CF5',
          'secondary': '#E052C8',
          'info': '#3B8BF0',
          'brand-dark': '#0B0B12',
          'brand-surface': '#161624',
          'brand-magenta': '#E052C8',
          'brand-violet': '#7B5CF5',
          'brand-blue': '#3B8BF0',
        },
      },
    },
  },
})
