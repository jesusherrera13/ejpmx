<template>
  <v-app class="bg-admin-grey">
    
    <!-- 1. BARRA SUPERIOR DEL PANEL -->
    <v-app-bar flat color="white" class="border-bottom px-4" height="64">
      <v-app-bar-nav-icon @click="drawer = !drawer" class="text-grey-darken-3" />
      
      <v-toolbar-title class="font-weight-black text-grey-darken-4 text-h6 d-flex align-center">
        <v-icon icon="mdi-shield-crown" color="primary" class="mr-2" size="26" />
        Panel de Administración
      </v-toolbar-title>

      <v-spacer></v-spacer>

      <!-- Botón de regreso al sitio público -->
      <v-btn to="/" variant="text" size="small" class="text-capitalize font-weight-bold text-grey-darken-2 mr-2" prepend-icon="mdi-arrow-left">
        Volver al Sitio
      </v-btn>

      <!-- Menú de Usuario -->
      <v-menu :close-on-content-click="false" location="bottom end" transition="scale-transition">
        <template v-slot:activator="{ props }">
          <v-avatar v-bind="props" color="orange-darken-3" class="cursor-pointer text-white font-weight-bold elevation-1" size="36">
            {{ authStore.user?.initial || 'U' }}
          </v-avatar>
        </template>

        <v-card width="300" class="mt-2 rounded-xl pa-4 menu-container-mini" flat elevation="4">
          <div class="text-center pt-1">
            <div class="text-caption text-grey-darken-2 font-weight-medium">{{ authStore.user?.email }}</div>
            <h3 class="text-body-1 font-weight-bold text-grey-darken-4 my-2">¡Hola, {{ authStore.user?.name }}!</h3>
            <v-divider class="my-2"></v-divider>
            <v-btn block variant="flat" size="small" class="text-capitalize rounded-pill justify-start px-3 custom-menu-btn" @click="handleLogout">
              <v-icon icon="mdi-logout" size="18" class="mr-2 text-grey-darken-3" />
              <span class="text-caption font-weight-medium text-grey-darken-3">Cerrar sesión</span>
            </v-btn>
          </div>
        </v-card>
      </v-menu>
    </v-app-bar>

    <!-- 2. SIDEBAR LATERAL (NAVEGACIÓN DE MÓDULOS) -->
    <v-navigation-drawer 
      v-model="drawer" 
      color="white" 
      flat 
      class="border-end" 
      width="260"
      theme="light"
    >
      <div class="pa-4 text-overline font-weight-black text-grey-darken-1">Módulos del Sistema</div>
      
      <v-list density="comfortable" nav class="px-3 bg-white">
        <v-list-item
          v-for="item in filteredMenuItems"
          :key="item.id"
          :value="item.id"
          :active="activeTab === item.id"
          @click="activeTab = item.id"
          color="primary"
          rounded="xl"
          class="mb-1 py-2 text-grey-darken-3"
        >
          <template v-slot:prepend>
            <v-icon :icon="item.icon" size="22" class="mr-2" />
          </template>
          <v-list-item-title class="font-weight-medium text-body-2 text-black">
            {{ item.title }}
          </v-list-item-title>
        </v-list-item>
      </v-list>
    </v-navigation-drawer>

    <!-- 3. ÁREA DE CONTENIDO PRINCIPAL -->
    <v-main>
      <v-container class="pa-6" fluid>
        
        <!-- Encabezado de la sección dinámica -->
        <div class="d-flex justify-space-between align-center mb-6">
          <div>
            <h1 class="text-h5 font-weight-black text-grey-darken-4">
              {{ currentModule.title }}
            </h1>
            <p class="text-body-2 text-grey">
              Gestiona, añade o edita la información del módulo de {{ currentModule.title.toLowerCase() }}.
            </p>
          </div>
          <v-btn 
            color="black" 
            class="text-capitalize rounded-pill font-weight-bold px-4" 
            prepend-icon="mdi-plus" 
            flat
            @click="openCreateModal"
          >
            Nuevo Registro
          </v-btn>
        </div>

        <!-- Tarjetas de Estadísticas Rápidas -->
        <v-row class="mb-6">
          <v-col cols="12" sm="6" md="3">
            <v-card variant="outlined" color="grey-lighten-2" class="rounded-xl bg-white">
              <v-card-text class="pa-4 d-flex align-center justify-space-between text-black">
                <div>
                  <div class="text-caption text-grey font-weight-bold mb-1">TOTAL EN MÓDULO</div>
                  <div class="text-h4 font-weight-black">{{ totalItems }}</div>
                </div>
                <v-avatar color="blue-lighten-5" class="text-blue" size="48">
                  <v-icon :icon="currentModule?.icon" size="24" />
                </v-avatar>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Filtro/Búsqueda -->
        <v-row class="mb-3 d-flex justify-end">
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              placeholder="Buscar..."
              variant="outlined"
              density="compact"
              hide-details
              class="bg-white rounded-pill text-field-search"
            />
          </v-col>
        </v-row>

        <!-- TABLAS DE DATOS SEGÚN EL MÓDULO ACTIVO -->
        <v-card variant="outlined" color="grey-lighten-2" class="rounded-xl bg-white overflow-hidden pa-1" theme="light">
          
          <!-- Módulo: USUARIOS -->
          <v-data-table
            v-if="activeTab === 'usuarios'"
            :headers="userHeaders"
            :items="usersStore.users"
            :loading="usersStore.loading"
            :search="search"
            class="admin-data-table bg-white"
            density="comfortable"
          >
            <template v-slot:item.name="{ item }">
              <div class="d-flex align-center py-2">
                <div>
                  <div class="text-body-2 font-weight-bold text-grey-darken-4">
                    {{ item.name }}
                  </div>
                  <div class="text-xxs text-grey">
                    @{{ item.username }} • {{ item.roles?.[0]?.name || (item.is_superuser ? 'Superusuario' : 'Sin Rol') }}
                  </div>
                </div>
              </div>
            </template>
            <template v-slot:item.is_active="{ item }">
              <v-chip size="x-small" :color="item.is_active !== false ? 'success' : 'error'" variant="flat" class="font-weight-bold rounded-pill">
                {{ item.is_active !== false ? 'Activo' : 'Inactivo' }}
              </v-chip>
            </template>
            <template v-slot:item.actions="{ item }">
              <v-btn icon="mdi-pencil" size="x-small" variant="text" color="grey-darken-2" @click="openEditModal(item)" />
              <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" />
            </template>
          </v-data-table>

          <!-- Módulo: PERFILES -->
          <v-data-table
            v-else-if="activeTab === 'roles'"
            :headers="rolHeaders"
            :items="rolesStore.roles"
            :loading="rolesStore.loading"
            :search="search"
            class="admin-data-table bg-white"
            density="comfortable"
          >
            <template v-slot:item.name="{ item }">
              <span class="font-weight-bold text-grey-darken-4">{{ item.name }}</span>
            </template>
            <template v-slot:item.is_active="{ item }">
              <v-chip size="x-small" :color="item.is_active ? 'success' : 'error'" variant="flat" class="font-weight-bold rounded-pill">
                {{ item.is_active ? 'Activo' : 'Inactivo' }}
              </v-chip>
            </template>
            <template v-slot:item.actions="{ item }">
              <v-btn icon="mdi-pencil" size="x-small" variant="text" color="grey-darken-2" @click="openEditModal(item)" />
              <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" />
            </template>
          </v-data-table>

          <!-- Módulo: EQUIPOS -->
          <v-data-table
            v-else-if="activeTab === 'equipos'"
            :headers="teamHeaders"
            :items="teamsStore.teams"
            :loading="teamsStore.loading"
            :search="search"
            class="admin-data-table bg-white"
            density="comfortable"
          >
            <template v-slot:item.name="{ item }">
              <div class="d-flex align-center py-1">
                <v-avatar color="blue-darken-3" size="28" class="mr-2 text-white text-caption font-weight-bold">
                  {{ item.abbreviation || 'EQ' }}
                </v-avatar>
                <span class="font-weight-bold text-grey-darken-4">{{ item.name }}</span>
              </div>
            </template>
            <template v-slot:item.is_active="{ item }">
              <v-chip size="x-small" :color="item.is_active ? 'success' : 'error'" variant="flat" class="font-weight-bold rounded-pill">
                {{ item.is_active ? 'Activo' : 'Inactivo' }}
              </v-chip>
            </template>
            <template v-slot:item.actions="{ item }">
              <v-btn icon="mdi-pencil" size="x-small" variant="text" color="grey-darken-2" @click="openEditModal(item)" />
              <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" />
            </template>
          </v-data-table>

          <!-- Módulo: JUGADORES -->
          <v-data-table
            v-else-if="activeTab === 'jugadores'"
            :headers="playerHeaders"
            :items="playersStore.players"
            :loading="playersStore.loading"
            :search="search"
            class="admin-data-table bg-white"
            density="comfortable"
          >
            <template v-slot:item.full_name="{ item }">
              <span class="font-weight-bold text-grey-darken-4">
                {{ item.first_name }} {{ item.last_name }} {{ item.second_last_name || '' }}
              </span>
            </template>
            <template v-slot:item.curp="{ item }">
              <code class="text-caption bg-grey-lighten-3 px-2 py-1 rounded text-uppercase">{{ item.curp }}</code>
            </template>
            <template v-slot:item.is_active="{ item }">
              <v-chip size="x-small" :color="item.is_active ? 'success' : 'error'" variant="flat" class="font-weight-bold rounded-pill">
                {{ item.is_active ? 'Activo' : 'Inactivo' }}
              </v-chip>
            </template>
            <template v-slot:item.actions="{ item }">
              <v-btn icon="mdi-pencil" size="x-small" variant="text" color="grey-darken-2" @click="openEditModal(item)" />
              <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" />
            </template>
          </v-data-table>

          <!-- Módulo: TORNEOS -->
          <v-data-table
            v-else-if="activeTab === 'torneos'"
            :headers="tournamentHeaders"
            :items="tournamentsStore.tournaments"
            :loading="tournamentsStore.loading"
            :search="search"
            class="admin-data-table bg-white"
            density="comfortable"
          >
            <template v-slot:item.actions="{ item }">
              <v-btn icon="mdi-pencil" size="x-small" variant="text" color="grey-darken-2" @click="openEditModal(item)" />
              <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" />
            </template>
          </v-data-table>

          <!-- Módulo: BLOG DE NOTICIAS -->
          <div v-else-if="activeTab === 'blog'" class="pa-4 bg-white">
            <!-- Estado de carga -->
            <div v-if="blogStore.loading" class="d-flex justify-center pa-10">
              <v-progress-circular indeterminate color="primary" size="48"></v-progress-circular>
            </div>

            <!-- Estado sin datos -->
            <div v-else-if="blogStore.posts.length === 0" class="text-center pa-10 text-grey-darken-1 font-weight-medium">
              No hay noticias disponibles.
            </div>

            <!-- Vista de Mosaicos -->
            <v-row v-else>
              <v-col v-for="item in blogStore.posts" :key="item.id" cols="12" sm="6" md="4" lg="3">
                <v-card class="rounded-lg border bg-white d-flex flex-column h-100" elevation="0">
                  <v-img
                    :src="getCoverImage(item)"
                    height="180"
                    cover
                    class="bg-grey-lighten-3 position-relative"
                  >
                    <!-- Icono de Play si es un video -->
                    <div 
                      v-if="item.content_type?.slug === 'video'" 
                      class="d-flex fill-height align-center justify-center" 
                      style="background: rgba(0,0,0,0.3);"
                    >
                      <v-icon icon="mdi-play-circle" color="white" size="48" opacity="0.8"></v-icon>
                    </div>
                  </v-img>
                  
                  <v-card-text class="flex-grow-1 pa-4">
                    <h3 class="text-subtitle-1 font-weight-black text-grey-darken-4 mb-2" style="line-height: 1.2;">
                      {{ item.title }}
                    </h3>
                    <p class="text-caption text-grey-darken-1 line-clamp-3">
                      <!-- Limpiamos etiquetas HTML para el resumen rápido -->
                      {{ item.content?.replace(/<[^>]*>?/gm, '').substring(0, 100) || 'Sin descripción disponible para esta noticia.' }}...
                    </p>
                  </v-card-text>

                  <v-divider></v-divider>
                  
                  <!-- Acciones del panel de administración -->
                  <v-card-actions class="px-4 py-2 bg-grey-lighten-4">
                    <span class="text-caption font-weight-medium text-grey">
                      {{ formatCardDate(item.created_at) }}
                    </span>
                    <v-spacer></v-spacer>
                    <v-btn icon="mdi-pencil" size="small" variant="text" color="blue-darken-2" @click="openEditModal(item)"></v-btn>
                    <v-btn icon="mdi-delete" size="small" variant="text" color="error"></v-btn>
                  </v-card-actions>
                </v-card>
              </v-col>
            </v-row>
          </div>
        </v-card>
      </v-container>
    </v-main>

    <!-- COMPONENTES MODALES POR MÓDULO -->

    <!-- Modal de Usuarios -->
    <UserFormModal
      v-if="showUserModal"
      v-model="showUserModal"
      :user-data="selectedItem"
      :loading="usersStore.loading"
      @save="handleSaveUser"
    />

    <!-- Modal de Roles -->
    <RoleFormModal
      v-if="showRolModal"
      v-model="showRolModal"
      :rol-data="selectedItem"
      :loading="rolesStore.loading"
      @save="handleSaveRol"
    />

    <!-- Modal de Equipos -->
    <TeamFormModal
      v-if="showTeamModal"
      v-model="showTeamModal"
      :team-data="selectedItem"
      :loading="teamsStore.loading"
      @save="handleSaveTeam"
    />

    <!-- Modal de Jugadores -->
    <PlayerFormModal
      v-if="showPlayerModal"
      v-model="showPlayerModal"
      :player-data="selectedItem"
      :loading="playersStore.loading"
      @save="handleSavePlayer"
    />

    <!-- Modal de Blog -->
    <BlogFormModal
      v-if="showBlogModal"
      v-model="showBlogModal"
      :blog-data="selectedItem"
      :loading="blogStore.loading"
      @save="handleSaveBlog"
    />

  </v-app>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore } from '@/stores/users'
