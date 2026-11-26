<template>
  <v-app class="bg-background">
    <v-app-bar flat color="brand-blue" class="text-white px-md-8" height="56" order="1">
      <router-link to="/" class="d-flex align-center text-white text-decoration-none mr-6">
        <span class="font-weight-black text-h6 tracking-wider">EL JUEGO PERFECTO</span>
        <span class="text-caption font-weight-medium ml-1">MX</span>
      </router-link>
      <v-spacer />
      <v-btn to="/" variant="text" class="text-none font-weight-bold" prepend-icon="mdi-arrow-left">
        Inicio
      </v-btn>
    </v-app-bar>

    <ScoresTicker />

    <v-main>
      <v-container class="py-8 px-4" style="max-width: 1100px;">
        <div class="d-flex flex-wrap align-center ga-3 mb-6">
          <h1 class="text-h5 font-weight-black mr-auto">Calendario y resultados</h1>

          <v-select
            v-if="store.tournaments.length > 1"
            :model-value="tournamentId"
            :items="store.tournaments"
            item-title="name"
            item-value="id"
            label="Torneo"
            variant="outlined"
            density="compact"
            hide-details
            class="filter-field"
            @update:model-value="selectTournament"
          />
          <div v-else-if="currentTournament" class="text-subtitle-1 font-weight-bold text-grey-darken-2">
            {{ currentTournament.name }}
          </div>

          <v-select
            v-if="currentTournament"
            :model-value="teamId"
            :items="currentTournament.teams"
            item-title="name"
            item-value="id"
            label="Equipo"
            placeholder="Todos los equipos"
            persistent-placeholder
            variant="outlined"
            density="compact"
            hide-details
            clearable
            class="filter-field"
            @update:model-value="selectTeam"
          />
        </div>

        <!-- Sin torneos públicos -->
        <v-card v-if="store.loaded && !store.tournaments.length" flat class="pa-8 text-center rounded-lg">
          <v-icon icon="mdi-baseball-diamond-outline" size="48" color="grey" class="mb-2" />
          <div class="text-subtitle-1 text-grey-darken-1">Aún no hay torneos publicados.</div>
        </v-card>

        <template v-else-if="tournamentId">
          <!-- Navegación por fecha: días con juego, calendario y Hoy -->
          <v-card flat class="rounded-lg mb-6 pa-2">
            <div class="d-flex align-center ga-1">
              <v-btn
                icon="mdi-chevron-left"
                variant="text"
                size="small"
                :disabled="!prevGameDay"
                title="Día anterior con juego"
                @click="goTo(prevGameDay)"
              />

              <div class="d-flex ga-1 flex-grow-1 justify-center overflow-hidden date-strip">
                <v-btn
                  v-for="day in strip"
                  :key="day"
                  :variant="day === date ? 'flat' : 'text'"
                  :color="day === date ? 'brand-blue' : undefined"
                  class="text-none date-chip"
                  :class="{ 'no-games': !gameDates.includes(day) }"
                  @click="goTo(day)"
                >
                  <div class="d-flex flex-column align-center line-height-tight">
                    <span class="text-xxs text-uppercase">{{ weekday(day) }}</span>
                    <span class="text-subtitle-2 font-weight-black">{{ parseDay(day).getDate() }}</span>
                    <span class="text-xxs text-uppercase">{{ monthShort(day) }}</span>
                  </div>
                </v-btn>
              </div>

              <v-btn
                icon="mdi-chevron-right"
                variant="text"
                size="small"
                :disabled="!nextGameDay"
                title="Día siguiente con juego"
                @click="goTo(nextGameDay)"
              />

              <v-divider vertical class="mx-1" />

              <v-menu v-model="calendarOpen" :close-on-content-click="false" location="bottom end">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon="mdi-calendar-month-outline" variant="text" size="small" title="Elegir fecha" />
                </template>
                <v-card class="pa-3" width="260">
                  <div class="text-caption text-grey-darken-1 mb-2">Ir a una fecha</div>
                  <v-text-field
                    :model-value="date"
                    type="date"
                    variant="outlined"
                    density="compact"
                    hide-details
                    :min="gameDates[0]"
                    :max="gameDates[gameDates.length - 1]"
                    @update:model-value="pickDate"
                  />
                </v-card>
              </v-menu>
              <v-btn variant="text" size="small" class="text-none" :disabled="date === today" @click="goTo(today)">Hoy</v-btn>
            </div>
          </v-card>

          <div class="text-subtitle-1 font-weight-bold mb-3">{{ longDate }}</div>

          <div v-if="loading" class="d-flex flex-column ga-3">
            <v-skeleton-loader v-for="n in 2" :key="n" type="list-item-two-line" class="rounded-lg" />
          </div>

          <v-alert v-else-if="failed" type="error" variant="tonal" class="rounded-lg">
            No se pudieron cargar los partidos.
            <template #append>
              <v-btn variant="text" size="small" @click="loadGames">Reintentar</v-btn>
            </template>
          </v-alert>

          <v-card v-else-if="!games.length" flat class="pa-8 text-center rounded-lg">
            <div class="text-subtitle-1 text-grey-darken-1 mb-3">No hay partidos{{ teamId ? ' de este equipo' : '' }} en esta fecha.</div>
            <div class="d-flex justify-center ga-2 flex-wrap">
              <v-btn v-if="prevGameDay" variant="outlined" class="text-none" prepend-icon="mdi-chevron-left" @click="goTo(prevGameDay)">
                {{ shortDate(prevGameDay) }}
              </v-btn>
              <v-btn v-if="nextGameDay" variant="outlined" class="text-none" append-icon="mdi-chevron-right" @click="goTo(nextGameDay)">
                {{ shortDate(nextGameDay) }}
              </v-btn>
            </div>
          </v-card>

          <!-- Partidos del día -->
          <div v-else class="d-flex flex-column ga-3">
            <v-card v-for="game in games" :key="game.id" flat class="rounded-lg game-result">
              <div class="d-flex align-center px-4 pt-3 pb-1 text-caption font-weight-bold">
                <span :class="statusClass(game)" class="d-flex align-center">
                  <span v-if="game.status === 'in_progress'" class="live-dot mr-1" />
                  {{ game.status === 'scheduled' ? formatTime(game.time) : STATUS_LABELS[game.status].toUpperCase() }}
                </span>
                <span class="text-grey ml-auto">
                  <template v-if="game.game_number">Partido #{{ game.game_number }}</template>
                  <template v-if="game.matchday"> · Jornada {{ game.matchday }}</template>
                </span>
              </div>

              <div class="overflow-x-auto px-2 pb-2">
                <table class="score-table">
                  <thead>
                    <tr>
                      <th class="text-left team-col" />
                      <template v-if="hasLine(game)">
                        <th v-for="inning in innings(game)" :key="inning" class="inning-col">{{ inning }}</th>
                      </template>
                      <th v-if="hasScore(game)" class="total-col">C</th>
                      <th v-if="hasLine(game)" class="total-col">H</th>
                      <th v-if="hasLine(game)" class="total-col">E</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="side in sides" :key="side" :class="{ 'text-grey-darken-1': isLoser(game, side) }">
                      <td class="team-col">
                        <div class="d-flex align-center ga-2">
                          <v-avatar size="26" :color="teamColor(game[side])">
                            <span class="avatar-text">{{ teamInitials(game[side]) }}</span>
                          </v-avatar>
                          <div class="overflow-hidden">
                            <div class="font-weight-bold text-truncate">{{ game[side].name }}</div>
                            <div class="text-xxs text-grey">{{ game[side].record }} · {{ side === 'away' ? 'Visitante' : 'Local' }}</div>
                          </div>
                        </div>
                      </td>
                      <template v-if="hasLine(game)">
                        <td v-for="inning in innings(game)" :key="inning" class="inning-col">
                          {{ inningValue(game, side, inning) }}
                        </td>
                      </template>
                      <td v-if="hasScore(game)" class="total-col font-weight-black">{{ game[side].runs ?? '–' }}</td>
                      <td v-if="hasLine(game)" class="total-col">{{ game[side].hits ?? '–' }}</td>
                      <td v-if="hasLine(game)" class="total-col">{{ game[side].errors ?? '–' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </v-card>
          </div>
        </template>
      </v-container>

      <SiteFooter />
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  usePublicGamesStore, todayString, parseDay, formatTime, teamColor, teamInitials,
  STATUS_LABELS, type PublicGame,
} from '@/stores/publicGames'
import ScoresTicker from '@/components/ScoresTicker.vue'
import SiteFooter from '@/components/SiteFooter.vue'

const route = useRoute()
const router = useRouter()
const store = usePublicGamesStore()

const sides = ['away', 'home'] as const
const today = todayString()

const gameDates = ref<string[]>([])
const games = ref<PublicGame[]>([])
const loading = ref(true)
const failed = ref(false)
const calendarOpen = ref(false)

// Estado en la URL (?torneo=&fecha=&equipo=) para poder compartir y regresar
const tournamentId = computed(() => Number(route.query.torneo) || null)
const teamId = computed(() => Number(route.query.equipo) || null)
const date = computed(() => {
  const value = String(route.query.fecha ?? '')
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : today
})

const currentTournament = computed(() => store.tournaments.find(t => t.id === tournamentId.value) ?? null)

const prevGameDay = computed(() => [...gameDates.value].reverse().find(day => day < date.value) ?? null)
const nextGameDay = computed(() => gameDates.value.find(day => day > date.value) ?? null)

// Hasta 7 días con juego alrededor de la fecha elegida (la fecha elegida siempre aparece)
const strip = computed(() => {
  const all = gameDates.value.includes(date.value) ? gameDates.value : [...gameDates.value, date.value].sort()
  const index = all.indexOf(date.value)
  const start = Math.max(0, Math.min(index - 3, all.length - 7))
  return all.slice(start, start + 7)
})

const weekday = (day: string) => parseDay(day).toLocaleDateString('es-MX', { weekday: 'short' }).replace('.', '')
const monthShort = (day: string) => parseDay(day).toLocaleDateString('es-MX', { month: 'short' }).replace('.', '')
const shortDate = (day: string) => parseDay(day).toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' })
const longDate = computed(() => {
  const text = parseDay(date.value).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  return text.charAt(0).toUpperCase() + text.slice(1)
})

const statusClass = (game: PublicGame) => ({
  'text-error': game.status === 'in_progress',
  'text-warning': ['postponed', 'suspended', 'cancelled'].includes(game.status),
  'text-grey-darken-1': ['scheduled', 'final'].includes(game.status),
})

const isLoser = (game: PublicGame, side: 'away' | 'home') => {
  if (game.status !== 'final') return false
  const other = side === 'away' ? 'home' : 'away'
  return (game[side].runs ?? 0) < (game[other].runs ?? 0)
}

const hasScore = (game: PublicGame) => game.away.runs !== null || game.home.runs !== null
const hasLine = (game: PublicGame) => Array.isArray(game.away.line) || Array.isArray(game.home.line)
const innings = (game: PublicGame) => {
  const count = Math.max(game.away.line?.length ?? 0, game.home.line?.length ?? 0, 7)
  return Array.from({ length: count }, (_, i) => i + 1)
}
const inningValue = (game: PublicGame, side: 'away' | 'home', inning: number) => {
  const value = game[side].line?.[inning - 1]
  // Entrada sin jugar (p. ej. la parte baja de la última con el local ganando)
  return value === null || value === undefined ? (side === 'home' && game.status === 'final' ? 'X' : '–') : value
}

// ---- Navegación (todo pasa por la URL) ----

const setQuery = (changes: Record<string, string | number | null | undefined>) => {
  const query = { ...route.query, ...changes }
  for (const key of Object.keys(query)) {
    if (query[key] === null || query[key] === undefined || query[key] === '') delete query[key]
  }
  router.replace({ name: 'Results', query })
}

const goTo = (day: string | null) => day && setQuery({ fecha: day })
const pickDate = (value: string) => {
  if (!value) return
  calendarOpen.value = false
  goTo(value)
}
const selectTeam = (id: number | null) => setQuery({ equipo: id })
const selectTournament = (id: number) => {
  store.rememberTournament(id)
  setQuery({ torneo: id, equipo: null, fecha: null })
}

// ---- Carga ----

const loadDates = async () => {
  if (!tournamentId.value) return
  try {
    gameDates.value = await store.fetchDates(tournamentId.value, teamId.value)
  } catch (error) {
    console.error('Error al cargar las fechas:', error)
    gameDates.value = []
  }
}

const loadGames = async () => {
  if (!tournamentId.value) return
  loading.value = true
  failed.value = false
  try {
    const page = await store.fetchGames(tournamentId.value, { date: date.value, team_id: teamId.value })
    games.value = page.games
  } catch (error) {
    console.error('Error al cargar los partidos:', error)
    games.value = []
    failed.value = true
  } finally {
    loading.value = false
  }
}

// Sin fecha en la URL: hoy si hay juego; si no, el siguiente día con juego (o el último jugado)
const defaultDate = () => gameDates.value.includes(today)
  ? today
  : gameDates.value.find(day => day > today) ?? gameDates.value[gameDates.value.length - 1] ?? today

const init = async () => {
  loading.value = true
  await store.fetchTournaments()
  if (!tournamentId.value || !currentTournament.value) {
    const id = store.defaultTournamentId()
    if (id) setQuery({ torneo: id, equipo: null })
    return
  }
  await loadDates()
  if (!route.query.fecha) {
    setQuery({ fecha: defaultDate() })
    return
  }
  loadGames()
}

watch(() => [route.query.torneo, route.query.equipo], (now, before) => {
  // Cambió el torneo o el equipo: se recargan los días con juego
  if (before && now[0] === before[0] && now[1] === before[1]) return
  init()
}, { immediate: true })

watch(() => route.query.fecha, (now, before) => {
  if (now && now !== before && tournamentId.value) loadGames()
})
</script>

<style scoped>
.filter-field {
  max-width: 260px;
  min-width: 200px;
}
.text-xxs {
  font-size: 0.65rem !important;
}
.line-height-tight {
  line-height: 1.15;
}
.date-chip {
  min-width: 56px !important;
  height: 58px !important;
  padding: 0 6px !important;
}
.date-chip.no-games {
  opacity: 0.55;
}
.avatar-text {
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
}
.score-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.score-table th {
  font-size: 0.7rem;
  font-weight: 700;
  color: #757575;
  padding: 4px 6px;
}
.score-table td {
  padding: 6px;
  border-top: 1px solid #eee;
}
.team-col {
  min-width: 200px;
  max-width: 320px;
}
.inning-col,
.total-col {
  text-align: center;
  width: 32px;
}
.total-col {
  background: #fafafa;
}
.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  animation: pulse 1.4s infinite;
}
@keyframes pulse {
  50% { opacity: 0.3; }
}
</style>
