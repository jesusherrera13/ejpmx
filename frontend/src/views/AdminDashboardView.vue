<template>
  <v-app class="bg-admin-grey">
    
    <!-- 1. BARRA SUPERIOR DEL PANEL -->
    <v-app-bar flat color="white" class="border-bottom px-4" height="64">
      <div class="font-weight-black text-grey-darken-4 text-h6 d-flex align-center flex-shrink-0 mr-6">
        <v-icon icon="mdi-shield-crown" color="primary" class="mr-2" size="26" />
        Panel de Administración
      </div>

      <!-- Navegación de módulos en el encabezado -->
      <nav class="d-none d-md-flex align-center fill-height">
        <a
          v-for="item in filteredMenuItems"
          :key="item.id"
          href="#"
          class="module-link"
          :class="{ 'module-link--active': activeTab === item.id }"
          @click.prevent="activeTab = item.id"
        >
          {{ item.title }}
        </a>
      </nav>

      <v-spacer></v-spacer>

      <!-- Navegación de módulos en pantallas pequeñas -->
      <v-menu location="bottom end">
        <template v-slot:activator="{ props }">
          <v-btn
            v-bind="props"
            variant="text"
            size="small"
            class="d-md-none text-capitalize font-weight-bold text-grey-darken-3 mr-2"
            append-icon="mdi-chevron-down"
          >
            {{ currentModule.title }}
          </v-btn>
        </template>
        <v-list density="compact" min-width="200" class="rounded-lg">
          <v-list-item
            v-for="item in filteredMenuItems"
            :key="item.id"
            :prepend-icon="item.icon"
            :title="item.title"
            :active="activeTab === item.id"
            color="primary"
            @click="activeTab = item.id"
          />
        </v-list>
      </v-menu>

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

    <!-- 2. ÁREA DE CONTENIDO PRINCIPAL -->
    <v-main>
      <v-container class="pa-6" fluid>

        <!-- Encabezado de la sección dinámica: título, búsqueda y acciones -->
        <div class="d-flex flex-wrap align-center ga-3 mb-4">
          <h1 class="text-subtitle-1 font-weight-bold text-grey-darken-4 mr-auto">
            {{ currentModule.title }}
          </h1>
          <!-- Calendario: se elige el torneo en lugar de buscar -->
          <v-select
            v-if="activeTab === 'calendario'"
            v-model="calendarTournamentId"
            :items="tournamentsStore.tournaments"
            item-title="name"
            item-value="id"
            placeholder="Selecciona un torneo"
            prepend-inner-icon="mdi-trophy-outline"
            variant="outlined"
            density="compact"
            hide-details
            :loading="tournamentsStore.loading"
            no-data-text="No hay torneos registrados"
            class="bg-white text-field-search"
          />
          <v-text-field
            v-else
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            placeholder="Buscar..."
            variant="outlined"
            density="compact"
            hide-details
            class="bg-white text-field-search"
          />
          <v-menu v-if="activeTab !== 'calendario'" location="bottom end">
            <template v-slot:activator="{ props }">
              <v-btn
                v-bind="props"
                color="black"
                size="small"
                class="text-capitalize rounded-pill font-weight-bold px-4"
                append-icon="mdi-dots-vertical"
                flat
              >
                Acciones
              </v-btn>
            </template>
            <v-list density="compact" min-width="180" class="rounded-lg">
              <v-list-item prepend-icon="mdi-plus" title="Nuevo" @click="openCreateModal" />
              <template v-if="currentImport">
                <v-divider class="my-1" />
                <v-list-item prepend-icon="mdi-file-download-outline" title="Descargar Plantilla" @click="handleDownloadTemplate" />
                <v-list-item prepend-icon="mdi-file-upload-outline" title="Cargar Plantilla" @click="showImportModal = true" />
              </template>
            </v-list>
          </v-menu>
        </div>

        <!-- TABLAS DE DATOS SEGÚN EL MÓDULO ACTIVO -->
        <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white overflow-hidden" theme="light">
          
          <!-- Módulo: USUARIOS -->
          <v-data-table
            v-if="activeTab === 'usuarios'"
            :headers="userHeaders"
            :items="filteredUsers"
            :loading="usersStore.loading"
            class="admin-data-table bg-white"
            density="compact"
          >
            <template v-slot:item.name="{ item }">
              <div class="d-flex align-center py-1">
                <div>
                  <div class="font-weight-bold text-black">
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
              <RowActionsMenu :can-delete="canDelete" @edit="openEditModal(item)" @delete="askDelete(item)" />
            </template>
          </v-data-table>

          <!-- Módulo: PERFILES -->
          <v-data-table
            v-else-if="activeTab === 'roles'"
            :headers="rolHeaders"
            :items="filteredRoles"
            :loading="rolesStore.loading"
            class="admin-data-table bg-white"
            density="compact"
          >
            <template v-slot:item.name="{ item }">
              <span class="font-weight-bold text-black">{{ item.name }}</span>
            </template>
            <template v-slot:item.is_active="{ item }">
              <v-chip size="x-small" :color="item.is_active ? 'success' : 'error'" variant="flat" class="font-weight-bold rounded-pill">
                {{ item.is_active ? 'Activo' : 'Inactivo' }}
              </v-chip>
            </template>
            <template v-slot:item.actions="{ item }">
              <RowActionsMenu :can-delete="canDelete" @edit="openEditModal(item)" @delete="askDelete(item)" />
            </template>
          </v-data-table>

          <!-- Módulo: EQUIPOS -->
          <v-data-table
            v-else-if="activeTab === 'equipos'"
            :headers="withOwnerColumn(teamHeaders)"
            :items="filteredTeams"
            :loading="teamsStore.loading"
            class="admin-data-table bg-white"
            density="compact"
          >
            <template v-slot:item.name="{ item }">
              <div class="d-flex align-center py-1">
                <v-avatar color="blue-darken-3" size="28" class="mr-2 text-white text-caption font-weight-bold">
                  {{ item.abbreviation || 'EQ' }}
                </v-avatar>
                <span class="font-weight-bold text-black">{{ item.name }}</span>
              </div>
            </template>
            <template v-slot:item.is_active="{ item }">
              <v-chip size="x-small" :color="item.is_active ? 'success' : 'error'" variant="flat" class="font-weight-bold rounded-pill">
                {{ item.is_active ? 'Activo' : 'Inactivo' }}
              </v-chip>
            </template>
            <template v-slot:item.actions="{ item }">
              <RowActionsMenu :can-delete="canDelete" @edit="openEditModal(item)" @delete="askDelete(item)" />
            </template>
          </v-data-table>

          <!-- Módulo: JUGADORES -->
          <v-data-table
            v-else-if="activeTab === 'jugadores'"
            :headers="withOwnerColumn(playerHeaders)"
            :items="filteredPlayers"
            :loading="playersStore.loading"
            class="admin-data-table bg-white"
            density="compact"
          >
            <template v-slot:item.full_name="{ item }">
              <span class="font-weight-bold text-black">
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
              <RowActionsMenu :can-delete="canDelete" @edit="openEditModal(item)" @delete="askDelete(item)" />
            </template>
          </v-data-table>

          <!-- Módulo: TORNEOS -->
          <v-data-table
            v-else-if="activeTab === 'torneos'"
            :headers="withOwnerColumn(tournamentHeaders)"
            :items="filteredTournaments"
            :loading="tournamentsStore.loading"
            class="admin-data-table bg-white"
            density="compact"
          >
            <template v-slot:item.name="{ item }">
              <span class="font-weight-bold text-black">{{ item.name }}</span>
            </template>
            <template v-slot:item.is_active="{ item }">
              <v-chip size="x-small" :color="item.is_active ? 'success' : 'error'" variant="flat" class="font-weight-bold rounded-pill">
                {{ item.is_active ? 'Activo' : 'Inactivo' }}
              </v-chip>
            </template>
            <template v-slot:item.actions="{ item }">
              <RowActionsMenu :can-delete="canDelete" @edit="openEditModal(item)" @delete="askDelete(item)">
                <v-list-item
                  prepend-icon="mdi-cog-outline"
                  title="Configuración"
                  :to="{ name: 'TournamentConfiguration', params: { id: item.id } }"
                />
              </RowActionsMenu>
            </template>
          </v-data-table>

          <!-- Módulo: CALENDARIO -->
          <div v-else-if="activeTab === 'calendario'" class="pa-4 bg-white">
            <TournamentSchedule
              :games="calendarTournamentId ? gamesStore.games : []"
              :byes="gamesStore.byes"
              :loading="gamesStore.loading"
              :empty-text="calendarTournamentId ? 'El torneo aún no tiene calendario. Genéralo desde Torneos > Configuración.' : 'Selecciona un torneo para ver sus partidos.'"
            />
          </div>

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
                    <RowActionsMenu :can-delete="canDelete" @edit="openEditModal(item)" @delete="askDelete(item)" />
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

    <!-- Modal de Carga de Plantilla (Equipos / Jugadores) -->
    <ImportTemplateModal
      v-if="showImportModal && currentImport"
      v-model="showImportModal"
      :title="currentImport.title"
      :hint="currentImport.hint"
      :columns="currentImport.columns"
      :example="currentImport.example"
      :importer="currentImport.store.importFile"
      :loading="currentImport.store.loading"
      @imported="totalItems = currentImport.count()"
    />

    <!-- Modal de Torneos -->
    <TournamentFormModal
      v-if="showTournamentModal"
      v-model="showTournamentModal"
      :tournament-data="selectedItem"
      :loading="tournamentsStore.loading"
      @save="handleSaveTournament"
    />

    <!-- Modal de Jugadores -->
    <PlayerFormModal
      v-if="showPlayerModal"
      v-model="showPlayerModal"
      :player-data="selectedItem"
      :loading="playersStore.loading"
      @save="handleSavePlayer"
    />

    <!-- Confirmación de Eliminación -->
    <v-dialog v-model="showDeleteDialog" max-width="420px" persistent>
      <v-card class="rounded-xl pa-2" theme="light">
        <v-card-title class="text-h6 font-weight-black text-grey-darken-4 pt-4 px-4">
          Eliminar registro
        </v-card-title>
        <v-card-text class="text-body-2 text-grey-darken-2">
          ¿Seguro que deseas eliminar <strong>{{ itemToDelete && deleteLabel(itemToDelete) }}</strong>? Esta acción no se puede deshacer.
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 justify-end ga-2">
          <v-btn
            variant="outlined"
            color="grey-darken-1"
            class="text-capitalize rounded-pill px-5"
            :disabled="isDeleting"
            @click="showDeleteDialog = false"
          >
            Cancelar
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            class="text-capitalize rounded-pill px-5 font-weight-bold"
            :loading="isDeleting"
            @click="confirmDelete"
          >
            Eliminar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

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
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore } from '@/stores/users'
import { useRolesStore } from '@/stores/roles'
import { useTeamsStore } from '@/stores/teams'
import { usePlayersStore } from '@/stores/players'
import { useTournamentsStore } from '@/stores/tournaments'
import { useGamesStore } from '@/stores/games'
import TournamentSchedule from '@/components/TournamentSchedule.vue'

