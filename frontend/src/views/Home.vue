<template>
    

    <!-- Encabezado del portal (barra principal y marcador), el mismo en todas las páginas públicas -->
    <SiteHeader />

    <!-- 3. CONTENIDO PRINCIPAL -->
    <v-main>
      <v-container class="py-8 px-4 px-md-8" fluid style="max-width: 1440px;">
        <v-row>
          <!-- COLUMNA IZQUIERDA (2/3 de la pantalla en desktop) -->
          <v-col cols="12" lg="8">
            <!-- NOTICIA DESTACADA: el artículo publicado más reciente -->
            <v-card v-if="featured" flat class="rounded-lg overflow-hidden bg-white mb-8" :to="postRoute(featured)">
              <v-img
                v-if="featured.image_url"
                :src="featured.image_url"
                height="450"
                cover
                class="align-end"
              >
                <template v-slot:placeholder>
                  <v-row class="fill-height ma-0" align="center" justify="center">
                    <v-progress-circular indeterminate color="primary" />
                  </v-row>
                </template>
              </v-img>

              <v-card-item class="pa-6">
                <div class="text-caption font-weight-bold text-primary text-uppercase mb-2">
                  {{ formatPostDate(featured.created_at) }}
                </div>
                <h1 class="text-h4 font-weight-black line-height-tight mb-4 text-grey-darken-4">
                  {{ featured.title }}
                </h1>
                <p class="text-body-1 text-grey-darken-2 line-clamp-3">
                  {{ featured.excerpt }}
                </p>
              </v-card-item>
            </v-card>

            <v-card v-else-if="blogsStore.loading || !blogsStore.loaded" flat class="rounded-lg overflow-hidden bg-white mb-8">
              <v-skeleton-loader type="image, article" />
            </v-card>

            <v-card v-else flat class="rounded-lg bg-white mb-8 pa-10 text-center text-grey-darken-1">
              <v-icon icon="mdi-newspaper-variant-outline" size="48" color="primary" class="mb-3" />
              <div class="text-h6 font-weight-bold text-grey-darken-3">Pronto habrá noticias</div>
              <div class="text-body-2">Aquí aparecerán los artículos que se publiquen.</div>
            </v-card>

            <!-- SECCIÓN: MOMENTOS CULMINANTES (videos publicados) -->
            <div v-if="videos.length" class="mb-8">
              <h2 class="text-h5 font-weight-black mb-4 text-grey-darken-4">
                Momentos culminantes
              </h2>
              <v-row>
                <v-col
                  v-for="video in videos"
                  :key="video.id"
                  cols="12"
                  sm="6"
                  md="3"
                >
                  <v-card flat class="bg-transparent" :to="postRoute(video)">
                    <!-- Miniatura del video -->
                    <v-img
                      :src="videoThumbnail(video.video_url, video.image_url) || undefined"
                      height="140"
                      cover
                      class="rounded-lg align-end position-relative video-thumbnail bg-brand-surface"
                    >
                      <div class="play-btn-overlay">
                        <v-btn
                          icon="mdi-play"
                          color="rgba(0, 0, 0, 0.6)"
                          theme="dark"
                          size="small"
                          class="play-icon"
                        />
                      </div>
                    </v-img>
                    <v-card-item class="px-1 py-3">
                      <p class="text-subtitle-2 font-weight-bold text-grey-darken-4 text-wrap line-clamp-2 leading-snug">
                        {{ video.title }}
                      </p>
                    </v-card-item>
                  </v-card>
                </v-col>
              </v-row>
            </div>

            <!-- SECCIÓN: MÁS NOTICIAS (los siguientes artículos) -->
            <div v-if="moreArticles.length" class="mb-8">
              <h2 class="text-h5 font-weight-black mb-4 text-grey-darken-4">
                Más noticias
              </h2>
              <v-row>
                <v-col
                  v-for="post in moreArticles"
                  :key="post.id"
                  cols="12"
                  sm="6"
                  md="3"
                >
                  <v-card
                    flat
                    height="320"
                    class="rounded-lg overflow-hidden position-relative d-flex align-end cursor-pointer popular-card"
                    :class="post.image_url ? '' : 'bg-brand-surface'"
                    :to="postRoute(post)"
                  >
                    <v-img
                      v-if="post.image_url"
                      :src="post.image_url"
                      cover
                      class="position-absolute fill-height w-100"
                    />
                    <div class="gradient-overlay"></div>

                    <v-card-text class="position-relative z-10 text-white pa-4">
                      <h3 class="text-subtitle-1 font-weight-black line-height-tight text-white mb-0">
                        {{ post.title }}
                      </h3>
                    </v-card-text>
                  </v-card>
                </v-col>
              </v-row>
            </div>

            <!-- SECCIÓN: POSICIONES -->
            <div class="mb-8">
              <div class="d-flex justify-space-between align-center mb-4">
                <h2 class="text-h5 font-weight-black text-grey-darken-4 mb-0">
                  Posiciones
                </h2>
                <v-btn
                  variant="text"
                  color="primary"
                  class="text-none font-weight-bold text-caption"
                  append-icon="mdi-chevron-right"
                  :to="{ name: 'Standings' }"
                >
                  Posiciones completas
                </v-btn>
              </div>

              <!-- Posiciones de torneos públicos al azar -->
              <v-row v-if="standingsLoading">
                <v-col v-for="n in 3" :key="n" cols="12" md="4">
                  <v-skeleton-loader type="table-tbody" class="rounded-lg" />
                </v-col>
              </v-row>
              <v-card v-else-if="!featuredStandings.length" flat class="rounded-lg pa-6 text-center text-body-2 text-grey-darken-1">
                Aún no hay torneos publicados.
              </v-card>
              <v-row v-else>
                <v-col
                  v-for="item in featuredStandings"
                  :key="item.tournament.id"
                  cols="12"
                  :md="featuredStandings.length === 1 ? 6 : 4"
                >
                  <StandingsCard :data="item" />
                </v-col>
              </v-row>
            </div>
          </v-col>

          <!-- COLUMNA DERECHA (1/3 de la pantalla - Barra Lateral) -->
          <v-col cols="12" lg="4">
            <!-- BLOQUE: TITULARES -->
            <v-card flat class="pa-5 rounded-lg bg-white mb-6">
              <h2 class="text-h6 font-weight-black mb-4 border-bottom pb-2 text-grey-darken-4 d-flex align-center">
                <v-icon icon="mdi-newspaper-variant-outline" class="mr-2" color="primary" />
                Titulares
              </h2>

              <div v-if="!headlines.length" class="text-body-2 text-grey-darken-1 py-2">
                {{ blogsStore.loading ? 'Cargando…' : 'Aún no hay publicaciones.' }}
              </div>
              <v-list v-else lines="two" class="bg-transparent pa-0">
                <v-list-item
                  v-for="news in headlines"
                  :key="news.id"
                  class="px-0 py-3 border-bottom-dashed"
                  :to="postRoute(news)"
                >
                  <template v-slot:prepend>
                    <v-avatar color="brand-surface" class="text-white" size="36">
                      <v-icon size="18">{{ news.type === 'video' ? 'mdi-play' : 'mdi-baseball' }}</v-icon>
                    </v-avatar>
                  </template>

                  <v-list-item-title class="text-subtitle-2 font-weight-bold text-wrap line-clamp-2 text-grey-darken-4">
                    {{ news.title }}
                  </v-list-item-title>
                </v-list-item>
              </v-list>
            </v-card>

            <!-- BLOQUE: LÍDERES DE UN TORNEO AL AZAR -->
            <StatLeaders />

            <!-- BLOQUE: PUBLICIDAD DE PATROCINADORES (al azar entre la vigente) -->
            <SponsoredAds placeholder />

            <!-- BLOQUE: JUEGO DE ESTRELLAS 2026 -->
            <v-card flat class="pa-5 rounded-lg bg-white">
              <h2 class="text-h6 font-weight-black mb-4 border-bottom pb-2 text-grey-darken-4 d-flex align-center">
                <v-icon icon="mdi-star-outline" class="mr-2" color="primary" />
                Juego de Estrellas 2026
              </h2>

              <v-list lines="two" class="bg-transparent pa-0">
                <v-list-item
                  v-for="(starNews, idx) in allStarNews"
                  :key="idx"
                  class="px-0 py-3 border-bottom-dashed"
                  link
                >
                  <template v-slot:prepend>
                    <v-avatar color="#0A2240" class="text-white" size="36">
                      <v-icon size="18">{{ starNews.icon }}</v-icon>
                    </v-avatar>
                  </template>

                  <v-list-item-title class="text-subtitle-2 font-weight-bold text-wrap line-clamp-2 text-grey-darken-4">
                    {{ starNews.title }}
                  </v-list-item-title>
                </v-list-item>
              </v-list>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <!-- PIE DE PÁGINA: redes y contacto -->
      <SiteFooter />
    </v-main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { usePublicBlogsStore } from '@/stores/publicBlogs'
