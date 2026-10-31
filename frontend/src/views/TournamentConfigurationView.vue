<!-- src/views/TournamentConfigurationView.vue -->
<!-- Configuración de un torneo: equipos participantes y rol de juegos -->
<template>
  <!-- Tema claro fijo: con el tema del sistema en oscuro los campos quedaban blancos sobre blanco -->
  <v-app class="bg-admin-grey" theme="light">

    <!-- BARRA SUPERIOR -->
    <v-app-bar flat color="white" class="border-bottom px-4" height="64">
      <div class="font-weight-black text-grey-darken-4 text-h6 d-flex align-center">
        <v-icon icon="mdi-shield-crown" color="primary" class="mr-2" size="26" />
        Panel de Administración
      </div>

      <v-spacer></v-spacer>

      <v-btn
        :to="{ name: 'AdminDashboard', query: { tab: 'torneos' } }"
        variant="text"
        size="small"
        class="text-capitalize font-weight-bold text-grey-darken-2"
        prepend-icon="mdi-arrow-left"
      >
        Volver a Torneos
      </v-btn>
    </v-app-bar>

    <v-main>
      <v-container class="pa-6" fluid>

        <!-- Encabezado: Torneos / Nombre -->
        <div class="d-flex align-center mb-2">
          <v-icon icon="mdi-trophy" size="20" class="text-grey-darken-1 mr-2" />
          <router-link :to="{ name: 'AdminDashboard', query: { tab: 'torneos' } }" class="text-subtitle-1 text-primary text-decoration-none">
            Torneos
          </router-link>
          <span class="text-subtitle-1 text-grey mx-2">/</span>
          <h1 class="text-subtitle-1 font-weight-bold text-grey-darken-4">
            {{ tournament?.name || 'Cargando...' }}
          </h1>
        </div>

        <v-tabs v-model="tab" color="primary" density="compact" class="config-tabs mb-4">
          <v-tab value="equipos" class="text-none" prepend-icon="mdi-shield-home">
            Equipos
            <v-chip size="x-small" class="ml-2" variant="tonal">{{ teamsStore.teams.length }}</v-chip>
          </v-tab>
          <v-tab value="calendario" class="text-none" prepend-icon="mdi-calendar-month">
            Calendario
          </v-tab>
        </v-tabs>

        <v-window v-model="tab">

          <!-- PESTAÑA: EQUIPOS -->
          <v-window-item value="equipos">
            <v-alert
              v-if="hasCalendar"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-3 text-body-2"
            >
              El torneo ya tiene calendario. Para agregar o retirar equipos, elimina primero el calendario.
            </v-alert>

            <div class="gh-box">
              <div class="gh-box__header">
                <v-icon icon="mdi-shield-home" size="18" class="mr-2 text-grey-darken-1" />
                <span class="font-weight-bold text-grey-darken-4">{{ teamsStore.teams.length }}</span>
                <span class="ml-1 text-grey-darken-2">{{ teamsStore.teams.length === 1 ? 'equipo participante' : 'equipos participantes' }}</span>
                <v-spacer />
                <v-btn
                  color="success"
                  size="small"
                  variant="flat"
                  class="text-none font-weight-bold"
                  prepend-icon="mdi-plus"
                  :disabled="hasCalendar"
                  @click="openAddDialog"
                >
                  Agregar equipos
                </v-btn>
              </div>

              <div v-if="teamsStore.loading && teamsStore.teams.length === 0" class="pa-6 d-flex justify-center">
                <v-progress-circular indeterminate color="primary" size="28" />
              </div>

              <div v-else-if="teamsStore.teams.length === 0" class="pa-8 text-center text-grey-darken-1 text-body-2">
                Aún no hay equipos en este torneo. Usa <strong>Agregar equipos</strong> para inscribirlos.
              </div>

              <template v-else>
                <div v-for="team in teamsStore.teams" :key="team.id" class="gh-row">
                  <v-avatar color="blue-darken-3" size="24" class="mr-3 text-white team-abbr font-weight-bold">
                    {{ team.abbreviation }}
                  </v-avatar>
                  <span class="gh-row__name font-weight-bold text-black">{{ team.name }}</span>
                  <span class="gh-row__meta text-grey-darken-1">{{ team.short_name }}</span>
                  <span class="gh-row__date text-grey-darken-1">Inscrito {{ formatDate(team.pivot?.created_at) }}</span>
                  <v-btn
                    icon="mdi-close"
                    size="x-small"
                    variant="text"
                    color="grey-darken-1"
                    :disabled="hasCalendar"
                    title="Retirar del torneo"
                    @click="askRemoveTeam(team)"
                  />
                </div>
              </template>
            </div>
          </v-window-item>

          <!-- PESTAÑA: CALENDARIO -->
          <v-window-item value="calendario">
            <div class="gh-box mb-4">
              <div class="gh-box__header">
                <v-icon icon="mdi-cog-outline" size="18" class="mr-2 text-grey-darken-1" />
                <span class="font-weight-bold text-grey-darken-4">Parámetros del rol de juegos</span>
              </div>
              <div class="pa-4">
                <!-- Calendario guardado: los parámetros ya no se modifican -->
                <v-row v-if="hasCalendar" dense align="center">
                  <v-col v-for="param in savedParams" :key="param.label" cols="6" sm="3" md="2">
                    <div class="text-caption text-grey-darken-1">{{ param.label }}</div>
                    <div class="text-body-2 font-weight-bold text-grey-darken-4">{{ param.value }}</div>
                  </v-col>
                  <v-col cols="12" md="4" class="d-flex justify-md-end">
                    <v-menu location="bottom end">
                      <template v-slot:activator="{ props: menuProps }">
                        <v-btn
                          v-bind="menuProps"
                          color="black"
                          size="small"
                          variant="outlined"
                          class="text-none font-weight-bold rounded-pill"
                          append-icon="mdi-chevron-down"
                          :loading="isExporting"
                        >
                          Opciones
                        </v-btn>
                      </template>
                      <v-list density="compact" class="py-1">
                        <v-list-item prepend-icon="mdi-file-pdf-box" title="PDF Partidos" @click="exportCalendar('pdf', 'games')" />
                        <v-list-item prepend-icon="mdi-file-pdf-box" title="PDF Series" @click="exportCalendar('pdf', 'series')" />
                        <v-divider class="my-1" />
                        <v-list-item prepend-icon="mdi-microsoft-excel" title="XLS Partidos" @click="exportCalendar('xlsx', 'games')" />
                        <v-list-item prepend-icon="mdi-microsoft-excel" title="XLS Series" @click="exportCalendar('xlsx', 'series')" />
                        <v-divider class="my-1" />
                        <v-list-item prepend-icon="mdi-delete-outline" title="Eliminar" base-color="error" @click="confirmAction = 'clear'" />
                      </v-list>
                    </v-menu>
                  </v-col>
                </v-row>

                <v-row v-else dense align="center">
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field v-model="options.start_date" type="date" label="Fecha de inicio" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="6" sm="3" md="2">
                    <v-select v-model="options.rounds" :items="[1, 2, 3, 4]" label="Vueltas" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="6" sm="3" md="2">
                    <v-select v-model="options.games_per_series" :items="[1, 2, 3]" label="Juegos por serie" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="6" sm="3" md="2">
                    <v-select v-model="options.rest_days" :items="[0, 1, 2, 3, 4, 5, 6, 7]" label="Descanso entre series (días)" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="12" md="3" class="d-flex ga-2 justify-md-end">
                    <v-btn
                      v-if="previewData"
                      color="grey-darken-1"
                      size="small"
                      variant="outlined"
                      class="text-none font-weight-bold rounded-pill"
                      :disabled="gamesStore.loading"
                      @click="previewData = null"
                    >
                      Descartar
                    </v-btn>
                    <v-btn
                      v-if="previewData"
                      color="success"
                      size="small"
                      variant="flat"
                      class="text-none font-weight-bold rounded-pill px-4"
                      prepend-icon="mdi-content-save"
                      :loading="gamesStore.loading"
                      @click="saveCalendar"
                    >
                      Guardar calendario
                    </v-btn>
                    <v-btn
                      v-else
                      color="black"
                      size="small"
                      variant="flat"
                      class="text-none font-weight-bold rounded-pill px-4"
                      prepend-icon="mdi-calendar-sync"
                      :loading="gamesStore.loading"
                      :disabled="teamsStore.teams.length < 2"
                      @click="previewCalendar"
                    >
                      Generar calendario
                    </v-btn>
                  </v-col>
                </v-row>
                <div class="text-caption text-grey-darken-1 mt-3">
                  Todos contra todos: en cada jornada cada equipo juega una serie; con número impar de equipos uno descansa.
                  Local y visitante se alternan entre jornadas y se invierten en cada vuelta.
                  <span v-if="teamsStore.teams.length >= 2">
                    Con {{ teamsStore.teams.length }} equipos: {{ preview.matchdays }} jornadas, {{ preview.series }} series y {{ preview.games }} partidos.
                  </span>
                  <span v-else class="text-error">Inscribe al menos 2 equipos para generar el calendario.</span>
                </div>
              </div>
            </div>

            <v-alert
              v-if="previewData"
              type="warning"
              variant="tonal"
              density="compact"
              class="mb-3 text-body-2"
            >
              Vista previa con {{ options.games_per_series }} {{ options.games_per_series === 1 ? 'juego' : 'juegos' }} por serie. El calendario aún no se guarda: presiona <strong>Guardar calendario</strong> para confirmarlo.
            </v-alert>

            <TournamentSchedule
              :games="previewData ? previewData.games : gamesStore.games"
              :byes="previewData ? previewData.byes : gamesStore.byes"
              :show-actions="!previewData"
              @edit="editGame"
              @boxscore="openBoxscore"
              :loading="gamesStore.loading"
              default-view="series"
            />
          </v-window-item>
        </v-window>
      </v-container>
    </v-main>

    <!-- Agregar equipos -->
    <v-dialog v-model="showAddDialog" max-width="720px">
      <v-card class="rounded-xl pa-2" theme="light">
        <v-card-title class="text-h6 font-weight-black text-grey-darken-4 pt-4 px-4">
          Agregar equipos
        </v-card-title>
        <v-card-text>
          <v-text-field
            v-model="teamSearch"
            prepend-inner-icon="mdi-magnify"
            placeholder="Buscar equipo..."
            variant="outlined"
            density="compact"
            hide-details
            clearable
            class="mb-3"
          />
          <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white overflow-hidden">
            <v-data-table
              v-model="selectedTeamIds"
              :headers="availableTeamHeaders"
              :items="availableTeams"
              :search="teamSearch"
              :loading="allTeamsStore.loading"
              :items-per-page="10"
              item-value="id"
              show-select
              class="admin-data-table bg-white"
              density="compact"
              no-data-text="No hay más equipos disponibles"
            >
              <template v-slot:item.name="{ item }">
                <div class="d-flex align-center py-1">
                  <v-avatar color="blue-darken-3" size="24" class="mr-2 text-white team-abbr font-weight-bold">
                    {{ item.abbreviation || 'EQ' }}
                  </v-avatar>
                  <span class="font-weight-bold text-black">{{ item.name }}</span>
                </div>
              </template>
            </v-data-table>
          </v-card>
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 justify-end ga-2">
          <span class="text-body-2 text-grey-darken-1 mr-auto">
            {{ selectedTeamIds.length }} {{ selectedTeamIds.length === 1 ? 'equipo seleccionado' : 'equipos seleccionados' }}
          </span>
          <v-btn variant="outlined" color="grey-darken-1" class="text-capitalize rounded-pill px-5" @click="showAddDialog = false">
            Cancelar
          </v-btn>
          <v-btn
            color="success"
            variant="flat"
            class="text-capitalize rounded-pill px-5 font-weight-bold"
            :disabled="selectedTeamIds.length === 0"
            :loading="teamsStore.loading"
            @click="addTeams"
          >
            Agregar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirmaciones -->
    <v-dialog :model-value="!!confirmAction" max-width="420px" persistent>
      <v-card class="rounded-xl pa-2" theme="light">
        <v-card-title class="text-h6 font-weight-black text-grey-darken-4 pt-4 px-4">
          {{ confirmTexts[confirmAction]?.title }}
        </v-card-title>
        <v-card-text class="text-body-2 text-grey-darken-2">
          {{ confirmTexts[confirmAction]?.message }}
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 justify-end ga-2">
          <v-btn variant="outlined" color="grey-darken-1" class="text-capitalize rounded-pill px-5" :disabled="isConfirming" @click="confirmAction = null">
            Cancelar
          </v-btn>
          <v-btn color="error" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" :loading="isConfirming" @click="runConfirmAction">
            {{ confirmTexts[confirmAction]?.button }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-app>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useTournamentsStore } from '@/stores/tournaments'
