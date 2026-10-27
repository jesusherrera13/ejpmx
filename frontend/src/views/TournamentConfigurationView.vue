<!-- src/views/TournamentConfigurationView.vue -->
<!-- Configuración de un torneo: equipos participantes y rol de juegos -->
<template>
  <v-app class="bg-admin-grey">

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
                <v-row dense align="center">
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field v-model="options.start_date" type="date" label="Fecha de inicio" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="6" sm="3" md="2">
                    <v-select v-model="options.rounds" :items="[1, 2, 3, 4]" label="Vueltas" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="6" sm="3" md="2">
                    <v-select v-model="options.games_per_series" :items="[1, 2, 3, 4, 5, 6, 7]" label="Juegos por serie" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="6" sm="3" md="2">
                    <v-select v-model="options.rest_days" :items="[0, 1, 2, 3, 4, 5, 6, 7]" label="Descanso entre series (días)" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="12" md="3" class="d-flex ga-2 justify-md-end">
                    <v-btn
                      color="black"
                      size="small"
                      variant="flat"
                      class="text-none font-weight-bold rounded-pill px-4"
                      prepend-icon="mdi-calendar-sync"
                      :loading="gamesStore.loading"
                      :disabled="teamsStore.teams.length < 2"
                      @click="hasCalendar ? (confirmAction = 'regenerate') : generateCalendar()"
                    >
                      {{ hasCalendar ? 'Regenerar' : 'Generar calendario' }}
                    </v-btn>
                    <v-btn
                      v-if="hasCalendar"
                      color="error"
                      size="small"
                      variant="outlined"
                      class="text-none font-weight-bold rounded-pill"
                      @click="confirmAction = 'clear'"
                    >
                      Eliminar
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

            <TournamentSchedule
              :games="gamesStore.games"
              :byes="gamesStore.byes"
              :loading="gamesStore.loading"
              default-view="series"
            />
          </v-window-item>
        </v-window>
      </v-container>
    </v-main>

    <!-- Agregar equipos -->
    <v-dialog v-model="showAddDialog" max-width="520px">
      <v-card class="rounded-xl pa-2" theme="light">
        <v-card-title class="text-h6 font-weight-black text-grey-darken-4 pt-4 px-4">
          Agregar equipos
        </v-card-title>
        <v-card-text>
          <v-autocomplete
            v-model="selectedTeamIds"
            :items="availableTeams"
            item-title="name"
            item-value="id"
            label="Equipos"
            variant="outlined"
            density="compact"
            multiple
            chips
            closable-chips
            :loading="allTeamsStore.loading"
            no-data-text="No hay más equipos disponibles"
          />
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 justify-end ga-2">
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
import { ref, computed, onMounted } from 'vue'
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

// Solo equipos activos del mismo propietario que el torneo y que aún no participan
const availableTeams = computed(() => {
  const enrolled = new Set(teamsStore.teams.map(team => team.id))
  return allTeamsStore.teams.filter(team =>
    team.is_active && !enrolled.has(team.id) && (!tournament.value || team.user_id === tournament.value.user_id)
  )
})

const openAddDialog = () => {
  selectedTeamIds.value = []
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
    games_per_series: gamesPerSeries,
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

const generateCalendar = async () => {
  try {
    await gamesStore.generate(props.id, options.value)
    notify.success('Calendario generado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al generar el calendario'))
  }
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
  regenerate: {
    title: 'Regenerar calendario',
    message: 'Se reemplazará el calendario actual por uno nuevo con los parámetros indicados.',
    button: 'Regenerar'
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
    } else if (confirmAction.value === 'regenerate') {
      await gamesStore.generate(props.id, options.value)
      notify.success('Calendario generado correctamente')
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
  } catch (error) {
    notify.error(getErrorMessage(error, 'No se encontró el torneo'))
    return
  }

  await Promise.all([teamsStore.fetchTeams(props.id), gamesStore.fetchGames(props.id)])
  syncOptionsFromGames()
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

@media (max-width: 600px) {
  .gh-row__meta,
  .gh-row__date {
    display: none;
  }
}
</style>
