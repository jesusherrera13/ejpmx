<template>
    

    <!-- 2. NAVBAR PRINCIPAL (Se queda igual, Vuetify la apilará automáticamente debajo) -->
    <v-app-bar flat color="brand-blue" class="text-white px-md-8" height="56" order="1">
      <div class="d-flex align-center mr-6">
        <span class="font-weight-black text-h6 tracking-wider">EL JUEGO PERFECTO</span>
        <span class="text-caption font-weight-medium ml-1 text-white">MX</span>
      </div>

      <div class="d-none d-lg-flex ga-4 align-center">
        <v-btn variant="text" class="text-capitalize font-weight-bold text-body-2" v-for="link in mainLinks" :key="link.title" :to="link.to">
          {{ link.title }}
        </v-btn>
      </div>

      <v-spacer />

      <div class="d-flex align-center ga-3">
        <div class="d-none d-md-flex ga-3 text-caption text-grey-lighten-2 mr-4">
          <span class="cursor-pointer hover-white">TIENDA</span>
          <span class="cursor-pointer hover-white">EQUIPOS</span>
        </div>
        
        <v-btn icon="mdi-magnify" variant="text" size="small" />
        
        <!-- CONTROL DE AUTENTICACIÓN (DIARIO/MENÚ DE USUARIO) -->
        <template v-if="!authStore.user.id">
          <v-btn 
            variant="outlined" 
            size="small" 
            class="text-capitalize rounded-pill px-4 text-white border-white" 
            prepend-icon="mdi-account-circle"
            to="/login"
          >
            Iniciar Sesión
          </v-btn>
        </template>

        <template v-else>
          <!-- Menú desplegable estilo cuenta de Google -->
          <v-menu :close-on-content-click="false" location="bottom end" transition="scale-transition">
            <template v-slot:activator="{ props }">
              <v-avatar 
                v-bind="props" 
                color="secondary" 
                class="cursor-pointer text-white font-weight-bold elevation-1" 
                size="36"
              >
                {{ authStore.user.initial }}
              </v-avatar>
            </template>

            <!-- Contenedor del Menú Flotante -->
            <v-card width="340" class="mt-2 rounded-xl pa-4 menu-container" flat elevation="4">
              <!-- Encabezado del perfil -->
              <div class="text-center position-relative pt-2">
                <div class="text-caption text-grey-darken-2 font-weight-medium mb-1">
                  {{ authStore.user.email }}
                </div>
                
                <!-- Avatar Central (Soporta Foto de Perfil o Inicial) -->
                <div class="d-flex justify-center my-3">
                  <v-avatar 
                    color="secondary" 
                    size="76" 
                    class="text-white text-h4 font-weight-medium elevation-2"
                    :image="authStore.user.avatarUrl || undefined"
                  >
                    <template v-if="!authStore.user.avatarUrl">
                      {{ authStore.user.initial }}
                    </template>
                  </v-avatar>
                </div>

                <h2 class="text-h6 font-weight-regular text-grey-darken-4 mb-4">
                  ¡Hola, {{ authStore.user.name }}!
                </h2>
              </div>

              <v-divider class="my-2"></v-divider>

              <!-- LISTA DE MENÚS Y OPCIONES INTERACTIVAS -->
              <div class="d-flex flex-column ga-2 mt-2">
                
                <!-- MENÚ ADMINISTRADOR (Solo visible si isAdmin es true) -->
                <v-btn
                  block
                  variant="flat"
                  size="large"
                  class="text-capitalize rounded-pill justify-start px-4 custom-menu-btn admin-btn"
                  to="/admin/dashboard"
                >
                  <v-icon icon="mdi-shield-account" size="22" class="mr-3 text-primary" />
                  <span class="text-body-1 font-weight-medium text-grey-darken-4">
                    Panel de Administración
                  </span>
                </v-btn>

                <!-- BOTÓN CERRAR SESIÓN -->
                <v-btn
                  block
                  variant="flat"
                  size="large"
                  class="text-capitalize rounded-pill justify-start px-4 custom-menu-btn"
                  @click="handleLogout"
                >
                  <v-icon icon="mdi-logout" size="22" class="mr-3 text-grey-darken-3" />
                  <span class="text-body-1 font-weight-medium text-grey-darken-3">
                    Cerrar sesión
                  </span>
                </v-btn>
              </div>

              <!-- Footer Legal Mínimo -->
              <div class="d-flex justify-center ga-4 mt-4 pt-2 text-xxs text-grey-darken-1 border-top-light">
                <a href="#" class="text-decoration-none text-grey-darken-1">Política de Privacidad</a>
                <span>•</span>
                <a href="#" class="text-decoration-none text-grey-darken-1">Términos del Servicio</a>
              </div>
            </v-card>
          </v-menu>
        </template>
      </div>
    </v-app-bar>

    <!-- 1. MARCADOR SUPERIOR: partidos reales de los torneos públicos -->
    <ScoresTicker />

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
                >
                  Posiciones completas
                </v-btn>
              </div>

              <!-- Grid de tablas por división -->
              <v-row>
                <v-col
                  v-for="(division, divIdx) in standings"
                  :key="divIdx"
                  cols="12"
                  md="4"
                >
                  <v-card flat class="rounded-lg border pa-2 bg-white">
                    <div class="text-subtitle-2 font-weight-bold text-grey-darken-3 px-3 py-1">
                      {{ division.name }}
                    </div>
                    <v-table density="compact" class="standings-table">
                      <thead>
                        <tr>
                          <th class="text-left text-xxs font-weight-bold text-grey">EQUIPO</th>
                          <th class="text-center text-xxs font-weight-bold text-grey">G</th>
                          <th class="text-center text-xxs font-weight-bold text-grey">P</th>
                          <th class="text-center text-xxs font-weight-bold text-grey">%</th>
                          <th class="text-center text-xxs font-weight-bold text-grey">DIF</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(team, teamIdx) in division.teams" :key="teamIdx">
                          <td class="text-left font-weight-bold text-caption py-1">
                            <v-avatar size="18" class="mr-2" :color="team.color">
                              <span class="text-xxs text-white font-weight-bold">{{ team.logoChar }}</span>
                            </v-avatar>
                            {{ team.name }}
                          </td>
                          <td class="text-center text-caption font-weight-medium">{{ team.w }}</td>
                          <td class="text-center text-caption font-weight-medium">{{ team.l }}</td>
                          <td class="text-center text-caption font-weight-medium text-grey-darken-1">{{ team.pct }}</td>
                          <td class="text-center text-caption font-weight-medium text-grey-darken-1">{{ team.gb }}</td>
                        </tr>
                      </tbody>
                    </v-table>
                  </v-card>
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

            <!-- BLOQUE: PUBLICIDAD DE PATROCINADORES (al azar entre la vigente) -->
            <SponsoredAds />

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
import { useAuthStore } from '@/stores/auth';
import { usePublicBlogsStore } from '@/stores/publicBlogs'
import { videoThumbnail } from '@/utils/video'
import SponsoredAds from '@/components/SponsoredAds.vue'
import SiteFooter from '@/components/SiteFooter.vue'
import ScoresTicker from '@/components/ScoresTicker.vue'