import { useRolesStore } from '@/stores/roles'
import { useTeamsStore } from '@/stores/teams'
import { usePlayersStore } from '@/stores/players'

// Asumo que crearás un store para el Blog similar a los demás
import { useBlogsStore } from '@/stores/blogs' 

// Importación de componentes modales dedicados
import UserFormModal from '@/components/UserFormModal.vue'
import RoleFormModal from '@/components/RoleFormModal.vue'
import TeamFormModal from '@/components/TeamFormModal.vue'
import PlayerFormModal from '@/components/PlayerFormModal.vue'
import BlogFormModal from '@/components/BlogFormModal.vue' // Importar componente BlogFormModal

const authStore = useAuthStore()
const usersStore = useUsersStore()
const rolesStore = useRolesStore()
const teamsStore = useTeamsStore() 
const playersStore = usePlayersStore()
const blogStore = useBlogsStore() // Instanciar el store del blog

// Mocks temporales para stores aún no creados
const tournamentsStore = ref({ tournaments: [], loading: false })

const router = useRouter()
const search = ref('')
const drawer = ref(true)
const totalItems = ref(0)
const activeTab = ref('usuarios')

const formatCardDate = (dateString) => {
  if (!dateString) return 'Fecha desconocida'
  
  const d = new Date(dateString)
  
  // Arreglos para traducir y capitalizar manualmente
  const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
  const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
  
  const dayName = days[d.getDay()]
  const day = d.getDate()
  const monthName = months[d.getMonth()]
  
  // Cálculo de hora en formato 12h
  let hours = d.getHours()
  const minutes = d.getMinutes().toString().padStart(2, '0')
  const ampm = hours >= 12 ? 'pm' : 'am'
  
  hours = hours % 12
  hours = hours ? hours : 12 // El 0 se convierte en 12
  
  return `${dayName} ${day} de ${monthName} ${hours}:${minutes}${ampm}`
}