import { videoThumbnail } from '@/utils/video'
import SponsoredAds from '@/components/SponsoredAds.vue'
import SiteFooter from '@/components/SiteFooter.vue'
import SiteHeader from '@/components/SiteHeader.vue'
import StandingsCard from '@/components/StandingsCard.vue'
import StatLeaders from '@/components/StatLeaders.vue'
import { usePublicGamesStore } from '@/stores/publicGames'


/* const authStore = ref({
  isAuthenticated: true, // Cambia a false para ver el botón de login clásico
  user: {
    name: 'JESUS MANUEL',
    email: 'jesus.herrera@pyansa.com.mx',
    initial: 'J'
  }
}) */

// Publicaciones del panel de administración (artículos y videos publicados)
const blogsStore = usePublicBlogsStore()

const featured = computed(() => blogsStore.articles[0] || null)
const moreArticles = computed(() => blogsStore.articles.slice(1, 5))
const videos = computed(() => blogsStore.videos.slice(0, 4))
const headlines = computed(() => blogsStore.posts.slice(0, 7))

const postRoute = (post) => ({ name: 'BlogPost', params: { id: post.id, slug: post.slug || 'publicacion' } })

const formatPostDate = (value) => value
  ? new Date(value).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
  : ''

onMounted(() => {
  blogsStore.fetchLatest()
  loadStandings()
})