// Asumo que crearás un store para el Blog similar a los demás
import { useBlogsStore } from '@/stores/blogs' 

// Importación de componentes modales dedicados
import UserFormModal from '@/components/UserFormModal.vue'
import RoleFormModal from '@/components/RoleFormModal.vue'
import TeamFormModal from '@/components/TeamFormModal.vue'
import ImportTemplateModal from '@/components/ImportTemplateModal.vue'
import RowActionsMenu from '@/components/RowActionsMenu.vue'
import { useNotificationsStore } from '@/stores/notifications'
import { getErrorMessage } from '@/utils/errors'
import PlayerFormModal from '@/components/PlayerFormModal.vue'
import TournamentFormModal from '@/components/TournamentFormModal.vue'
import BlogFormModal from '@/components/BlogFormModal.vue' // Importar componente BlogFormModal

const authStore = useAuthStore()
const usersStore = useUsersStore()
const rolesStore = useRolesStore()
const teamsStore = useTeamsStore() 
const playersStore = usePlayersStore()
const blogStore = useBlogsStore() // Instanciar el store del blog

const tournamentsStore = useTournamentsStore()
const gamesStore = useGamesStore()
const notify = useNotificationsStore()

const router = useRouter()
const route = useRoute()
const search = ref('')
const totalItems = ref(0)
// ?tab=torneos permite regresar a un módulo desde otra vista (p. ej. la configuración del torneo)
const activeTab = ref(route.query.tab || 'usuarios')

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
const showImportModal = ref(false)
const showPlayerModal = ref(false)
const showTournamentModal = ref(false)
const showBlogModal = ref(false) // Nuevo estado para el modal de Blog

