// src/utils/download.js
import axiosClient from '@/axios'

// Descarga un archivo de la API (con el token de sesión) y lo guarda en el navegador;
// con data se pide por POST (p. ej. el contenido de un PDF que arma el frontend)
export async function downloadFile(url, filename, data = null) {
  const response = data
    ? await axiosClient.post(url, data, { responseType: 'blob' })
    : await axiosClient.get(url, { responseType: 'blob' })
  const objectUrl = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = filename
  link.click()
  URL.revokeObjectURL(objectUrl)
}
