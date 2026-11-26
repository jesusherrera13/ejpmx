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
      <v-container class="py-8 px-4" style="max-width: 1200px;">
        <div class="d-flex flex-wrap align-center ga-3 mb-6">
          <h1 class="text-h5 font-weight-black mr-auto">Posiciones</h1>

          <!-- Buscador de torneos: se escribe para filtrar o se elige de la lista -->
          <v-autocomplete
            :model-value="tournamentId"
            :items="store.tournaments"
            item-title="name"
            item-value="id"
            label="Buscar torneo"
            placeholder="Escribe el nombre del torneo"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            hide-details
            clearable
            auto-select-first
            no-data-text="Sin torneos con ese nombre"
            class="search-field"
            :loading="store.loading"
            @update:model-value="selectTournament"
          />
        </div>

        <!-- Torneo elegido: tabla completa -->
        <template v-if="tournamentId">
          <v-btn variant="text" size="small" class="text-none mb-2 px-1" prepend-icon="mdi-arrow-left" @click="selectTournament(null)">
            Ver varios torneos
          </v-btn>

          <v-skeleton-loader v-if="loading" type="table" class="rounded-lg" />

          <v-alert v-else-if="failed" type="error" variant="tonal" class="rounded-lg">
            No se pudieron cargar las posiciones de este torneo.
          </v-alert>

          <v-card v-else-if="detail" flat class="rounded-lg pa-4">
            <div class="d-flex flex-wrap align-center ga-2 mb-3">
              <div>
                <div class="text-h6 font-weight-black">{{ detail.tournament.name }}</div>
                <div class="text-caption text-grey-darken-1">
                  <template v-if="detail.games_played">
                    Al {{ longDate(detail.date) }} · {{ detail.games_played }} de {{ detail.games_total }} partidos jugados
                  </template>
                  <template v-else>
                    El torneo aún no comienza · {{ detail.games_total }} partidos programados
                  </template>
                </div>
              </div>
              <v-spacer />
              <v-btn
                variant="outlined"
                size="small"
                class="text-none"
                prepend-icon="mdi-calendar-month-outline"
                :to="{ name: 'Results', query: { torneo: detail.tournament.id } }"
              >
                Calendario y resultados
              </v-btn>
            </div>

            <div class="overflow-x-auto">
              <table class="full-table">
                <thead>
                  <tr>
                    <th class="team-cell">Equipo</th>
                    <th v-for="column in columns" :key="column.key" :title="column.title">{{ column.label }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, index) in detail.standings" :key="row.team.id">
                    <td class="team-cell">
                      <div class="d-flex align-center ga-2">
                        <span class="text-caption text-grey rank">{{ index + 1 }}</span>
                        <v-avatar size="24" :color="teamColor(row.team)">
                          <span class="avatar-text">{{ teamInitials(row.team) }}</span>
                        </v-avatar>
                        <span class="font-weight-bold text-truncate">{{ row.team.name }}</span>
                      </div>
                    </td>
                    <td v-for="column in columns" :key="column.key" :class="column.class">{{ column.value(row) }}</td>
                  </tr>
                  <tr v-if="!detail.standings.length">
                    <td :colspan="columns.length + 1" class="text-center text-grey py-6">Este torneo aún no tiene equipos inscritos.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="text-xxs text-grey mt-3">
              G ganados · P perdidos · DIF juegos detrás del líder · U10 últimos 10 · CA carreras anotadas ·
              CP carreras permitidas · DIF C diferencia de carreras · X-G/P récord esperado por carreras
            </div>
          </v-card>
        </template>

        <!-- Sin torneo elegido: algunos torneos al azar -->
        <template v-else>
          <div class="text-body-2 text-grey-darken-1 mb-4">
            Algunos torneos al azar. Busca un torneo arriba para ver su tabla completa.
          </div>

          <v-row v-if="loading">
            <v-col v-for="n in 3" :key="n" cols="12" sm="6" md="4">
              <v-skeleton-loader type="table-tbody" class="rounded-lg" />
            </v-col>
          </v-row>
          <v-card v-else-if="!featured.length" flat class="pa-8 text-center rounded-lg text-grey-darken-1">
            Aún no hay torneos publicados.
          </v-card>
          <v-row v-else>
            <v-col v-for="item in featured" :key="item.tournament.id" cols="12" sm="6" md="4">
              <StandingsCard :data="item" :limit="8" />
            </v-col>
          </v-row>

          <div v-if="store.tournaments.length > featured.length" class="text-center mt-4">
            <v-btn variant="text" class="text-none" prepend-icon="mdi-shuffle-variant" :loading="loading" @click="loadFeatured">
              Ver otros torneos
            </v-btn>
          </div>
        </template>
      </v-container>

      <SiteFooter />
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  usePublicGamesStore, parseDay, formatPct, formatGb, teamColor, teamInitials,
  type PublicStandings, type StandingRow,
} from '@/stores/publicGames'
import ScoresTicker from '@/components/ScoresTicker.vue'
import StandingsCard from '@/components/StandingsCard.vue'
import SiteFooter from '@/components/SiteFooter.vue'

