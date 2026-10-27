<!-- src/components/TournamentSchedule.vue -->
<!-- Rol de juegos de un torneo agrupado por jornada, por series o por partido -->
<template>
  <div v-if="loading && games.length === 0" class="pa-6 d-flex justify-center">
    <v-progress-circular indeterminate color="primary" size="28" />
  </div>

  <div v-else-if="games.length === 0" class="schedule-box pa-8 text-center text-grey-darken-1 text-body-2">
    {{ emptyText }}
  </div>

  <div v-else>
    <!-- Resumen, vista y filtro por equipo -->
    <div class="d-flex flex-wrap align-center ga-3 mb-3">
      <div class="text-body-2 text-grey-darken-3 mr-auto">
        {{ games.length }} partidos en {{ matchdays.length }} jornadas,
        del {{ formatDate(games[0].scheduled_date) }}
        al {{ formatDate(games[games.length - 1].scheduled_date) }}.
      </div>
      <v-btn-toggle v-model="view" mandatory density="compact" variant="outlined" divided color="primary" class="bg-white">
        <v-btn value="games" size="small" class="text-none">Partidos</v-btn>
        <v-btn value="series" size="small" class="text-none">Series</v-btn>
      </v-btn-toggle>
      <v-select
        v-model="teamFilter"
        :items="teams"
        item-title="name"
        item-value="id"
        label="Filtrar por equipo"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="bg-white team-filter"
      />
    </div>

    <div class="schedule-box">
      <table class="schedule-table">
        <thead v-if="view === 'series'">
          <tr>
            <th>Serie</th>
            <th class="text-right">Visitante</th>
            <th class="text-center"></th>
            <th>Local</th>
            <th>Fechas</th>
            <th class="text-center">Juegos</th>
          </tr>
        </thead>
        <thead v-else>
          <tr>
            <th>#</th>
            <th>Fecha</th>
            <th class="text-right">Visitante</th>
            <th class="text-center"></th>
            <th>Local</th>
            <th class="text-center">Juego</th>
            <th class="text-center">Resultado</th>
          </tr>
        </thead>

        <tbody v-for="matchday in visibleMatchdays" :key="matchday.number">
          <tr class="schedule-table__group">
            <td :colspan="view === 'series' ? 6 : 7">
              <span class="font-weight-bold text-grey-darken-4">Jornada {{ matchday.number }}</span>
              <span class="text-grey-darken-1"> · Vuelta {{ matchday.round }} · {{ formatRange(matchday.start, matchday.end) }}</span>
              <span v-if="matchday.bye" class="text-grey-darken-1"> · Descansa <strong class="text-grey-darken-3">{{ matchday.bye.name }}</strong></span>
            </td>
          </tr>

          <template v-if="view === 'series'">
            <tr v-for="series in matchday.series" :key="series.number">
              <td class="text-grey-darken-1">{{ series.number }}</td>
              <td class="text-right font-weight-bold text-black">{{ series.away.name }}</td>
              <td class="text-center text-grey">@</td>
              <td class="font-weight-bold text-black">{{ series.home.name }}</td>
              <td class="text-grey-darken-2">{{ formatRange(series.start, series.end) }}</td>
              <td class="text-center text-grey-darken-2">{{ series.games.length }}</td>
            </tr>
          </template>

          <template v-else>
            <tr v-for="game in matchday.games" :key="game.id">
              <td class="text-grey-darken-1">{{ game.game_number }}</td>
              <td class="text-grey-darken-2 text-no-wrap">{{ formatDate(game.scheduled_date) }}</td>
              <td class="text-right font-weight-bold text-black">{{ game.away_team.name }}</td>
              <td class="text-center text-grey">@</td>
              <td class="font-weight-bold text-black">{{ game.home_team.name }}</td>
              <td class="text-center text-grey-darken-2">{{ game.series_game }} de {{ seriesSize[game.series] }}</td>
              <td class="text-center text-grey-darken-2">
                <template v-if="game.away_score !== null && game.home_score !== null">{{ game.away_score }} - {{ game.home_score }}</template>
                <span v-else class="text-grey">—</span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { formatDate, formatRange } from '@/utils/dates'

