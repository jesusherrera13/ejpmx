<template>
  <!-- Publicidad vigente de los patrocinadores, al azar; sin ninguna se oculta o, con placeholder, invita a anunciarse -->
  <v-card v-if="ads.length || (placeholder && loaded)" flat class="pa-5 rounded-lg bg-white mb-6">
    <h2 class="text-h6 font-weight-black mb-4 border-bottom pb-2 text-grey-darken-4 d-flex align-center">
      <v-icon icon="mdi-bullhorn-outline" class="mr-2" color="primary" />
      Patrocinadores
    </h2>

    <div v-if="!ads.length" class="text-center py-4 px-2 placeholder-box rounded-lg">
      <v-icon icon="mdi-storefront-outline" size="40" color="primary" class="mb-2" />
      <div class="text-subtitle-2 font-weight-bold text-grey-darken-3 mb-1">Tu marca puede estar aquí</div>
      <div class="text-body-2 text-grey-darken-1 mb-3">Llega a los aficionados del béisbol amateur.</div>
      <v-btn
        :href="`mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent('Quiero anunciarme en El Juego Perfecto MX')}`"
        variant="outlined"
        color="primary"
        size="small"
        class="text-none rounded-pill"
        prepend-icon="mdi-email-outline"
      >
        Anúnciate con nosotros
      </v-btn>
    </div>

    <v-fade-transition v-else group>
      <component
        :is="adHref(ad) ? 'a' : 'div'"
        v-for="ad in ads"
        :key="ad.id"
        :href="adHref(ad) || undefined"
        :target="adHref(ad) ? '_blank' : undefined"
        :rel="adHref(ad) ? 'noopener sponsored' : undefined"
        class="ad-item d-block text-decoration-none mb-4"
      >
        <v-img
          v-if="adImage(ad)"
          :src="adImage(ad)"
          :alt="ad.title"
          :aspect-ratio="ad.type === 'video' ? 1.78 : 0.8"
          cover
          class="rounded-lg bg-grey-lighten-3 mb-2"
        >
          <div v-if="ad.type === 'video'" class="d-flex fill-height align-center justify-center ad-play">
            <v-icon icon="mdi-play-circle" color="white" size="44" />
          </div>
        </v-img>

        <div class="d-flex align-center ga-2">
          <v-avatar v-if="ad.sponsor?.logo_url" size="24" rounded="sm" class="border">
            <v-img :src="ad.sponsor.logo_url" contain />
          </v-avatar>
          <div class="min-w-0">
            <div class="text-subtitle-2 font-weight-bold text-grey-darken-4 line-clamp-2">{{ ad.title }}</div>
            <div class="text-caption text-grey-darken-1">
              Patrocinado{{ ad.sponsor?.name ? ` · ${ad.sponsor.name}` : '' }}
            </div>
          </div>
        </div>
        <p v-if="!adImage(ad) && ad.excerpt" class="text-body-2 text-grey-darken-2 mt-1 mb-0 line-clamp-3">{{ ad.excerpt }}</p>
      </component>
    </v-fade-transition>
  </v-card>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import axiosClient from '@/axios'
import { videoThumbnail } from '@/utils/video'

interface PublicAd {
  id: number
  title: string
  type: string | null
  content: string | null
  excerpt?: string
  image_url: string | null
  video_url: string | null
  link_url: string | null
  sponsor: { name: string, logo_url: string | null } | null
}

const props = withDefaults(defineProps<{
  limit?: number
  // Cada cuánto se vuelve a sortear (ms); 0 = solo al cargar
  rotateEvery?: number
  // Sin publicidad vigente, muestra una invitación a anunciarse en lugar de ocultarse
  placeholder?: boolean
}>(), {
  limit: 2,
  rotateEvery: 30_000,
  placeholder: false,
})

// Mismo contacto del pie de página
const CONTACT_EMAIL = 'eljuegoperfectomx13@gmail.com'

const ads = ref<PublicAd[]>([])
const loaded = ref(false)
let timer: ReturnType<typeof setInterval> | null = null

const plainText = (html: string | null) => {
  if (!html) return ''
  const div = document.createElement('div')
  div.innerHTML = html
  return (div.textContent || '').replace(/\s+/g, ' ').trim()
}

const adImage = (ad: PublicAd) => (ad.type === 'video' ? videoThumbnail(ad.video_url, ad.image_url) : ad.image_url) || undefined
const adHref = (ad: PublicAd) => ad.link_url || (ad.type === 'video' ? ad.video_url : null)

const load = async () => {
  try {
    const response = await axiosClient.get('/public/advertising', { params: { limit: props.limit } })
    const rows: PublicAd[] = Array.isArray(response.data) ? response.data : []
    ads.value = rows.map(ad => ({ ...ad, excerpt: plainText(ad.content) }))
  } catch (error) {
    // La publicidad no debe afectar la portada: si falla, simplemente no se muestra
    console.error('Error al obtener la publicidad:', error)
  } finally {
    loaded.value = true
  }
}

onMounted(() => {
  load()
  if (props.rotateEvery > 0) timer = setInterval(load, props.rotateEvery)
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.border-bottom {
  border-bottom: 1px solid #e0e0e0 !important;
}
.ad-item {
  color: inherit;
  transition: transform 0.15s ease-in-out;
}
a.ad-item:hover {
  transform: translateY(-2px);
}
.ad-item:last-child {
  margin-bottom: 0 !important;
}
.placeholder-box {
  border: 1px dashed #c5d3ee;
  background: #f7f9fe;
}
.ad-play {
  background: rgba(0, 0, 0, 0.25);
}
.min-w-0 {
  min-width: 0;
}
.line-clamp-2,
.line-clamp-3 {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.line-clamp-2 { -webkit-line-clamp: 2; }
.line-clamp-3 { -webkit-line-clamp: 3; }
</style>