// Menú de navegación
const menuItems = ref([
  { id: 'usuarios', title: 'Usuarios', icon: 'mdi-account-group' },
  { id: 'roles', title: 'Roles', icon: 'mdi-card-account-details' },
  { id: 'equipos', title: 'Equipos', icon: 'mdi-shield-home' },
  { id: 'jugadores', title: 'Jugadores', icon: 'mdi-account-badge' },
  { id: 'torneos', title: 'Torneos', icon: 'mdi-trophy' },
  { id: 'calendario', title: 'Calendario', icon: 'mdi-calendar-month' },
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
    allowedIds.push('equipos','jugadores','torneos','calendario','blog')
  }
  else if (user.roles[0].id == 3) {
    allowedIds.push('blog')
  }
  if (!allowedIds.includes(activeTab.value)) activeTab.value = allowedIds[0]
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
  { title: 'Torneo', key: 'name' },
  { title: 'Juegos por serie', key: 'games_per_series', align: 'center' },
  { title: 'Estado', key: 'is_active', align: 'center' },
  { title: 'Acciones', key: 'actions', align: 'center', sortable: false }
]

// Los administradores ven registros de todos los usuarios: se agrega la columna del propietario
const ADMIN_ROLES = ['Master', 'Administrador']
const isAdmin = computed(() => (authStore.user?.roles || []).some(role => ADMIN_ROLES.includes(role.name)))