// SECCIÓN: Posiciones de torneos públicos (al azar, primero los que ya tienen juegos)
const publicGamesStore = usePublicGamesStore()
const featuredStandings = ref([])
const standingsLoading = ref(true)

const loadStandings = async () => {
  try {
    featuredStandings.value = await publicGamesStore.fetchFeaturedStandings(3)
  } catch (error) {
    console.error('Error al cargar las posiciones:', error)
  } finally {
    standingsLoading.value = false
  }
}

// SECCIÓN ADICIONAL: Juego de Estrellas 2026 (Columna Derecha)
const allStarNews = ref([
  { title: 'Cody Bellinger, cuarto Yankee en ser nombrado JMV del Juego de Estrellas', icon: 'mdi-baseball' },
  { title: 'Entre debuts, finales de película y talento joven, la Semana de Estrellas no decepcionó', icon: 'mdi-trophy' },
  { title: 'La Liga Americana pega temprano y conquista el Juego de Estrellas en Filadelfia', icon: 'mdi-star' },
  { title: '¿Héroes o villanos? Los más abucheados y ovacionados del All-Star', icon: 'mdi-account-group' },
  { title: 'Los mejores momentos en la historia del Festival de Jonrones', icon: 'mdi-fire' }
])

</script>

<style scoped>
/* Scrollbar oculta para el scoreboard superior */
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.text-xxs {
  font-size: 0.65rem !important;
}