const authStore = useAuthStore();

console.log(authStore.user);

/* const authStore = ref({
  isAuthenticated: true, // Cambia a false para ver el botón de login clásico
  user: {
    name: 'JESUS MANUEL',
    email: 'jesus.herrera@pyansa.com.mx',
    initial: 'J'
  }
}) */

// Enlaces navegación principal
// (solo Resultados y Calendario tienen página por ahora)
const mainLinks = [
  { title: 'RESULTADOS', to: { name: 'Results' } },
  { title: 'POSICIONES' },
  { title: 'CALENDARIO', to: { name: 'Results' } },
  { title: 'ESTADÍSTICAS' },
  { title: 'JUGADORES' },
]

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
})

// SECCIÓN: Posiciones (Estructura de tablas por División)
const standings = ref([
  {
    name: 'Este de la L.A.',
    teams: [
      { name: 'TB', logoChar: 'TB', color: '#092C5C', w: 56, l: 38, pct: '.596', gb: '-' },
      { name: 'NYY', logoChar: 'NY', color: '#0C2340', w: 54, l: 42, pct: '.563', gb: '3.0' },
      { name: 'BOS', logoChar: 'B', color: '#BD3039', w: 46, l: 48, pct: '.489', gb: '10.0' },
      { name: 'BAL', logoChar: 'O', color: '#DF4601', w: 46, l: 51, pct: '.474', gb: '11.5' },
      { name: 'TOR', logoChar: 'B', color: '#134A8E', w: 45, l: 51, pct: '.469', gb: '12.0' }
    ]
  },
  {
    name: 'Central de la L.A.',
    teams: [
      { name: 'CWS', logoChar: 'SO', color: '#27251F', w: 50, l: 45, pct: '.526', gb: '-' },
      { name: 'CLE', logoChar: 'C', color: '#E31937', w: 51, l: 46, pct: '.526', gb: '-' },
      { name: 'MIN', logoChar: 'M', color: '#0C2340', w: 48, l: 49, pct: '.495', gb: '3.0' },
      { name: 'DET', logoChar: 'D', color: '#0C2340', w: 44, l: 52, pct: '.458', gb: '6.5' },
      { name: 'KC', logoChar: 'KC', color: '#004687', w: 38, l: 59, pct: '.392', gb: '13.0' }
    ]
  },
  {
    name: 'Oeste de la L.A.',
    teams: [
      { name: 'TEX', logoChar: 'T', color: '#003278', w: 49, l: 47, pct: '.510', gb: '-' },
      { name: 'SEA', logoChar: 'S', color: '#0C2C56', w: 48, l: 49, pct: '.495', gb: '1.5' },
      { name: 'HOU', logoChar: 'H', color: '#EB6E1F', w: 47, l: 51, pct: '.480', gb: '3.0' },
      { name: 'ATH', logoChar: 'A', color: '#003831', w: 41, l: 55, pct: '.427', gb: '8.0' },
      { name: 'LAA', logoChar: 'A', color: '#BA0021', w: 38, l: 59, pct: '.392', gb: '11.5' }
    ]
  }
])