const withOwnerColumn = (headers) => {
  if (!isAdmin.value) return headers
  const index = headers.findIndex(header => header.key === 'is_active')
  const ownerHeader = { title: 'Propietario', key: 'user.name' }
  return [...headers.slice(0, index), ownerHeader, ...headers.slice(index)]
}

// Búsqueda sobre el texto que se muestra en cada fila (sin distinguir mayúsculas ni acentos).
// Cada palabra buscada debe aparecer en algún campo: "liam lopez" encuentra "Liam Hicks López".
const normalizeText = (value) => String(value ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const statusLabel = (active) => (active ? 'Activo' : 'Inactivo')

const searchRows = (rows, fields) => {
  const terms = normalizeText(search.value).split(/\s+/).filter(Boolean)
  if (terms.length === 0) return rows
  return rows.filter(row => {
    const text = normalizeText(fields(row).join(' '))
    return terms.every(term => text.includes(term))
  })
}

const ownerName = (row) => (isAdmin.value ? row.user?.name : '')

const filteredUsers = computed(() => searchRows(usersStore.users, user => [
  user.id, user.name, user.username, user.email,
  user.roles?.[0]?.name || (user.is_superuser ? 'Superusuario' : 'Sin Rol'),
  statusLabel(user.is_active !== false)
]))

const filteredRoles = computed(() => searchRows(rolesStore.roles, rol => [
  rol.id, rol.name, statusLabel(rol.is_active)
]))

const filteredTeams = computed(() => searchRows(teamsStore.teams, team => [
  team.id, team.name, team.short_name, team.abbreviation, ownerName(team), statusLabel(team.is_active)
]))

const filteredPlayers = computed(() => searchRows(playersStore.players, player => [
  player.id, player.first_name, player.last_name, player.second_last_name,
  player.curp, player.birthdate, ownerName(player), statusLabel(player.is_active)
]))

const filteredTournaments = computed(() => searchRows(tournamentsStore.tournaments, tournament => [
  tournament.id, tournament.name, ownerName(tournament), statusLabel(tournament.is_active)
]))

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
      case 'torneos':
        await tournamentsStore.fetchTournaments()
        totalItems.value = tournamentsStore.tournaments.length
        break
      case 'calendario':
        await tournamentsStore.fetchTournaments()
        if (calendarTournamentId.value) await gamesStore.fetchGames(calendarTournamentId.value)
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

// Módulo Calendario: partidos del torneo seleccionado
const calendarTournamentId = ref(null)
watch(calendarTournamentId, (tournamentId) => {
  if (tournamentId) gamesStore.fetchGames(tournamentId)
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
  else if (activeTab.value === 'torneos') showTournamentModal.value = true
  else if (activeTab.value === 'blog') showBlogModal.value = true // Abrir modal de Blog
}

const openEditModal = (item) => {
  selectedItem.value = { ...item }
  if (activeTab.value === 'usuarios') showUserModal.value = true
  else if (activeTab.value === 'roles') showRolModal.value = true
  else if (activeTab.value === 'equipos') showTeamModal.value = true
  else if (activeTab.value === 'jugadores') showPlayerModal.value = true
  else if (activeTab.value === 'torneos') showTournamentModal.value = true
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
    notify.success(isEditing ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al guardar el usuario'))
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
    notify.success(isEditing ? 'Perfil actualizado correctamente' : 'Perfil creado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al guardar el perfil'))
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
    notify.success(isEditing ? 'Equipo actualizado correctamente' : 'Equipo creado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al guardar el equipo'))
  }
}

