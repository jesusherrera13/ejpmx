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

export function formatRange(start, end) {
  return start === end ? formatDate(start) : `${formatDate(start)} – ${formatDate(end)}`
}

export function toIsoDate(date) {
  return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-')
}