import { useTournamentTeamsStore } from '@/stores/tournamentTeams'
import { useGamesStore } from '@/stores/games'
import { useTeamsStore } from '@/stores/teams'
import { useNotificationsStore } from '@/stores/notifications'
import { getErrorMessage } from '@/utils/errors'
import { formatDate, parseDate, toIsoDate } from '@/utils/dates'
import TournamentSchedule from '@/components/TournamentSchedule.vue'

const props = defineProps({
  id: { type: [String, Number], required: true }
})

const tournamentsStore = useTournamentsStore()
const teamsStore = useTournamentTeamsStore()
const gamesStore = useGamesStore()
const allTeamsStore = useTeamsStore()
const notify = useNotificationsStore()

const tournament = ref(null)
const tab = ref('equipos')
const hasCalendar = computed(() => gamesStore.games.length > 0)

// ---------- EQUIPOS ----------
const showAddDialog = ref(false)
const selectedTeamIds = ref([])
const teamSearch = ref('')

const availableTeamHeaders = [
  { title: 'Equipo', key: 'name' },
  { title: 'Nombre Corto', key: 'short_name' },
  { title: 'Abreviación', key: 'abbreviation' }
]

// Solo equipos activos del mismo propietario que el torneo y que aún no participan
const availableTeams = computed(() => {
  const enrolled = new Set(teamsStore.teams.map(team => team.id))
  return allTeamsStore.teams.filter(team =>
    team.is_active && !enrolled.has(team.id) && (!tournament.value || team.user_id === tournament.value.user_id)
  )
})

