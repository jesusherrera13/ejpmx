<template>
  <!-- Marcador superior: ventana de días con juego alrededor de hoy (como MLB/LMB); las flechas cargan más días -->
  <v-app-bar flat height="75" color="white" class="ticker-bar px-2 px-md-4" order="2">
    <div class="d-flex align-center w-100 overflow-hidden">

      <!-- Torneo (solo si hay más de uno público) -->
      <v-menu v-if="store.tournaments.length > 1" location="bottom start">
        <template #activator="{ props }">
          <v-btn
            v-bind="props"
            variant="text"
            size="small"
            class="text-none flex-shrink-0 mr-2 tournament-btn"
            append-icon="mdi-chevron-down"
          >
            <span class="text-truncate">{{ currentTournament?.name || 'Torneo' }}</span>
          </v-btn>
        </template>
        <v-list density="compact">
          <v-list-item
            v-for="tournament in store.tournaments"
            :key="tournament.id"
            :active="tournament.id === tournamentId"
            :title="tournament.name"
            @click="selectTournament(tournament.id)"
          />
        </v-list>
      </v-menu>

      <!-- Partidos -->
      <div
        ref="tickerContainer"
        class="d-flex ga-3 overflow-x-auto flex-grow-1 py-1 align-center hide-scrollbar scroll-smooth"
        @scroll.passive="onScroll"
      >
        <div v-if="loadingOlder" class="flex-shrink-0 px-2">
          <v-progress-circular indeterminate size="18" width="2" color="grey" />
        </div>

        <div v-if="!loading && !days.length" class="text-caption text-grey-darken-1 px-2">
          {{ emptyMessage }}
        </div>
        <div v-else-if="loading" class="d-flex ga-3">
          <v-skeleton-loader v-for="n in 6" :key="n" type="image" width="170" height="58" class="rounded" />
        </div>

        <template v-for="day in days" :key="day.date">
          <!-- Fecha -->
          <div :data-day="day.date" class="text-center mx-1 border-end pr-3 flex-shrink-0 date-block">
            <div v-if="day.date === today" class="text-xxs font-weight-black text-error line-height-tight">HOY</div>
            <div class="text-caption font-weight-bold text-black line-height-tight">{{ day.dayNumber }}</div>
            <div class="text-caption text-grey font-weight-bold line-height-tight">{{ day.month }}</div>
          </div>

          <!-- Partido -->
          <v-card
            v-for="game in day.games"
            :key="game.id"
            variant="outlined"
            color="grey-lighten-2"
            class="flex-shrink-0 game-card"
            width="170"
            :to="resultsRoute(game.date)"
          >
            <v-card-text class="pa-2 text-black">
              <div class="d-flex align-center text-xxs font-weight-bold mb-1" :class="statusClass(game)">
                <span v-if="game.status === 'in_progress'" class="live-dot mr-1" />
                {{ statusText(game) }}
              </div>

              <div
                v-for="side in sides"
                :key="side"
                class="d-flex align-center justify-space-between"
                :class="{ 'mb-1': side === 'away', 'text-grey-darken-1': isLoser(game, side) }"
              >
                <div class="d-flex align-center flex-grow-1 overflow-hidden">
                  <v-avatar size="16" class="mr-1 flex-shrink-0" :color="teamColor(game[side])">
                    <span class="avatar-text">{{ teamInitials(game[side]).charAt(0) }}</span>
                  </v-avatar>
                  <span class="text-caption font-weight-bold team-name-width text-truncate" :title="game[side].name ?? ''">
                    {{ teamLabel(game[side]) }}
                  </span>
                  <span class="text-xxs text-grey-darken-1 ml-1">{{ game[side].record }}</span>
                </div>
                <span v-if="game[side].runs !== null" class="text-subtitle-2 font-weight-black ml-2">{{ game[side].runs }}</span>
              </div>
            </v-card-text>
          </v-card>
        </template>

        <div v-if="loadingNewer" class="flex-shrink-0 px-2">
          <v-progress-circular indeterminate size="18" width="2" color="grey" />
        </div>
      </div>

      <!-- Navegación -->
      <div class="d-flex align-center ga-1 pl-2 border-start bg-white flex-shrink-0">
        <v-btn icon="mdi-chevron-left" variant="plain" size="small" :disabled="!canGoLeft" aria-label="Anteriores" @click="scrollTicker(-1)" />
        <v-btn icon="mdi-chevron-right" variant="plain" size="small" :disabled="!canGoRight" aria-label="Siguientes" @click="scrollTicker(1)" />
        <v-btn
          v-if="tournamentId"
          icon="mdi-calendar-month-outline"
          variant="plain"
          size="small"
          aria-label="Ver calendario y resultados"
          title="Calendario y resultados"
          :to="resultsRoute()"
        />
      </div>
    </div>
  </v-app-bar>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import {
  usePublicGamesStore, todayString, parseDay, formatTime, teamLabel, teamColor, teamInitials,
  STATUS_LABELS, type PublicGame,
} from '@/stores/publicGames'

const store = usePublicGamesStore()

const sides = ['away', 'home'] as const
const today = todayString()

// Días con juego que se muestran en la ventana (siempre en orden)
const LOAD_DAYS = 3
const games = ref<PublicGame[]>([])
const prevDate = ref<string | null>(null)
const nextDate = ref<string | null>(null)
const tournamentId = ref<number | null>(null)

const loading = ref(true)
const loadingOlder = ref(false)
const loadingNewer = ref(false)
const failed = ref(false)

const tickerContainer = ref<HTMLElement | null>(null)
const atStart = ref(true)
const atEnd = ref(true)

