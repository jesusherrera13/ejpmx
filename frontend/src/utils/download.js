// src/utils/download.js
import axiosClient from '@/axios'

// Descarga un archivo de la API (con el token de sesión) y lo guarda en el navegador
export async function downloadFile(url, filename) {
  const response = await axiosClient.get(url, { responseType: 'blob' })
  const objectUrl = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = filename
  link.click()
  URL.revokeObjectURL(objectUrl)
}