// SECCIÓN ADICIONAL: Juego de Estrellas 2026 (Columna Derecha)
const allStarNews = ref([
  { title: 'Cody Bellinger, cuarto Yankee en ser nombrado JMV del Juego de Estrellas', icon: 'mdi-baseball' },
  { title: 'Entre debuts, finales de película y talento joven, la Semana de Estrellas no decepcionó', icon: 'mdi-trophy' },
  { title: 'La Liga Americana pega temprano y conquista el Juego de Estrellas en Filadelfia', icon: 'mdi-star' },
  { title: '¿Héroes o villanos? Los más abucheados y ovacionados del All-Star', icon: 'mdi-account-group' },
  { title: 'Los mejores momentos en la historia del Festival de Jonrones', icon: 'mdi-fire' }
])

const handleLogout = async () => {
  try {
    // Aquí ejecutas tu llamada fetch/axios a tu endpoint de Django:
    // await axios.post('/api/auth/logout/')
    
    // Limpiamos el estado local de la aplicación
    //authStore.value.isAuthenticated = false
    //authStore.value.user = null
    await authStore.logout();
    // Opcional: Redirigir al inicio o a la vista de login
    if(authStore.user.user_id) router.push('/')
  } catch (error) {
    console.error('Error al cerrar sesión:', error)
  }
}
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