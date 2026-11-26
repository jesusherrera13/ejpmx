<template>
  <!-- Posiciones compactas de un torneo (G, P, %, DIF), como las tarjetas de división de MLB -->
  <v-card flat class="rounded-lg border pa-2 bg-white h-100 d-flex flex-column">
    <div class="d-flex align-center px-3 py-1 ga-2">
      <div class="text-subtitle-2 font-weight-bold text-grey-darken-3 text-truncate" :title="data.tournament.name">
        {{ data.tournament.name }}
      </div>
      <v-spacer />
      <span class="text-xxs text-grey flex-shrink-0">{{ data.games_played }}/{{ data.games_total }} JJ</span>
    </div>

    <v-table density="compact" class="standings-table flex-grow-1">
      <thead>
        <tr>
          <th class="text-left text-xxs font-weight-bold text-grey">EQUIPO</th>
          <th class="text-center text-xxs font-weight-bold text-grey">G</th>
          <th class="text-center text-xxs font-weight-bold text-grey">P</th>
          <th class="text-center text-xxs font-weight-bold text-grey">%</th>
          <th class="text-center text-xxs font-weight-bold text-grey">DIF</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.team.id">
          <td class="text-left font-weight-bold text-caption py-1">
            <div class="d-flex align-center">
              <v-avatar size="18" class="mr-2 flex-shrink-0" :color="teamColor(row.team)">
                <span class="text-xxs text-white font-weight-bold">{{ teamInitials(row.team).slice(0, 2) }}</span>
              </v-avatar>
              <span class="text-truncate" :title="row.team.name">{{ teamLabel(row.team) }}</span>
            </div>
          </td>
          <td class="text-center text-caption font-weight-medium">{{ row.w }}</td>
          <td class="text-center text-caption font-weight-medium">{{ row.l }}</td>
          <td class="text-center text-caption font-weight-medium text-grey-darken-1">{{ formatPct(row.pct) }}</td>
          <td class="text-center text-caption font-weight-medium text-grey-darken-1">{{ formatGb(row.gb) }}</td>
        </tr>
        <tr v-if="!rows.length">
          <td colspan="5" class="text-center text-caption text-grey py-4">Sin equipos inscritos</td>
        </tr>
      </tbody>
    </v-table>

    <div v-if="hiddenCount > 0 || showLink" class="d-flex align-center px-3 pt-1">
      <span v-if="hiddenCount > 0" class="text-xxs text-grey">+{{ hiddenCount }} equipos</span>
      <v-spacer />
      <v-btn
        v-if="showLink"
        variant="text"
        size="x-small"
        color="primary"
        class="text-none font-weight-bold"
        append-icon="mdi-chevron-right"
        :to="{ name: 'Standings', query: { torneo: data.tournament.id } }"
      >
        Tabla completa
      </v-btn>
    </div>
  </v-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  formatGb, formatPct, teamColor, teamInitials, teamLabel, type PublicStandings,
} from '@/stores/publicGames'

const props = withDefaults(defineProps<{
  data: PublicStandings
  limit?: number
  showLink?: boolean
}>(), {
  limit: 6,
  showLink: true,
})

const rows = computed(() => props.data.standings.slice(0, props.limit))
const hiddenCount = computed(() => Math.max(0, props.data.standings.length - props.limit))
</script>

<style scoped>
.text-xxs {
  font-size: 0.65rem !important;
}
.standings-table :deep(th) {
  height: 32px !important;
  background-color: #f8fafc !important;
  border-bottom: 2px solid #e2e8f0 !important;
}
.standings-table :deep(td) {
  height: 38px !important;
  border-bottom: 1px solid #f1f5f9 !important;
}
.standings-table tr:hover {
  background-color: #f8fafc;
}
.standings-table :deep(.v-table__wrapper) {
  overflow-x: hidden;
}
</style>