.line-height-tight {
  line-height: 1.2;
}

.border-bottom {
  border-bottom: 1px solid #e0e0e0 !important;
}

.border-bottom-dashed {
  border-bottom: 1px dashed #e0e0e0 !important;
}

.border-end {
  border-right: 1px solid #e0e0e0 !important;
}

.hover-white:hover {
  color: #fff !important;
  transition: color 0.2s;
}

/* Limitación de líneas en textos largos */
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Efectos de la tarjeta de video (Momentos Culminantes) */
.video-thumbnail {
  cursor: pointer;
  transition: transform 0.2s ease-in-out;
}
.video-thumbnail:hover {
  transform: scale(1.02);
}
.play-btn-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: rgba(0, 0, 0, 0.15);
  transition: background-color 0.2s;
}
.video-thumbnail:hover .play-btn-overlay {
  background-color: rgba(0, 0, 0, 0.3);
}

/* Estilos de la sección Popular */
.popular-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.popular-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0,0,0,0.12) !important;
}
.gradient-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 60%;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0) 100%);
  z-index: 1;
}

/* Ajustes finos para la tabla de posiciones */
.standings-table :deep(th) {
  height: 32px !important;
  background-color: #f8fafc !important;
  border-bottom: 2px solid #e2e8f0 !important;
}
.standings-table :deep(td) {
  height: 38px !important;
  border-bottom: 1px solid #f1f5f9 !important;
}
.standings-table tr:hover {
  background-color: #f8fafc;
}

.standings-table :deep(.v-table__wrapper) {
  overflow-x: hidden !important;
}

/* Scroll de comportamiento suave */
.scroll-smooth {
  scroll-behavior: smooth;
}

/* Ocultar barra de scroll en navegadores (mantiene la capacidad de arrastrar/scroll con trackpad) */
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.team-name-width {
  display: inline-block;
  width: 38px; /* Espacio ideal para abreviaciones de 3 letras como NYM, PHI, LAD */
}

/* Contenedor del menú */
.menu-container {
  background-color: #f0f4f9 !important;
  border: 1px solid #e1e4e8;
}

/* Estilo base para los botones del menú (Evita picos negros) */
.custom-menu-btn {
  background-color: #ffffff !important;
  border: 1px solid #e1e4e8 !important;
  height: 48px !important;
  transition: background-color 0.2s ease !important;
}

.custom-menu-btn:hover {
  background-color: #f7f9fc !important;
}

/* Estilo opcional distintivo si quieres resaltar el botón de Admin */
.admin-btn {
  border-left: 4px solid rgb(var(--v-theme-primary)) !important; 
}

/* Limpieza de overlays nativos de Vuetify */
.custom-menu-btn :deep(.v-btn__overlay) {
  display: none !important;
}

.border-top-light {
  border-top: 1px solid #e1e4e8;
}

/* Efecto hover interactivo sobre la opción de cerrar sesión */
.logout-item {
  background-color: #ffffff !important;
  border: 1px solid #e1e4e8 !important;
  transition: background-color 0.2s ease;
  overflow: hidden; /* Asegura que nada se salga del redondeado */
}
.logout-item:hover {
  background-color: #f7f9fc !important;
}

.logout-item :deep(.v-list-item__overlay) {
  opacity: 0 !important;
}

/* Tipografías pequeñas y decoradores */
.text-xxs {
  font-size: 0.68rem !important;
}
.border-top-light {
  border-top: 1px solid #e1e4e8;
}
.cursor-pointer {
  cursor: pointer;
}

.logout-btn {
  background-color: #ffffff !important;
  border: 1px solid #e1e4e8 !important;
  height: 48px !important;
  transition: background-color 0.2s ease !important;
}

.logout-btn:hover {
  background-color: #f7f9fc !important;
}

.logout-btn :deep(.v-btn__overlay) {
  display: none !important;
}

</style>