const currentTournament = computed(() => store.tournaments.find(t => t.id === tournamentId.value) ?? null)

const days = computed(() => {
  const byDay = new Map<string, PublicGame[]>()
  for (const game of games.value) {
    if (!byDay.has(game.date)) byDay.set(game.date, [])
    byDay.get(game.date)!.push(game)
  }
  return [...byDay.entries()].map(([date, dayGames]) => {
    const parsed = parseDay(date)
    return {
      date,
      dayNumber: parsed.getDate(),
      month: parsed.toLocaleDateString('es-MX', { month: 'short' }).replace('.', '').toUpperCase(),
      games: dayGames,
    }
  })
})

const emptyMessage = computed(() => {
  if (failed.value) return 'No se pudieron cargar los partidos.'
  if (!store.tournaments.length) return 'Próximamente: calendario y resultados de los torneos.'
  return 'Este torneo aún no tiene partidos programados.'
})

const canGoLeft = computed(() => !atStart.value || !!prevDate.value)
const canGoRight = computed(() => !atEnd.value || !!nextDate.value)

const statusText = (game: PublicGame) => game.status === 'scheduled'
  ? formatTime(game.time)
  : STATUS_LABELS[game.status].toUpperCase()

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

const resultsRoute = (date?: string) => ({
  name: 'Results',
  query: { torneo: tournamentId.value ?? undefined, fecha: date },
})

// ---- Carga ----

const load = async () => {
  if (!tournamentId.value) {
    loading.value = false
    return
  }
  loading.value = true
  failed.value = false
  try {
    const page = await store.fetchGames(tournamentId.value, { around: today, before: 2, after: LOAD_DAYS })
    games.value = page.games
    prevDate.value = page.prev_date
    nextDate.value = page.next_date
  } catch (error) {
    console.error('Error al cargar el marcador:', error)
    games.value = []
    failed.value = true
  } finally {
    loading.value = false
  }

  // Se coloca en hoy (o en el primer día por jugar)
  await nextTick()
  const container = tickerContainer.value
  if (container) {
    const target = days.value.find(day => day.date >= today)
    const element = target && container.querySelector<HTMLElement>(`[data-day="${target.date}"]`)
    container.classList.remove('scroll-smooth')
    container.scrollLeft = element ? element.offsetLeft - container.offsetLeft - 8 : container.scrollWidth
    container.classList.add('scroll-smooth')
  }
  updateEdges()
}

const loadOlder = async () => {
  if (!tournamentId.value || !prevDate.value || loadingOlder.value || !games.value.length) return
  loadingOlder.value = true
  const container = tickerContainer.value
  const widthBefore = container?.scrollWidth ?? 0
  try {
    const page = await store.fetchGames(tournamentId.value, { around: games.value[0].date, before: LOAD_DAYS, after: 0 })
    games.value = [...page.games, ...games.value]
    prevDate.value = page.prev_date
  } catch (error) {
    console.error('Error al cargar partidos anteriores:', error)
  } finally {
    loadingOlder.value = false
  }
  // Conserva lo que el visitante estaba viendo y luego avanza hacia los nuevos días
  await nextTick()
  if (container) {
    container.classList.remove('scroll-smooth')
    container.scrollLeft += container.scrollWidth - widthBefore
    container.classList.add('scroll-smooth')
    container.scrollLeft -= SCROLL_STEP
  }
}

const loadNewer = async () => {
  if (!tournamentId.value || !nextDate.value || loadingNewer.value) return
  loadingNewer.value = true
  try {
    const page = await store.fetchGames(tournamentId.value, { around: nextDate.value, before: 0, after: LOAD_DAYS })
    games.value = [...games.value, ...page.games]
    nextDate.value = page.next_date
  } catch (error) {
    console.error('Error al cargar partidos siguientes:', error)
  } finally {
    loadingNewer.value = false
  }
  await nextTick()
  if (tickerContainer.value) tickerContainer.value.scrollLeft += SCROLL_STEP
}

// ---- Desplazamiento ----

// Dos tarjetas (170px) más su separación
const SCROLL_STEP = 182 * 2

const updateEdges = () => {
  const container = tickerContainer.value
  if (!container) return
  atStart.value = container.scrollLeft <= 4
  atEnd.value = container.scrollLeft + container.clientWidth >= container.scrollWidth - 4
}

const onScroll = () => updateEdges()

const scrollTicker = (direction: -1 | 1) => {
  const container = tickerContainer.value
  if (!container) return
  if (direction < 0 && atStart.value) return void loadOlder()
  if (direction > 0 && atEnd.value) return void loadNewer()
  container.scrollLeft += direction * SCROLL_STEP
}

const selectTournament = (id: number) => {
  if (id === tournamentId.value) return
  tournamentId.value = id
  store.rememberTournament(id)
  load()
}

onMounted(async () => {
  await store.fetchTournaments()
  if (store.error) failed.value = true
  tournamentId.value = store.defaultTournamentId()
  load()
})
</script>

<style scoped>
.ticker-bar {
  border-bottom: 1px solid #e0e0e0 !important;
}
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scroll-smooth {
  scroll-behavior: smooth;
}
.text-xxs {
  font-size: 0.65rem !important;
}
.line-height-tight {
  line-height: 1.2;
}
.team-name-width {
  width: 38px;
}
.avatar-text {
  color: #fff;
  font-size: 0.55rem;
  font-weight: 700;
}
.tournament-btn {
  max-width: 180px;
}
.game-card {
  transition: border-color 0.15s;
}
.game-card:hover {
  border-color: rgb(var(--v-theme-primary)) !important;
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