// Control de visibilidad de Modales por Módulo
const showUserModal = ref(false)
const showRolModal = ref(false)
const selectedItem = ref(null)
const showTeamModal = ref(false)
const showPlayerModal = ref(false)
const showBlogModal = ref(false) // Nuevo estado para el modal de Blog

// Menú de navegación
const menuItems = ref([
  { id: 'usuarios', title: 'Usuarios', icon: 'mdi-account-group' },
  { id: 'roles', title: 'Roles', icon: 'mdi-card-account-details' },
  { id: 'equipos', title: 'Equipos', icon: 'mdi-shield-home' },
  { id: 'jugadores', title: 'Jugadores', icon: 'mdi-account-badge' },
  { id: 'torneos', title: 'Torneos', icon: 'mdi-trophy' },
  { id: 'blog', title: 'Blog de Noticias', icon: 'mdi-newspaper-variant-outline' }
])

const filteredMenuItems = computed(() => {
  const user = authStore.user;

  if (!user || !user.roles || user.roles.length === 0) {
    router.push('/') // Redirigir a la página principal
    return []
  }

  if (!user) return []
  if (user.roles[0].id == 1) return menuItems.value

  let allowedIds = []
  if (user.roles[0].id == 2) {
    allowedIds.push('equipos','jugadores','torneos','blog')
    activeTab.value = 'equipos';
  }
  else if (user.roles[0].id == 3) {
    allowedIds.push('blog')
    activeTab.value = 'blog';
  }
  return menuItems.value.filter(item => allowedIds.includes(item.id))
})

