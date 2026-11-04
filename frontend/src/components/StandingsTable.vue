<!-- src/components/StandingsTable.vue -->
<!-- Tabla de posiciones de un torneo (filas de GET /tournaments/{id}/standings) -->
<template>
  <div>
    <div v-if="loading" class="d-flex justify-center pa-10">
      <v-progress-circular indeterminate color="primary" size="40" />
    </div>

    <div v-else-if="!standings.length" class="text-center pa-10 text-grey-darken-1 font-weight-medium">
      {{ emptyText }}
    </div>

    <div v-else class="standings-scroll">
      <table class="standings-table">
        <thead>
          <tr>
            <th class="text-left team-col">Equipo</th>
            <th v-for="column in COLUMNS" :key="column.key" :title="column.title" :class="{ 'group-start': column.groupStart }">
              {{ column.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in standings" :key="row.team.id">
            <td class="team-col">
              <div class="d-flex align-center">
                <v-avatar color="blue-darken-3" size="26" class="mr-2 text-white team-abbr font-weight-bold">
                  {{ row.team.abbreviation }}
                </v-avatar>
                <span class="font-weight-bold text-grey-darken-4">{{ row.team.name }}</span>
              </div>
            </td>
            <td v-for="column in COLUMNS" :key="column.key" :class="[{ 'group-start': column.groupStart }, column.class?.(row)]">
              {{ column.value(row) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
defineProps({
  standings: { type: Array, default: () => [] },
  loading: Boolean,
  emptyText: { type: String, default: 'No hay equipos inscritos en este torneo.' }
})

// .875, 1.000; sin partidos "-"
const formatPct = pct => pct === null ? '-' : pct.toFixed(3).replace(/^0/, '')

// Juegos detrás del líder: "-" para el líder, 1.5, 2.0
const formatGb = gb => gb === 0 ? '-' : gb.toFixed(1)

const formatDiff = diff => diff > 0 ? `+${diff}` : String(diff)

// groupStart separa visualmente los grupos de columnas, como en la tabla de MLB
const COLUMNS = [
  { key: 'w', label: 'G', title: 'Ganados', value: row => row.w },
  { key: 'l', label: 'P', title: 'Perdidos', value: row => row.l },
  { key: 'pct', label: 'PCT', title: 'Porcentaje de victorias', value: row => formatPct(row.pct) },
  { key: 'gb', label: 'DIF.', title: 'Juegos detrás del líder', value: row => formatGb(row.gb) },
  { key: 'last10', label: 'U10', title: 'Récord en los últimos 10 partidos', value: row => row.last10, groupStart: true },
  { key: 'streak', label: 'RACHA', title: 'Racha actual: G ganados seguidos, P perdidos seguidos', value: row => row.streak || '-' },
  { key: 'rs', label: 'CA', title: 'Carreras anotadas', value: row => row.rs, groupStart: true },
  { key: 'ra', label: 'CP', title: 'Carreras permitidas', value: row => row.ra },
  {
    key: 'diff',
    label: 'DIF C',
    title: 'Diferencia de carreras',
    value: row => formatDiff(row.diff),
    class: row => row.diff > 0 ? 'text-success' : row.diff < 0 ? 'text-error' : ''
  },
  { key: 'expected', label: 'X-G/P', title: 'Récord esperado según carreras anotadas y permitidas (pitagórico)', value: row => row.expected || '-' },
  { key: 'home', label: 'CASA', title: 'Récord como local', value: row => row.home, groupStart: true },
  { key: 'away', label: 'VISITANTE', title: 'Récord como visitante', value: row => row.away },
  { key: 'vs_500', label: '≥.500', title: 'Récord contra equipos con .500 o mejor a la fecha', value: row => row.vs_500 }
]
</script>

<style scoped>
.standings-scroll {
  overflow-x: auto;
}

.standings-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
  white-space: nowrap;
}

.standings-table th {
  font-size: 0.72rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  padding: 10px 12px;
  border-bottom: 2px solid #e2e8f0;
  text-align: center;
}

.standings-table td {
  padding: 8px 12px;
  border-bottom: 1px solid #f1f5f9;
  text-align: center;
  color: #212121;
}

.standings-table tbody tr:nth-child(even) {
  background-color: #f4f7fb;
}

.standings-table .team-col {
  text-align: left;
  min-width: 220px;
}

.standings-table .group-start {
  border-left: 1px solid #e2e8f0;
}

.team-abbr {
  font-size: 0.55rem !important;
}
</style>