const route = useRoute()
const router = useRouter()
const store = usePublicGamesStore()

const FEATURED_COUNT = 6

const featured = ref<PublicStandings[]>([])
const detail = ref<PublicStandings | null>(null)
const loading = ref(true)
const failed = ref(false)

const tournamentId = computed(() => Number(route.query.torneo) || null)

const columns: Array<{ key: string, label: string, title: string, class?: string, value: (row: StandingRow) => string | number }> = [
  { key: 'w', label: 'G', title: 'Ganados', value: row => row.w },
  { key: 'l', label: 'P', title: 'Perdidos', value: row => row.l },
  { key: 'pct', label: 'PCT', title: 'Porcentaje', class: 'font-weight-bold', value: row => formatPct(row.pct) },
  { key: 'gb', label: 'DIF', title: 'Juegos detrás del líder', value: row => formatGb(row.gb) },
  { key: 'last10', label: 'U10', title: 'Últimos 10', value: row => row.last10 },
  { key: 'streak', label: 'RACHA', title: 'Racha', value: row => row.streak ?? '-' },
  { key: 'rs', label: 'CA', title: 'Carreras anotadas', value: row => row.rs },
  { key: 'ra', label: 'CP', title: 'Carreras permitidas', value: row => row.ra },
  { key: 'diff', label: 'DIF C', title: 'Diferencia de carreras', value: row => (row.diff > 0 ? `+${row.diff}` : row.diff) },
  { key: 'expected', label: 'X-G/P', title: 'Récord esperado', value: row => row.expected ?? '-' },
  { key: 'home', label: 'CASA', title: 'Récord en casa', value: row => row.home },
  { key: 'away', label: 'VISITANTE', title: 'Récord de visitante', value: row => row.away },
]

const longDate = (day: string) => parseDay(day).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })

const selectTournament = (id: number | null) => {
  if (id) store.rememberTournament(id)
  router.replace({ name: 'Standings', query: id ? { torneo: id } : {} })
}

const loadFeatured = async () => {
  loading.value = true
  try {
    featured.value = await store.fetchFeaturedStandings(FEATURED_COUNT)
  } catch (error) {
    console.error('Error al cargar las posiciones:', error)
    featured.value = []
  } finally {
    loading.value = false
  }
}

const loadDetail = async (id: number) => {
  loading.value = true
  failed.value = false
  try {
    detail.value = await store.fetchStandings(id)
  } catch (error) {
    console.error('Error al cargar las posiciones del torneo:', error)
    detail.value = null
    failed.value = true
  } finally {
    loading.value = false
  }
}

watch(tournamentId, id => (id ? loadDetail(id) : loadFeatured()), { immediate: true })

onMounted(() => store.fetchTournaments())
</script>

<style scoped>
.search-field {
  min-width: 260px;
  max-width: 360px;
}
.text-xxs {
  font-size: 0.65rem !important;
}
.avatar-text {
  color: #fff;
  font-size: 0.55rem;
  font-weight: 700;
}
.rank {
  width: 16px;
  text-align: center;
}
.full-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.full-table th {
  font-size: 0.7rem;
  font-weight: 700;
  color: #757575;
  padding: 8px;
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  white-space: nowrap;
  text-align: center;
}
.full-table td {
  padding: 8px;
  border-bottom: 1px solid #f1f5f9;
  text-align: center;
  white-space: nowrap;
}
.full-table tbody tr:hover {
  background: #f8fafc;
}
.team-cell {
  text-align: left !important;
  min-width: 220px;
}
</style>