const currentModule = computed(() => {
  const found = filteredMenuItems.value.find(item => item.id === activeTab.value)
  return found || filteredMenuItems.value[0] || { id: '', title: 'Cargando...', icon: 'mdi-loading' }
})

// Headers
const userHeaders = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'name' },
  { title: 'Email', key: 'email' },
  { title: 'Estado', key: 'is_active', align: 'center' },
  { title: 'Acciones', key: 'actions', align: 'center', sortable: false }
]

const rolHeaders = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre del Perfil', key: 'name' },
  { title: 'Estado', key: 'is_active', align: 'center' },
  { title: 'Acciones', key: 'actions', align: 'center', sortable: false }
]

const teamHeaders = [
  { title: 'ID', key: 'id' },
  { title: 'Equipo', key: 'name' },
  { title: 'Nombre Corto', key: 'short_name' },
  { title: 'Abreviación', key: 'abbreviation' },
  { title: 'Estado', key: 'is_active', align: 'center' },
  { title: 'Acciones', key: 'actions', align: 'center', sortable: false }
]

const playerHeaders = [
  { title: 'ID', key: 'id' },
  { title: 'Jugador', key: 'full_name' },
  { title: 'CURP', key: 'curp' },
  { title: 'Fecha Nacimiento', key: 'birthdate' },
  { title: 'Estado', key: 'is_active', align: 'center' },
  { title: 'Acciones', key: 'actions', align: 'center', sortable: false }
]

