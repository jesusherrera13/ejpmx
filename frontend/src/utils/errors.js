// src/utils/errors.js

// Obtiene el mensaje de error de una respuesta de Laravel (validación 422 o mensaje general)
export function getErrorMessage(error, fallback = 'Ocurrió un error inesperado.') {
  const data = error?.response?.data
  if (data?.errors) {
    const firstKey = Object.keys(data.errors)[0]
    return data.errors[firstKey][0]
  }
  if (data?.message) return data.message
  if (error?.request && !error?.response) return 'No se pudo conectar con el servidor.'
  return fallback
}