// CARGA MASIVA POR PLANTILLA: módulos que la soportan
const importConfigs = {
  equipos: {
    store: teamsStore,
    title: 'Cargar Plantilla de Equipos',
    hint: 'Si el nombre del equipo ya existe, el registro se actualizará.',
    columns: ['Nombre', 'Nombre Corto', 'Abreviación'],
    example: ['Atléticos de Oakland', 'Atléticos', 'OAK'],
    count: () => teamsStore.teams.length
  },
  jugadores: {
    store: playersStore,
    title: 'Cargar Plantilla de Jugadores',
    hint: 'Si la CURP ya existe, el jugador se actualizará. Fecha en formato aaaa-mm-dd.',
    columns: ['APELLIDO 1', 'APELLIDO 2', 'NOMBRE', 'CURP', 'FECHA DE NACIMIENTO'],
    example: ['Pérez', 'López', 'Juan', 'PELJ100515HDFRPN09', '2010-05-15'],
    count: () => playersStore.players.length
  },
  torneos: {
    store: tournamentsStore,
    title: 'Cargar Plantilla de Torneos',
    hint: 'Si el nombre del torneo ya existe, el registro se actualizará.',
    columns: ['Nombre de Torneo'],
    example: ['Liga Mexicana del Pacífico'],
    count: () => tournamentsStore.tournaments.length
  }
}

const currentImport = computed(() => importConfigs[activeTab.value] || null)