const tournamentHeaders = [
  { title: 'ID', key: 'id' },
  { title: 'Torneo', key: 'title' },
  { title: 'Acciones', key: 'actions', align: 'center', sortable: false }
]

// Carga de datos por módulo
const loadModuleData = async (tabId) => {
  const user = authStore.user;

  if (user) {
    totalItems.value = 0
    switch (tabId) {
      case 'usuarios':
        await usersStore.fetchUsers()
        totalItems.value = usersStore.users.length
        break
      case 'roles':
        await rolesStore.fetchRoles()
        totalItems.value = rolesStore.roles.length
        break
      case 'equipos':
        await teamsStore.fetchTeams()
        totalItems.value = teamsStore.teams.length
        break
      case 'jugadores':
        await playersStore.fetchPlayers()
        totalItems.value = playersStore.players.length
        break
      case 'blog':
        // Asumiendo que crearás un método fetchPosts() en useBlogsStore
        await blogStore.fetchPosts() 
        totalItems.value = blogStore.posts.length
        break
    }
  }
}

watch(activeTab, (newTab) => {
  if (newTab) loadModuleData(newTab)
})

onMounted(() => {
  if (activeTab.value) loadModuleData(activeTab.value)
})

const openCreateModal = () => {
  selectedItem.value = null
  if (activeTab.value === 'usuarios') showUserModal.value = true
  else if (activeTab.value === 'roles') showRolModal.value = true
  else if (activeTab.value === 'equipos') showTeamModal.value = true
  else if (activeTab.value === 'jugadores') showPlayerModal.value = true
  else if (activeTab.value === 'blog') showBlogModal.value = true // Abrir modal de Blog
}

const openEditModal = (item) => {
  selectedItem.value = { ...item }
  if (activeTab.value === 'usuarios') showUserModal.value = true
  else if (activeTab.value === 'roles') showRolModal.value = true
  else if (activeTab.value === 'equipos') showTeamModal.value = true
  else if (activeTab.value === 'jugadores') showPlayerModal.value = true
  else if (activeTab.value === 'blog') showBlogModal.value = true // Abrir modal de Blog
}

