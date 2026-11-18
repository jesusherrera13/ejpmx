// Utilidades para URLs de video (YouTube y Vimeo) que se capturan en las publicaciones

const YOUTUBE_RE = /(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/
const VIMEO_RE = /vimeo\.com\/(?:video\/)?(\d+)/

export function youtubeId (url?: string | null): string | null {
  return url?.match(YOUTUBE_RE)?.[1] ?? null
}

// URL para insertar el video en un iframe; null si no se reconoce el servicio
export function embedUrl (url?: string | null): string | null {
  if (!url) return null
  const yt = youtubeId(url)
  if (yt) return `https://www.youtube.com/embed/${yt}`
  const vimeo = url.match(VIMEO_RE)?.[1]
  if (vimeo) return `https://player.vimeo.com/video/${vimeo}`
  return null
}

// Miniatura del video: la de YouTube o, si no, la imagen de la publicación
export function videoThumbnail (url?: string | null, fallback?: string | null): string | null {
  const yt = youtubeId(url)
  if (yt) return `https://img.youtube.com/vi/${yt}/hqdefault.jpg`
  return fallback || null
}
