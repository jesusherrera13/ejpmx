// src/utils/dates.js

// Fechas 'YYYY-MM-DD' (o ISO) se interpretan en hora local para no recorrer el día
export function parseDate(value) {
  if (!value) return null
  const [year, month, day] = String(value).substring(0, 10).split('-').map(Number)
  return new Date(year, month - 1, day)
}

const dateFormatter = new Intl.DateTimeFormat('es-MX', { weekday: 'short', day: '2-digit', month: 'short' })

// "vie, 06 nov"
export function formatDate(value) {
  const date = parseDate(value)
  return date ? dateFormatter.format(date) : ''
}

// Compara solo el día: 'YYYY-MM-DD HH:mm:ss' del mismo día es un solo día
export function formatRange(start, end) {
  return String(start).substring(0, 10) === String(end).substring(0, 10) ? formatDate(start) : `${formatDate(start)} – ${formatDate(end)}`
}

// '2026-10-31 19:30:00' -> '19:30'; vacío si no tiene hora asignada (00:00)
export function formatTime(value) {
  const time = String(value || '').substring(11, 16)
  return time && time !== '00:00' ? time : ''
}

export function toIsoDate(date) {
  return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-')
}