// ELIMINAR REGISTROS: módulos con borrado disponible en el backend
const deleteConfigs = {
  equipos: { store: teamsStore, label: item => item.name, count: () => teamsStore.teams.length },
  jugadores: {
    store: playersStore,
    label: item => [item.first_name, item.last_name, item.second_last_name].filter(Boolean).join(' '),
    count: () => playersStore.players.length
  },
  torneos: { store: tournamentsStore, label: item => item.name, count: () => tournamentsStore.tournaments.length },
  blog: { store: blogStore, label: item => item.title, count: () => blogStore.posts.length }
}

const canDelete = computed(() => !!deleteConfigs[activeTab.value])
const showDeleteDialog = ref(false)
const itemToDelete = ref(null)
const isDeleting = ref(false)

const deleteLabel = (item) => deleteConfigs[activeTab.value]?.label(item) || 'este registro'

const askDelete = (item) => {
  itemToDelete.value = item
  showDeleteDialog.value = true
}

const confirmDelete = async () => {
  const config = deleteConfigs[activeTab.value]
  if (!config || !itemToDelete.value) return
  isDeleting.value = true
  try {
    await config.store.deleteItem(itemToDelete.value.id)
    totalItems.value = config.count()
    showDeleteDialog.value = false
    itemToDelete.value = null
    notify.success('Registro eliminado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al eliminar el registro'))
  } finally {
    isDeleting.value = false
  }
}

// DESCARGAR PLANTILLA DEL MÓDULO ACTIVO
const handleDownloadTemplate = async () => {
  try {
    await currentImport.value.store.downloadTemplate()
    notify.success('Plantilla descargada')
  } catch (error) {
    notify.error('Ocurrió un error al descargar la plantilla')
  }
}

// GUARDAR TORNEOS
const handleSaveTournament = async ({ data, isEditing, closeModal }) => {
  try {
    if (isEditing) {
      await tournamentsStore.updateTournament(data.id, data)
    } else {
      await tournamentsStore.createTournament(data)
    }
    closeModal()
    totalItems.value = tournamentsStore.tournaments.length
    notify.success(isEditing ? 'Torneo actualizado correctamente' : 'Torneo creado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al guardar el torneo'))
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
    notify.success(isEditing ? 'Jugador actualizado correctamente' : 'Jugador creado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al guardar el jugador'))
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
    notify.success(isEditing ? 'Noticia actualizada correctamente' : 'Noticia publicada correctamente')
  } catch (error) {
    const errorMessage = getErrorMessage(error, 'Ocurrió un error al guardar la noticia.')

    // Retornamos el error al modal sin cerrarlo; además se avisa con el toast
    if (onError) onError(errorMessage)
    notify.error(errorMessage)
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

.module-link {
  display: flex;
  align-items: center;
  height: 100%;
  padding: 0 14px;
  font-size: 0.875rem;
  font-weight: 500;
  color: #64748b;
  text-decoration: none;
  border-bottom: 3px solid transparent;
  white-space: nowrap;
}

.module-link:hover {
  color: #0f172a;
}

.module-link--active {
  color: #0f172a;
  font-weight: 700;
  border-bottom-color: rgb(var(--v-theme-primary));
}

.text-field-search {
  flex: 0 1 320px;
  min-width: 200px;
}

.admin-data-table {
  font-size: 0.8125rem;
}

.admin-data-table :deep(.v-data-table__tr) {
  background-color: #ffffff !important;
  color: #212121 !important;
}

.admin-data-table :deep(tbody .v-data-table__tr:nth-child(even)) {
  background-color: #f4f7fb !important;
}

.admin-data-table :deep(th) {
  background-color: #f8fafc !important;
  font-size: 0.8125rem !important;
  font-weight: 700 !important;
  color: #475569 !important;
  border-bottom: 2px solid #e2e8f0 !important;
  height: 34px !important;
}

.admin-data-table :deep(td) {
  border-bottom: 1px solid #f1f5f9 !important;
  height: 30px !important;
}

.admin-data-table :deep(.v-data-table-footer) {
  font-size: 0.8125rem;
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