const props = defineProps({
  games: { type: Array, default: () => [] },
  // jornada => equipo que descansa
  byes: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  // 'games' (un renglón por partido) o 'series'
  defaultView: { type: String, default: 'games' },
  emptyText: { type: String, default: 'El torneo aún no tiene calendario.' }
})

const view = ref(props.defaultView)
const teamFilter = ref(null)

// Al cambiar de torneo se quita el filtro de equipo
watch(() => props.games, () => {
  if (teamFilter.value && !teams.value.some(team => team.id === teamFilter.value)) teamFilter.value = null
})

// Equipos que aparecen en el calendario, por nombre
const teams = computed(() => {
  const result = new Map()
  for (const game of props.games) {
    result.set(game.home_team.id, game.home_team)
    result.set(game.away_team.id, game.away_team)
  }
  return [...result.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const seriesSize = computed(() => {
  const result = {}
  for (const game of props.games) result[game.series] = (result[game.series] || 0) + 1
  return result
})

// Jornadas > series > partidos, a partir de la lista plana de partidos
const matchdays = computed(() => {
  const result = new Map()
  for (const game of props.games) {
    if (!result.has(game.matchday)) {
      result.set(game.matchday, {
        number: game.matchday,
        round: game.round,
        start: game.scheduled_date,
        end: game.scheduled_date,
        bye: props.byes[game.matchday] || null,
        games: [],
        series: new Map()
      })
    }
    const matchday = result.get(game.matchday)
    if (game.scheduled_date > matchday.end) matchday.end = game.scheduled_date
    matchday.games.push(game)

    if (!matchday.series.has(game.series)) {
      matchday.series.set(game.series, {
        number: game.series,
        home: game.home_team,
        away: game.away_team,
        start: game.scheduled_date,
        end: game.scheduled_date,
        games: []
      })
    }
    const series = matchday.series.get(game.series)
    if (game.scheduled_date > series.end) series.end = game.scheduled_date
    series.games.push(game)
  }

  // En la vista de partidos se ordenan por fecha dentro de la jornada
  return [...result.values()].map(matchday => ({
    ...matchday,
    games: matchday.games.sort((a, b) => a.scheduled_date.localeCompare(b.scheduled_date) || a.game_number - b.game_number),
    series: [...matchday.series.values()]
  }))
})

const playsIn = (item, teamId) => item.home_team_id === teamId || item.away_team_id === teamId

const visibleMatchdays = computed(() => {
  const teamId = teamFilter.value
  if (!teamId) return matchdays.value
  return matchdays.value
    .map(matchday => ({
      ...matchday,
      games: matchday.games.filter(game => playsIn(game, teamId)),
      series: matchday.series.filter(series => series.home.id === teamId || series.away.id === teamId)
    }))
    .filter(matchday => matchday.games.length > 0 || matchday.bye?.id === teamId)
})
</script>

<style scoped>
.schedule-box {
  background: #ffffff;
  border: 1px solid #d0d7de;
  border-radius: 6px;
  overflow: hidden;
}

.team-filter {
  flex: 0 1 280px;
  min-width: 200px;
}

.schedule-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.schedule-table th {
  padding: 8px 12px;
  background: #f8fafc;
  color: #475569;
  font-weight: 700;
  text-align: left;
  border-bottom: 2px solid #e2e8f0;
}

.schedule-table td {
  padding: 5px 12px;
  border-bottom: 1px solid #f1f5f9;
}

.schedule-table tbody tr:not(.schedule-table__group):nth-child(odd) {
  background: #f4f7fb;
}

.schedule-table__group td {
  background: #eef2f7;
  border-bottom: 1px solid #e2e8f0;
  padding-top: 7px;
  padding-bottom: 7px;
}
</style>