const openAddDialog = () => {
  selectedTeamIds.value = []
  teamSearch.value = ''
  showAddDialog.value = true
  if (allTeamsStore.teams.length === 0) allTeamsStore.fetchTeams()
}

const addTeams = async () => {
  try {
    await teamsStore.addTeams(props.id, selectedTeamIds.value)
    notify.success('Equipos agregados al torneo')
    showAddDialog.value = false
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al agregar los equipos'))
  }
}

const teamToRemove = ref(null)
const askRemoveTeam = (team) => {
  teamToRemove.value = team
  confirmAction.value = 'remove'
}

// ---------- CALENDARIO ----------
const options = ref({
  start_date: toIsoDate(new Date()),
  rounds: 2,
  games_per_series: 3,
  rest_days: 1
})

// Calendario calculado pero aún sin guardar
const previewData = ref(null)

// Al cambiar los juegos por serie se calcula la vista previa; con otros parámetros
// se recalcula solo si ya había una. Se activa hasta cargar los datos del torneo.
const watchOptions = () => watch(
  () => ({ ...options.value }),
  (current, previous) => {
    if (teamsStore.teams.length < 2) return
    if (current.games_per_series !== previous.games_per_series || previewData.value) previewCalendar()
  }
)

// Toma los parámetros con los que se generó el calendario actual
const syncOptionsFromGames = () => {
  const games = gamesStore.games
  if (games.length === 0) return
  const gamesPerSeries = Math.max(...games.map(game => game.series_game))
  const secondMatchday = games.find(game => game.matchday === 2)
  const daysBetween = secondMatchday ? Math.round((parseDate(secondMatchday.scheduled_date) - parseDate(games[0].scheduled_date)) / 86400000) : gamesPerSeries
  options.value = {
    start_date: games[0].scheduled_date,
    rounds: Math.max(...games.map(game => game.round)),
    // El combo arranca con el valor del registro del torneo
    games_per_series: tournament.value?.games_per_series ?? gamesPerSeries,
    rest_days: Math.max(0, daysBetween - gamesPerSeries)
  }
}