// GUARDAR USUARIOS
const handleSaveUser = async ({ data, isEditing, closeModal }) => {
  try {
    if (isEditing) {
      await usersStore.updateUser(data.id, data)
    } else {
      await usersStore.createUser(data)
    }
    closeModal()
    totalItems.value = usersStore.users.length
  } catch (error) {
    alert('Ocurrió un error al guardar el usuario')
  }
}

// GUARDAR ROLES
const handleSaveRol = async ({ data, isEditing, closeModal }) => {
  try {
    if (isEditing) {
      await rolesStore.updateRol(data.id, data)
    } else {
      await rolesStore.createRol(data)
    }
    closeModal()
    totalItems.value = rolesStore.roles.length
  } catch (error) {
    alert('Ocurrió un error al guardar el perfil')
  }
}

// GUARDAR EQUIPOS
const handleSaveTeam = async ({ data, isEditing, closeModal }) => {
  try {
    if (isEditing) {
      await teamsStore.updateTeam(data.id, data)
    } else {
      await teamsStore.createTeam(data)
    }
    closeModal()
    totalItems.value = teamsStore.teams.length
  } catch (error) {
    alert('Ocurrió un error al guardar el equipo')
  }
}

// GUARDAR JUGADORES
const handleSavePlayer = async ({ data, isEditing, closeModal }) => {
  try {
    if (isEditing) {
      await playersStore.updatePlayer(data.id, data)
    } else {
      await playersStore.createPlayer(data)
    }
    closeModal()
    totalItems.value = playersStore.players.length
  } catch (error) {
    alert('Ocurrió un error al guardar el jugador. Revisa si la CURP no está duplicada.')
  }
}

const handleSaveBlog = async ({ data, isEditing, closeModal, onError }) => {
  try {
    if (isEditing) {
      await blogStore.updatePost(data.id, data)
    } else {
      await blogStore.createPost(data)
    }
    // Si tiene éxito, cerramos el modal
    closeModal()
    totalItems.value = blogStore.posts.length
  } catch (error) {
    let errorMessage = 'Ocurrió un error al guardar la noticia.'
    
    // Extraer el mensaje específico del objeto "errors" de Laravel (Código HTTP 422)
    if (error.response?.data?.errors) {
      const errorsObj = error.response.data.errors
      const firstKey = Object.keys(errorsObj)[0]
      errorMessage = errorsObj[firstKey][0] // Ejemplo: "The image field must not be greater than 2048 kilobytes."
    } 
    // Fallback para errores generales (ej. 500 Server Error)
    else if (error.response?.data?.message) {
      errorMessage = error.response.data.message
    }

    // Retornamos el error al modal sin cerrarlo
    if (onError) {
      onError(errorMessage)
    } else {
      alert(errorMessage)
    }
  }
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/')
}

const getCoverImage = (item) => {
  // Si es un video, intentar extraer la miniatura de YouTube
  if (item.content_type?.slug === 'video' && item.video_url) {
    const ytMatch = item.video_url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})/)
    if (ytMatch && ytMatch[1]) {
      // Retorna la miniatura de alta calidad de YouTube
      return `https://img.youtube.com/vi/${ytMatch[1]}/hqdefault.jpg`
    }
  }
  
  // Si es artículo o la URL del video no es de YouTube, retornar la imagen guardada o un placeholder
  return item.image_url || 'https://via.placeholder.com/400x225?text=Sin+Imagen'
}
</script>

<style scoped>
.bg-admin-grey {
  background-color: #f8fafc !important;
}

.border-bottom {
  border-bottom: 1px solid #e2e8f0 !important;
}

.border-end {
  border-end: 1px solid #e2e8f0 !important;
}

.admin-data-table :deep(.v-data-table__tr) {
  background-color: #ffffff !important;
  color: #212121 !important;
}

.admin-data-table :deep(th) {
  background-color: #f8fafc !important;
  font-weight: 700 !important;
  color: #475569 !important;
  border-bottom: 2px solid #e2e8f0 !important;
  height: 40px !important;
}

.admin-data-table :deep(td) {
  border-bottom: 1px solid #f1f5f9 !important;
  height: 52px !important;
}

.menu-container-mini {
  background-color: #f0f4f9 !important;
  border: 1px solid #e1e4e8;
}

.custom-menu-btn {
  background-color: #ffffff !important;
  border: 1px solid #e1e4e8 !important;
}

.text-xxs {
  font-size: 0.68rem !important;
}

.cursor-pointer {
  cursor: pointer;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>