<template>
  <!-- Barra principal: marca, secciones y cuenta -->
  <v-app-bar flat color="brand-blue" class="text-white px-md-8" height="56" order="1">
    <router-link to="/" class="d-flex align-center mr-6 text-white text-decoration-none">
      <span class="font-weight-black text-h6 tracking-wider">EL JUEGO PERFECTO</span>
      <span class="text-caption font-weight-medium ml-1 text-white">MX</span>
    </router-link>

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


  <!-- Marcador con los partidos de los torneos públicos -->
  <ScoresTicker v-if="showTicker" />
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ScoresTicker from '@/components/ScoresTicker.vue'

defineProps({
  // El marcador de partidos debajo de la barra (se puede ocultar en páginas que no lo necesiten)
  showTicker: { type: Boolean, default: true },
})

const router = useRouter()
const authStore = useAuthStore()

// Secciones del portal (las que aún no tienen página quedan sin enlace)
const mainLinks = [
  { title: 'RESULTADOS', to: { name: 'Results' } },
  { title: 'POSICIONES', to: { name: 'Standings' } },
  { title: 'CALENDARIO', to: { name: 'Results' } },
  { title: 'ESTADÍSTICAS' },
  { title: 'JUGADORES' },
]

const handleLogout = async () => {
  try {
    await authStore.logout()
    router.push('/')
  } catch (error) {
    console.error('Error al cerrar sesión:', error)
  }
}
</script>

<style scoped>
.hover-white:hover {
  color: #fff !important;
  transition: color 0.2s;
}
.cursor-pointer {
  cursor: pointer;
}
.text-xxs {
  font-size: 0.68rem !important;
}
.menu-container {
  background-color: #f0f4f9 !important;
  border: 1px solid #e1e4e8;
}
.custom-menu-btn {
  background-color: #ffffff !important;
  border: 1px solid #e1e4e8 !important;
  height: 48px !important;
  transition: background-color 0.2s ease !important;
}
.custom-menu-btn:hover {
  background-color: #f7f9fc !important;
}
.admin-btn {
  border-left: 4px solid rgb(var(--v-theme-primary)) !important;
}
.custom-menu-btn :deep(.v-btn__overlay) {
  display: none !important;
}
.border-top-light {
  border-top: 1px solid #e1e4e8;
}
</style>