// Totales esperados antes de generar
const preview = computed(() => {
  const teams = teamsStore.teams.length
  const perRound = teams % 2 === 0 ? teams - 1 : teams
  const series = (teams * (teams - 1) / 2) * options.value.rounds
  return {
    matchdays: perRound * options.value.rounds,
    series,
    games: series * options.value.games_per_series
  }
})

const previewCalendar = async () => {
  try {
    previewData.value = await gamesStore.preview(props.id, options.value)
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al generar el calendario'))
  }
}

// Guarda el calendario y deja en el torneo los juegos por serie elegidos
const saveCalendar = async () => {
  try {
    await gamesStore.generate(props.id, options.value)
    tournament.value = { ...tournament.value, games_per_series: options.value.games_per_series }
    previewData.value = null
    notify.success('Calendario guardado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al guardar el calendario'))
  }
}

// Parámetros con que se guardó el calendario, como etiquetas
const savedParams = computed(() => [
  { label: 'Fecha de inicio', value: formatDate(options.value.start_date) },
  { label: 'Vueltas', value: options.value.rounds },
  { label: 'Juegos por serie', value: options.value.games_per_series },
  { label: 'Descanso entre series', value: `${options.value.rest_days} ${options.value.rest_days === 1 ? 'día' : 'días'}` }
])

