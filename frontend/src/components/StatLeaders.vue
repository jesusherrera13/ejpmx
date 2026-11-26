<template>
  <!-- Líderes de un torneo al azar (como "Stat Leaders" de MLB): una categoría a la vez, top 5 -->
  <v-card flat class="pa-5 rounded-lg bg-white mb-6">
    <h2 class="text-h6 font-weight-black mb-1 text-grey-darken-4 d-flex align-center">
      <v-icon icon="mdi-podium-gold" class="mr-2" color="primary" />
      Líderes
      <v-spacer />
      <v-btn
        v-if="canShuffle"
        icon="mdi-shuffle-variant"
        variant="text"
        size="small"
        :loading="loading"
        title="Ver otro torneo"
        aria-label="Ver otro torneo"
        @click="load()"
      />
    </h2>
    <div class="text-caption text-grey-darken-1 mb-3 border-bottom pb-2 text-truncate">
      {{ data?.tournament?.name || (loading ? 'Cargando…' : '') }}
    </div>

    <div v-if="loading && !data" class="d-flex flex-column ga-2">
      <v-skeleton-loader v-for="n in 4" :key="n" type="list-item-avatar" />
    </div>

    <div v-else-if="!data?.tournament" class="text-body-2 text-grey-darken-1 py-2">
      Aún no hay torneos publicados.
    </div>

    <template v-else>
      <v-chip-group v-model="selectedKey" mandatory column selected-class="text-primary" class="mb-2">
        <v-chip
          v-for="category in data.categories"
          :key="category.key"
          :value="category.key"
          size="small"
          variant="outlined"
          :title="category.label"
        >
          {{ category.abbr }}
        </v-chip>
      </v-chip-group>

      <div class="text-subtitle-2 font-weight-bold text-grey-darken-3 mb-2">{{ selected?.label }}</div>

      <div v-if="!selected?.leaders.length" class="text-body-2 text-grey-darken-1 py-3">
        Todavía no hay estadísticas capturadas en este torneo.
      </div>

      <template v-else>
        <!-- Líder -->
        <div class="d-flex align-center ga-3 pa-3 rounded-lg leader-top mb-2">
          <v-avatar size="44" :color="teamColor(leader!.team)">
            <span class="text-white font-weight-bold text-caption">{{ initials(leader!) }}</span>
          </v-avatar>
          <div class="flex-grow-1 overflow-hidden">
            <div class="text-subtitle-2 font-weight-black text-truncate">{{ leader!.name }}</div>
            <div class="text-caption text-grey-darken-1 text-truncate">
              {{ teamLabel(leader!.team) }}<template v-if="leader!.position"> · {{ leader!.position }}</template>
            </div>
          </div>
          <div class="text-h5 font-weight-black text-primary">{{ leader!.display }}</div>
        </div>

        <!-- Del 2 al 5 -->
        <div
          v-for="(entry, index) in others"
          :key="`${entry.name}-${entry.team.id}`"
          class="d-flex align-center ga-3 py-2 px-1 border-bottom-dashed"
        >
          <span class="text-caption text-grey font-weight-bold rank">{{ index + 2 }}</span>
          <div class="flex-grow-1 overflow-hidden">
            <div class="text-body-2 font-weight-bold text-truncate">{{ entry.name }}</div>
            <div class="text-xxs text-grey-darken-1">{{ teamLabel(entry.team) }}</div>
          </div>
          <span class="text-body-2 font-weight-black">{{ entry.display }}</span>
        </div>
      </template>
    </template>
  </v-card>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { usePublicGamesStore, teamColor, teamLabel, type LeaderEntry, type PublicLeaders } from '@/stores/publicGames'

const store = usePublicGamesStore()

const data = ref<PublicLeaders | null>(null)
const loading = ref(true)
const selectedKey = ref<string>('avg')

const selected = computed(() => data.value?.categories.find(c => c.key === selectedKey.value) ?? data.value?.categories[0] ?? null)
const leader = computed(() => selected.value?.leaders[0] ?? null)
const others = computed(() => selected.value?.leaders.slice(1) ?? [])
const canShuffle = computed(() => store.tournaments.length > 1)

const initials = (entry: LeaderEntry) => entry.name.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase()

// Primera categoría con datos, para no abrir en una vacía
watch(data, value => {
  const withData = value?.categories.find(c => c.leaders.length)
  if (withData && !value?.categories.find(c => c.key === selectedKey.value)?.leaders.length) {
    selectedKey.value = withData.key
  }
})

const load = async () => {
  loading.value = true
  try {
    // Al barajar, evita repetir el torneo que ya se ve (si hay otro)
    const others = store.tournaments.filter(t => t.id !== data.value?.tournament?.id)
    const pick = data.value && others.length ? others[Math.floor(Math.random() * others.length)].id : null
    data.value = await store.fetchLeaders(pick)
  } catch (error) {
    console.error('Error al cargar los líderes:', error)
    data.value = { tournament: null, categories: [] }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  store.fetchTournaments()
  load()
})
</script>

<style scoped>
.text-xxs {
  font-size: 0.65rem !important;
}
.border-bottom {
  border-bottom: 1px solid #e0e0e0;
}
.border-bottom-dashed {
  border-bottom: 1px dashed #e0e0e0;
}
.leader-top {
  background: #f5f8ff;
}
.rank {
  width: 16px;
  text-align: center;
}
</style>
