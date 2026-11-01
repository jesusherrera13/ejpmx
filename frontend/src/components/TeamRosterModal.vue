<!-- src/components/TeamRosterModal.vue -->
<!-- Roster de un equipo en el torneo: a la izquierda sus jugadores, a la derecha los disponibles -->
<template>
  <v-dialog v-model="dialog" max-width="1200px" scrollable>
    <v-card class="rounded-xl pa-2" theme="light">
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <div class="d-flex align-center">
          <v-avatar v-if="team" color="blue-darken-3" size="32" class="mr-3 text-white team-abbr font-weight-bold">
            {{ team.abbreviation }}
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-black text-grey-darken-4">Roster de {{ team?.name }}</div>
            <div class="text-body-2 text-grey-darken-1">Jugadores del equipo en esta temporada</div>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="dialog = false" />
      </v-card-title>

      <v-divider class="my-2" />

      <v-card-text>
        <v-row>
          <!-- Roster -->
          <v-col cols="12" md="6">
            <div class="d-flex align-center mb-2" style="min-height: 40px">
              <v-icon icon="mdi-account-group" size="18" class="mr-2 text-grey-darken-1" />
              <span class="font-weight-bold text-grey-darken-4">{{ roster.length }}</span>
              <span class="ml-1 text-grey-darken-2">{{ roster.length === 1 ? 'jugador en el roster' : 'jugadores en el roster' }}</span>
            </div>
            <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white overflow-hidden">
              <v-data-table
                :headers="rosterHeaders"
                :items="roster"
                :loading="loading"
                :items-per-page="10"
                item-value="id"
                class="admin-data-table bg-white"
                density="compact"
                no-data-text="Aún no hay jugadores en el roster. Agrégalos desde la tabla de disponibles."
              >
                <template v-slot:item.name="{ item }">
                  <span class="font-weight-bold text-black">{{ fullName(item) }}</span>
                </template>
                <template v-slot:item.birthdate="{ item }">
                  {{ item.birthdate ? formatDate(item.birthdate) : '—' }}
                </template>
                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon="mdi-arrow-right"
                    size="x-small"
                    variant="text"
                    color="grey-darken-1"
                    title="Quitar del roster"
                    :disabled="saving"
                    @click="removePlayer(item)"
                  />
                </template>
              </v-data-table>
            </v-card>
          </v-col>

          <!-- Disponibles -->
          <v-col cols="12" md="6">
            <div class="d-flex align-center ga-2 mb-2" style="min-height: 40px">
              <v-text-field
                v-model="search"
                prepend-inner-icon="mdi-magnify"
                placeholder="Buscar jugador disponible..."
                variant="outlined"
                density="compact"
                hide-details
                clearable
              />
              <v-btn
                color="success"
                variant="flat"
                class="text-none font-weight-bold"
                prepend-icon="mdi-arrow-left"
                :disabled="selected.length === 0"
                :loading="saving"
                @click="addSelected"
              >
                Agregar ({{ selected.length }})
              </v-btn>
            </div>
            <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white overflow-hidden">
              <v-data-table
                v-model="selected"
                :headers="availableHeaders"
                :items="available"
                :search="search"
                :loading="loading"
                :items-per-page="10"
                item-value="id"
                show-select
                class="admin-data-table bg-white"
                density="compact"
                no-data-text="No hay jugadores disponibles: todos están en algún roster de este torneo o inactivos."
              >
                <template v-slot:item.name="{ item }">
                  <span class="font-weight-bold text-black">{{ fullName(item) }}</span>
                </template>
                <template v-slot:item.birthdate="{ item }">
                  {{ item.birthdate ? formatDate(item.birthdate) : '—' }}
                </template>
              </v-data-table>
            </v-card>
            <div class="text-caption text-grey-darken-1 mt-2">
              Se muestran tus jugadores activos que no están en el roster de ningún equipo de este torneo.
            </div>
          </v-col>
        </v-row>
      </v-card-text>

      <v-card-actions class="pa-4 pt-0 justify-end">
        <v-btn color="black" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" @click="dialog = false">
          Listo
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useTournamentTeamsStore } from '@/stores/tournamentTeams'
import { useNotificationsStore } from '@/stores/notifications'
import { getErrorMessage } from '@/utils/errors'
import { formatDate } from '@/utils/dates'

const props = defineProps({
  modelValue: Boolean,
  tournamentId: { type: [String, Number], required: true },
  team: Object
})

const emit = defineEmits(['update:modelValue'])

const teamsStore = useTournamentTeamsStore()
const notify = useNotificationsStore()

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const roster = ref([])
const available = ref([])
const selected = ref([])
const search = ref('')
const loading = ref(false)
const saving = ref(false)

// La búsqueda de la tabla usa el nombre completo y la CURP
const rosterHeaders = [
  { title: 'Jugador', key: 'name', value: item => fullName(item) },
  { title: 'CURP', key: 'curp' },
  { title: 'Nacimiento', key: 'birthdate' },
  { title: '', key: 'actions', align: 'end', sortable: false }
]

const availableHeaders = [
  { title: 'Jugador', key: 'name', value: item => fullName(item) },
  { title: 'CURP', key: 'curp' },
  { title: 'Nacimiento', key: 'birthdate' }
]

const fullName = player => [player.first_name, player.last_name, player.second_last_name].filter(Boolean).join(' ')

const apply = (data) => {
  roster.value = data.roster
  available.value = data.available
  selected.value = selected.value.filter(id => data.available.some(player => player.id === id))
}

// Se carga cada vez que se abre para el equipo elegido
watch(() => [props.modelValue, props.team?.id], async ([open]) => {
  if (!open || !props.team) return
  roster.value = []
  available.value = []
  selected.value = []
  search.value = ''
  loading.value = true
  try {
    apply(await teamsStore.fetchRoster(props.tournamentId, props.team.id))
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al cargar el roster'))
  } finally {
    loading.value = false
  }
}, { immediate: true })

const addSelected = async () => {
  saving.value = true
  try {
    const count = selected.value.length
    apply(await teamsStore.addRosterPlayers(props.tournamentId, props.team.id, selected.value))
    selected.value = []
    notify.success(count === 1 ? 'Jugador agregado al roster' : `${count} jugadores agregados al roster`)
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al agregar los jugadores'))
  } finally {
    saving.value = false
  }
}

const removePlayer = async (player) => {
  saving.value = true
  try {
    apply(await teamsStore.removeRosterPlayer(props.tournamentId, props.team.id, player.id))
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al quitar al jugador'))
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.team-abbr {
  font-size: 0.6rem !important;
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
