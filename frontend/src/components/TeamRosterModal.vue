<!-- src/components/TeamRosterModal.vue -->
<!-- Roster de un equipo en el torneo: a la izquierda sus jugadores, a la derecha los disponibles -->
<template>
  <v-dialog v-model="dialog" max-width="1200px" scrollable>
    <v-card class="rounded-xl pa-2" theme="light">
      <!-- Bloquea el modal mientras se agrega o quita un jugador -->
      <v-overlay
        :model-value="saving"
        contained
        persistent
        scrim="white"
        opacity="0.7"
        class="align-center justify-center"
      >
        <div class="d-flex flex-column align-center">
          <v-progress-circular indeterminate color="blue-darken-3" size="48" width="4" />
          <div class="mt-3 font-weight-bold text-grey-darken-3">Actualizando roster...</div>
        </div>
      </v-overlay>

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
              <v-spacer />
              <v-btn
                variant="outlined"
                color="grey-darken-1"
                size="small"
                class="text-capitalize rounded-pill"
                prepend-icon="mdi-file-upload-outline"
                :disabled="loading || saving"
                @click="showImport = true"
              >
                Cargar desde Excel
              </v-btn>
            </div>
            <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white overflow-hidden">
              <v-data-table
                :headers="rosterHeaders"
                :items="roster"
                :loading="loading"
                :items-per-page="10"
                item-value="id"
                class="admin-data-table roster-table bg-white"
                density="compact"
                @dblclick:row="(event, { item }) => removePlayer(item)"
                no-data-text="Aún no hay jugadores en el roster. Agrégalos desde la tabla de disponibles."
              >
                <template v-slot:item.name="{ item }">
                  <span class="font-weight-bold text-black">{{ fullName(item) }}</span>
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
            </div>
            <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white overflow-hidden">
              <v-data-table
                :headers="availableHeaders"
                :items="available"
                :search="search"
                :custom-filter="matchesSearch"
                :loading="loading"
                :items-per-page="10"
                item-value="id"
                class="admin-data-table roster-table bg-white"
                density="compact"
                @dblclick:row="(event, { item }) => addPlayer(item)"
                no-data-text="No hay jugadores disponibles: todos están en algún roster de este torneo o inactivos."
              >
                <template v-slot:item.name="{ item }">
                  <span class="font-weight-bold text-black">{{ fullName(item) }}</span>
                </template>
                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon="mdi-arrow-left"
                    size="x-small"
                    variant="text"
                    color="success"
                    title="Agregar al roster"
                    :disabled="saving"
                    @click="addPlayer(item)"
                  />
                </template>
              </v-data-table>
            </v-card>
            <div class="text-caption text-grey-darken-1 mt-2">
              Se muestran tus jugadores activos que no están en el roster de ningún equipo de este torneo.
              Doble clic en un jugador para agregarlo o quitarlo del roster.
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

    <!-- Carga del roster por Excel: se identifica a cada jugador por su CURP -->
    <ImportTemplateModal
      v-if="showImport"
      v-model="showImport"
      :title="`Cargar roster de ${team?.name}`"
      hint="El jugador se identifica por su CURP; el nombre es solo de referencia. Cada CURP debe pertenecer a uno de tus jugadores activos que no esté en el roster de otro equipo de este torneo. Antes de guardar verás una vista previa."
      :columns="['NOMBRE_COMPLETO', 'CURP']"
      :example="['Juan Pérez López', 'PELJ100515HDFRPNA4']"
      :importer="importFile"
      :previewer="previewFile"
      :preview-headers="importPreviewHeaders"
      :preview-subtitle="`Roster de ${team?.name}`"
      :action-labels="importActionLabels"
      :loading="importing"
      @imported="apply"
    />
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useTournamentTeamsStore } from '@/stores/tournamentTeams'
import { useNotificationsStore } from '@/stores/notifications'
import { getErrorMessage } from '@/utils/errors'
import ImportTemplateModal from '@/components/ImportTemplateModal.vue'

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
const search = ref('')
const loading = ref(false)
const saving = ref(false)
const showImport = ref(false)
const importing = ref(false)

// La búsqueda de la tabla usa el nombre completo y la CURP
const rosterHeaders = [
  { title: 'Jugador', key: 'name', value: item => fullName(item) },
  { title: 'CURP', key: 'curp' },
  { title: '', key: 'actions', align: 'end', sortable: false }
]

// El icono para agregar va a la izquierda, del lado del roster
const availableHeaders = [
  { title: '', key: 'actions', sortable: false, width: 40 },
  { title: 'Jugador', key: 'name', value: item => fullName(item) },
  { title: 'CURP', key: 'curp' }
]

// Búsqueda indistinta a acentos y mayúsculas ("martinez diaz" encuentra "Martínez Díaz")
const normalize = text => String(text ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
const matchesSearch = (value, query) => normalize(value).includes(normalize(query).trim())

const fullName = player => [player.first_name, player.last_name, player.second_last_name].filter(Boolean).join(' ')

const apply = (data) => {
  roster.value = data.roster
  available.value = data.available
}

// Se carga cada vez que se abre para el equipo elegido
watch(() => [props.modelValue, props.team?.id], async ([open]) => {
  if (!open || !props.team) return
  roster.value = []
  available.value = []
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

const addPlayer = async (player) => {
  if (saving.value) return
  saving.value = true
  try {
    apply(await teamsStore.addRosterPlayers(props.tournamentId, props.team.id, [player.id]))
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al agregar al jugador'))
  } finally {
    saving.value = false
  }
}

const importPreviewHeaders = [
  { title: 'Jugador', key: 'name' },
  { title: 'CURP', key: 'curp' }
]

// En el roster no hay actualizaciones: "create" agrega y "unchanged" es que ya estaba
const importActionLabels = {
  create: { label: 'por agregar', short: 'Agregar' },
  update: { label: null },
  unchanged: { label: 'ya en el roster', short: 'Ya está' }
}

const withImporting = async (request) => {
  importing.value = true
  try {
    return await request()
  } finally {
    importing.value = false
  }
}

const previewFile = file => withImporting(() => teamsStore.previewRosterFile(props.tournamentId, props.team.id, file))
const importFile = file => withImporting(() => teamsStore.importRosterFile(props.tournamentId, props.team.id, file))

const removePlayer = async (player) => {
  if (saving.value) return
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

/* Doble clic agrega/quita: evita que el doble clic seleccione texto */
.roster-table :deep(tbody .v-data-table__tr) {
  cursor: pointer;
  user-select: none;
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
