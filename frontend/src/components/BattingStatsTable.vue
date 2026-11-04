<!-- src/components/BattingStatsTable.vue -->
<!-- Estadísticas de bateo por jugador (filas de GET /tournaments/{id}/stats/batting) -->
<template>
  <div>
    <div class="d-flex flex-wrap align-center ga-3 pa-4 pb-2">
      <v-select
        v-model="teamId"
        :items="teams"
        item-title="name"
        item-value="id"
        placeholder="Todos los equipos"
        prepend-inner-icon="mdi-shield-outline"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="stats-filter"
      />
      <v-text-field
        v-model="search"
        placeholder="Buscar jugador..."
        prepend-inner-icon="mdi-magnify"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="stats-filter"
      />
    </div>

    <v-data-table
      v-model:sort-by="sortBy"
      :headers="HEADERS"
      :items="filteredPlayers"
      :search="search"
      :custom-filter="matchesSearch"
      :loading="loading"
      :items-per-page="25"
      item-value="key"
      class="admin-data-table stats-table bg-white"
      density="compact"
      :no-data-text="emptyText"
    >
      <template v-slot:item.name="{ item }">
        <span class="font-weight-bold text-black">{{ item.name }}</span>
        <span v-if="item.position" class="ml-2 text-grey-darken-1">{{ item.position }}</span>
      </template>
      <template v-slot:item.team="{ item }">
        <span :title="item.team.name">{{ item.team.abbreviation }}</span>
      </template>
      <template v-for="key in RATES" :key="key" v-slot:[`item.${key}`]="{ item }">
        {{ formatRate(item[key]) }}
      </template>
    </v-data-table>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  players: { type: Array, default: () => [] },
  loading: Boolean,
  emptyText: { type: String, default: 'No hay estadísticas a esta fecha: aún no se captura el bateo de ningún partido.' }
})

const RATES = ['avg', 'obp', 'slg', 'ops']

// Las estadísticas se ordenan de mayor a menor al primer clic
const stat = (title, key, description) => ({ title, key, align: 'end', sortable: true, headerProps: { title: description } })

const HEADERS = [
  { title: 'Jugador', key: 'name' },
  { title: 'Equipo', key: 'team', value: item => item.team.abbreviation },
  stat('J', 'g', 'Juegos'),
  stat('TB', 'ab', 'Turnos al bat'),
  stat('C', 'r', 'Carreras'),
  stat('H', 'h', 'Hits'),
  stat('2B', 'doubles', 'Dobles'),
  stat('3B', 'triples', 'Triples'),
  stat('HR', 'hr', 'Home runs'),
  stat('CI', 'rbi', 'Carreras impulsadas'),
  stat('BB', 'bb', 'Bases por bolas'),
  stat('P', 'so', 'Ponches'),
  stat('PRO', 'avg', 'Promedio de bateo (H / TB)'),
  stat('OBP', 'obp', 'Porcentaje de embase'),
  stat('SLG', 'slg', 'Slugging: bases totales / TB'),
  stat('OPS', 'ops', 'OBP + SLG')
]

const sortBy = ref([{ key: 'hr', order: 'desc' }])
// Equipo filtrado: v-model:team-id para que la descarga exporte lo mismo
const teamId = defineModel('teamId', { default: null })
const search = ref('')

// .421, 1.105; sin turnos "-"
const formatRate = value => value === null || value === undefined ? '-' : value.toFixed(3).replace(/^0/, '')

// Búsqueda indistinta a acentos y mayúsculas
const normalize = text => String(text ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
const matchesSearch = (value, query, item) => normalize(item.raw.name).includes(normalize(query).trim())

const teams = computed(() => {
  const unique = new Map(props.players.map(player => [player.team.id, player.team]))
  return [...unique.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const filteredPlayers = computed(() => props.players
  .filter(player => !teamId.value || player.team.id === teamId.value)
  .map(player => ({ ...player, key: `${player.player_id}-${player.team.id}` })))
</script>

<style scoped>
.stats-filter {
  flex: 0 1 280px;
  min-width: 200px;
}

.stats-table {
  white-space: nowrap;
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
</style>