const isExporting = ref(false)

// format: 'pdf' o 'xlsx'; view: 'games' (partidos) o 'series'
const exportCalendar = async (format, view) => {
  isExporting.value = true
  try {
    const name = (tournament.value?.name || 'torneo').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '_').toLowerCase()
    const prefix = view === 'series' ? 'calendario_series' : 'calendario_partidos'
    await gamesStore.exportCalendar(props.id, format, view, `${prefix}_${name}.${format}`)
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al generar el archivo'))
  } finally {
    isExporting.value = false
  }
}

// ---------- ACCIONES DE PARTIDOS ----------
// item: un partido (vista Partidos) o una serie con sus partidos (vista Series)
const editGame = (item) => {
  notify.warning(`Editar ${item.away_team?.name ?? item.away.name} @ ${item.home_team?.name ?? item.home.name}: próximamente`)
}

const openBoxscore = (item) => {
  notify.warning(`Boxscore de ${item.away_team?.name ?? item.away.name} @ ${item.home_team?.name ?? item.home.name}: próximamente`)
}

// ---------- CONFIRMACIONES ----------
const confirmAction = ref(null)
const isConfirming = ref(false)

const confirmTexts = computed(() => ({
  remove: {
    title: 'Retirar equipo',
    message: `¿Seguro que deseas retirar a ${teamToRemove.value?.name || 'este equipo'} del torneo?`,
    button: 'Retirar'
  },
  clear: {
    title: 'Eliminar calendario',
    message: '¿Seguro que deseas eliminar el calendario completo del torneo? Esta acción no se puede deshacer.',
    button: 'Eliminar'
  }
}))

const runConfirmAction = async () => {
  isConfirming.value = true
  try {
    if (confirmAction.value === 'remove') {
      await teamsStore.removeTeam(props.id, teamToRemove.value.id)
      notify.success('Equipo retirado del torneo')
    } else if (confirmAction.value === 'clear') {
      await gamesStore.clear(props.id)
      notify.success('Calendario eliminado correctamente')
    }
    confirmAction.value = null
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al realizar la acción'))
  } finally {
    isConfirming.value = false
  }
}

onMounted(async () => {
  // Limpiar datos de otro torneo abierto antes
  teamsStore.teams = []
  gamesStore.games = []
  gamesStore.byes = {}

  try {
    tournament.value = await tournamentsStore.fetchTournament(props.id)
    options.value.games_per_series = tournament.value.games_per_series ?? 3
  } catch (error) {
    notify.error(getErrorMessage(error, 'No se encontró el torneo'))
    return
  }

  await Promise.all([teamsStore.fetchTeams(props.id), gamesStore.fetchGames(props.id)])
  syncOptionsFromGames()
  watchOptions()
})
</script>

<style scoped>
.bg-admin-grey {
  background-color: #f8fafc !important;
}

.border-bottom {
  border-bottom: 1px solid #e2e8f0 !important;
}

.config-tabs {
  border-bottom: 1px solid #e2e8f0;
}

/* Contenedor estilo lista de archivos de GitHub */
.gh-box {
  background: #ffffff;
  border: 1px solid #d0d7de;
  border-radius: 6px;
  overflow: hidden;
}

.gh-box__header {
  display: flex;
  align-items: center;
  min-height: 48px;
  padding: 8px 16px;
  background: #f6f8fa;
  border-bottom: 1px solid #d0d7de;
  font-size: 0.875rem;
}

.gh-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  padding: 4px 8px 4px 16px;
  font-size: 0.8125rem;
}

.gh-row + .gh-row {
  border-top: 1px solid #eaeef2;
}

.gh-row:hover {
  background: #f6f8fa;
}

.gh-row__name {
  flex: 0 0 35%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gh-row__meta {
  flex: 1 1 auto;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gh-row__date {
  white-space: nowrap;
}

.team-abbr {
  font-size: 0.55rem !important;
}

/* Mismo estilo que las tablas de catálogos del panel */
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

@media (max-width: 600px) {
  .gh-row__meta,
  .gh-row__date {
    display: none;
  }
}
</style>
