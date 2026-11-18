<template>
  <v-app class="bg-background">
    <v-app-bar flat color="brand-blue" class="text-white px-md-8" height="56">
      <router-link to="/" class="d-flex align-center text-white text-decoration-none mr-6">
        <span class="font-weight-black text-h6 tracking-wider">EL JUEGO PERFECTO</span>
        <span class="text-caption font-weight-medium ml-1">MX</span>
      </router-link>
      <v-spacer />
      <v-btn to="/" variant="text" class="text-none font-weight-bold" prepend-icon="mdi-arrow-left">
        Inicio
      </v-btn>
    </v-app-bar>

    <v-main>
      <v-container class="py-8 px-4" style="max-width: 860px;">
        <!-- Cargando -->
        <v-card v-if="loading" flat class="rounded-lg bg-white">
          <v-skeleton-loader type="image, heading, paragraph, paragraph" />
        </v-card>

        <!-- No existe o no está publicada -->
        <v-card v-else-if="error" flat class="rounded-lg bg-white pa-10 text-center">
          <v-icon icon="mdi-file-question-outline" size="48" color="primary" class="mb-3" />
          <div class="text-h6 font-weight-bold mb-1">{{ error }}</div>
          <v-btn to="/" color="primary" variant="flat" class="text-none rounded-pill mt-4">Volver al inicio</v-btn>
        </v-card>

        <!-- Publicación -->
        <v-card v-else-if="post" flat class="rounded-lg overflow-hidden bg-white">
          <!-- Video: reproductor arriba; artículo: portada -->
          <div v-if="post.type === 'video' && videoEmbed" class="video-wrapper">
            <iframe
              :src="videoEmbed"
              :title="post.title"
              frameborder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            />
          </div>
          <v-img v-else-if="post.image_url" :src="post.image_url" max-height="480" cover />

          <div class="pa-6 pa-md-10">
            <div class="text-caption font-weight-bold text-primary text-uppercase mb-2">
              {{ post.type === 'video' ? 'Video' : 'Artículo' }} · {{ formattedDate }}
            </div>
            <h1 class="text-h4 font-weight-black line-height-tight text-grey-darken-4 mb-6">
              {{ post.title }}
            </h1>

            <v-btn
              v-if="post.type === 'video' && !videoEmbed && post.video_url"
              :href="post.video_url"
              target="_blank"
              rel="noopener"
              color="primary"
              variant="flat"
              class="text-none rounded-pill mb-6"
              prepend-icon="mdi-play"
            >
              Ver video
            </v-btn>

            <!-- HTML del editor del panel (solo lo capturan administradores) -->
            <div v-if="post.content" class="post-content" v-html="post.content" />
          </div>
        </v-card>
      </v-container>

      <!-- PIE DE PÁGINA: redes y contacto -->
      <SiteFooter />
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { usePublicBlogsStore, type PublicBlog } from '@/stores/publicBlogs'
import { embedUrl } from '@/utils/video'
import SiteFooter from '@/components/SiteFooter.vue'

const props = defineProps<{ id: string, slug?: string }>()

const blogsStore = usePublicBlogsStore()

const post = ref<PublicBlog | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const videoEmbed = computed(() => embedUrl(post.value?.video_url))
const formattedDate = computed(() => post.value?.created_at
  ? new Date(post.value.created_at).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
  : '')

const load = async (id: string) => {
  loading.value = true
  error.value = null
  post.value = null
  try {
    post.value = await blogsStore.fetchOne(id)
    document.title = `${post.value.title} | El Juego Perfecto MX`
    window.scrollTo(0, 0)
  } catch {
    error.value = 'Esta publicación no existe o ya no está disponible.'
  } finally {
    loading.value = false
  }
}

watch(() => props.id, id => load(id), { immediate: true })
</script>

<style scoped>
.line-height-tight {
  line-height: 1.2;
}
.video-wrapper {
  position: relative;
  padding-top: 56.25%;
  background: #000;
}
.video-wrapper iframe {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
.post-content {
  font-size: 1.0625rem;
  line-height: 1.8;
  color: #2a2a38;
  overflow-wrap: anywhere;
}
.post-content :deep(> * + *) { margin-top: 1em; }
.post-content :deep(h1) { font-size: 1.75rem; font-weight: 800; line-height: 1.25; margin-top: 1.4em; }
.post-content :deep(h2) { font-size: 1.4rem; font-weight: 700; line-height: 1.3; margin-top: 1.3em; }
.post-content :deep(h3) { font-size: 1.15rem; font-weight: 700; margin-top: 1.2em; }
.post-content :deep(ul),
.post-content :deep(ol) { padding-left: 1.5rem; }
.post-content :deep(blockquote) {
  border-left: 3px solid rgb(var(--v-theme-primary));
  padding-left: 1rem;
  color: #55556a;
  font-style: italic;
}
.post-content :deep(a) { color: rgb(var(--v-theme-primary)); }
</style>
