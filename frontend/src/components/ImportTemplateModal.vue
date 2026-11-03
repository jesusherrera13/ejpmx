<!-- src/components/ImportTemplateModal.vue -->
<!-- Modal genérico para cargar una plantilla de Excel (equipos, jugadores, ...) -->
<template>
  <v-dialog v-model="dialog" max-width="800px" persistent>
    <v-card class="rounded-xl pa-2" theme="light">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <span class="text-h6 font-weight-black text-grey-darken-4">
          {{ title }}
        </span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" :disabled="loading" @click="closeModal" />
      </v-card-title>

      <v-divider class="my-2" />

      <v-card-text class="pt-2">
        <!-- Instrucciones -->
        <p class="text-body-2 text-grey-darken-2 mb-3">
          Selecciona un archivo de Excel; en cada hoja, la primera fila debe tener los siguientes encabezados.
          {{ hint }}
        </p>

        <v-table density="compact" class="border rounded-lg mb-5 text-caption">
          <thead>
            <tr>
              <th v-for="column in columns" :key="column" class="font-weight-bold text-no-wrap">{{ column }}</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td v-for="(value, i) in example" :key="i" class="text-no-wrap">{{ value }}</td>
            </tr>
          </tbody>
        </v-table>

        <!-- Archivo -->
        <v-file-input
          v-model="file"
          label="Archivo de Excel"
          accept=".xlsx,.xls,.csv"
          prepend-icon=""
          prepend-inner-icon="mdi-file-excel-outline"
          variant="outlined"
          density="comfortable"
          show-size
          :disabled="loading"
          :error-messages="errorMessage"
          @update:model-value="result = null; errorMessage = ''"
        />

        <!-- Resultado -->
        <template v-if="result">
          <v-alert
            :type="result.errors.length ? 'warning' : 'success'"
            variant="tonal"
            density="compact"
            class="text-body-2"
          >
            {{ result.message }}
          </v-alert>

          <v-list v-if="result.errors.length" density="compact" class="mt-2 text-caption text-error" max-height="160">
            <v-list-item v-for="(error, i) in result.errors" :key="i" class="px-1" min-height="24">
              {{ error }}
            </v-list-item>
          </v-list>
        </template>
      </v-card-text>

      <!-- Actions -->
      <v-card-actions class="pa-4 pt-0 justify-end ga-2">
        <v-btn
          variant="outlined"
          color="grey-darken-1"
          class="text-capitalize rounded-pill px-5"
          :disabled="loading"
          @click="closeModal"
        >
          {{ result ? 'Cerrar' : 'Cancelar' }}
        </v-btn>

        <v-btn
          color="black"
          variant="flat"
          class="text-capitalize rounded-pill px-5 font-weight-bold"
          prepend-icon="mdi-upload"
          :loading="loading"
          :disabled="!file"
          @click="previewer ? handlePreview() : handleImport()"
        >
          Cargar
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Vista previa: qué pasará con cada fila antes de guardar -->
    <v-dialog v-model="showPreview" max-width="1200px" persistent scrollable>
      <v-card class="rounded-xl pa-2" theme="light">
        <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
          <span class="text-h6 font-weight-black text-grey-darken-4">Vista previa</span>
          <v-btn icon="mdi-close" variant="text" density="comfortable" :disabled="loading" @click="showPreview = false" />
        </v-card-title>

        <v-divider class="my-2" />

        <v-card-text class="pt-2">
          <div class="d-flex flex-wrap align-center ga-2 mb-3">
            <v-chip
              v-for="(status, action) in ACTIONS"
              :key="action"
              size="small"
              label
              :color="status.color"
              :variant="actionFilter === action ? 'flat' : 'tonal'"
              :prepend-icon="status.icon"
              @click="actionFilter = actionFilter === action ? null : action"
            >
              {{ counts[action] }} {{ status.label }}
            </v-chip>
            <v-spacer />
            <v-text-field
              v-model="search"
              placeholder="Buscar en la vista previa..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              class="preview-search"
            />
          </div>

          <v-alert v-if="counts.skip" type="warning" variant="tonal" density="compact" class="text-body-2 mb-3">
            Las filas con errores no se guardarán ni actualizarán; el resto sí.
          </v-alert>

          <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white overflow-hidden">
            <v-data-table
              :headers="tableHeaders"
              :items="filteredRows"
              :search="search"
              :custom-filter="matchesSearch"
              :items-per-page="10"
              item-value="row"
              class="admin-data-table bg-white"
              density="compact"
              :no-data-text="search ? 'No hay filas que coincidan con la búsqueda.' : 'No hay filas con este estado.'"
            >
              <template v-slot:item.player_id="{ item }">
                <v-chip v-if="item.player_id" size="x-small" label color="primary" variant="tonal" title="Jugador encontrado por CURP">
                  #{{ item.player_id }}
                </v-chip>
                <span v-else class="text-grey">—</span>
              </template>
              <template v-slot:item.action="{ item }">
                <v-chip size="x-small" label :color="ACTIONS[item.action].color" :prepend-icon="ACTIONS[item.action].icon">
                  {{ ACTIONS[item.action].short }}
                </v-chip>
              </template>
              <template v-slot:item.errors="{ item }">
                <div v-for="(error, i) in item.errors" :key="i" class="text-caption text-error">{{ error }}</div>
              </template>
            </v-data-table>
          </v-card>
        </v-card-text>

        <v-card-actions class="pa-4 pt-0 justify-end ga-2">
          <v-btn
            variant="outlined"
            color="grey-darken-1"
            class="text-capitalize rounded-pill px-5"
            :disabled="loading"
            @click="showPreview = false"
          >
            Regresar
          </v-btn>
          <v-btn
            color="black"
            variant="flat"
            class="text-capitalize rounded-pill px-5 font-weight-bold"
            prepend-icon="mdi-check"
            :loading="loading"
            :disabled="!counts.create && !counts.update"
            @click="handleImport"
          >
            Confirmar carga
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useNotificationsStore } from '@/stores/notifications'
import { getErrorMessage } from '@/utils/errors'

const props = defineProps({
  modelValue: Boolean,
  title: String,
  hint: String,
  // Encabezados que debe traer el archivo y una fila de ejemplo
  columns: Array,
  example: Array,
  // Función async que recibe el File y regresa { message, created, updated, errors }
  importer: Function,
  // Opcional: función async que recibe el File y regresa { rows: [{ row, data, player_id, action, errors }] }
  // para mostrar la vista previa antes de importar; previewHeaders son las columnas de "data"
  previewer: Function,
  previewHeaders: { type: Array, default: () => [] },
  // Opcional: textos de los estados que reemplazan a los de ACTION_DEFAULTS, p. ej. { create: { label, short } }
  actionLabels: { type: Object, default: () => ({}) },
  loading: Boolean
})

// Qué pasará con cada fila de la vista previa
const ACTION_DEFAULTS = {
  create: { label: 'nuevos', short: 'Nuevo', color: 'success', icon: 'mdi-plus' },
  update: { label: 'por actualizar', short: 'Actualizar', color: 'primary', icon: 'mdi-pencil' },
  unchanged: { label: 'sin cambios', short: 'Sin cambios', color: 'grey', icon: 'mdi-equal' },
  skip: { label: 'con errores (se omiten)', short: 'Se omite', color: 'error', icon: 'mdi-alert' }
}

// Los estados con label en null no se muestran como filtro
const ACTIONS = Object.fromEntries(
  Object.entries(ACTION_DEFAULTS)
    .map(([action, status]) => [action, { ...status, ...props.actionLabels[action] }])
    .filter(([, status]) => status.label !== null)
)

const emit = defineEmits(['update:modelValue', 'imported'])

const notify = useNotificationsStore()

const file = ref(null)
const result = ref(null)
const errorMessage = ref('')

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const closeModal = () => {
  dialog.value = false
  file.value = null
  result.value = null
  errorMessage.value = ''
}

// v-file-input puede regresar un File o un arreglo según la versión de Vuetify
const selectedFile = () => (Array.isArray(file.value) ? file.value[0] : file.value)

const previewRows = ref([])
const showPreview = ref(false)
const actionFilter = ref(null)
const search = ref('')

// Búsqueda sin distinguir mayúsculas ni acentos ("gomez" encuentra "Gómez")
const normalize = (text) => String(text ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
const matchesSearch = (value, query) => normalize(value).includes(normalize(query).trim())

const tableHeaders = computed(() => [
  { title: 'Fila', key: 'row', width: 60 },
  ...props.previewHeaders,
  { title: 'ID', key: 'player_id', width: 70 },
  { title: 'Estado', key: 'action', width: 120 },
  { title: 'Observaciones', key: 'errors', sortable: false }
])

const counts = computed(() => {
  const totals = Object.fromEntries(Object.keys(ACTIONS).map(action => [action, 0]))
  previewRows.value.forEach(item => totals[item.action]++)
  return totals
})

const filteredRows = computed(() => previewRows.value.filter(item => !actionFilter.value || item.action === actionFilter.value))

const handlePreview = async () => {
  if (!file.value) return
  errorMessage.value = ''
  result.value = null

  try {
    const { rows } = await props.previewer(selectedFile())
    previewRows.value = rows.map(({ data, ...item }) => ({ ...data, ...item }))
    actionFilter.value = null
    search.value = ''
    showPreview.value = true
  } catch (error) {
    errorMessage.value = getErrorMessage(error, 'Ocurrió un error al leer el archivo.')
    notify.error(errorMessage.value)
  }
}

const handleImport = async () => {
  if (!file.value) return
  errorMessage.value = ''
  result.value = null

  try {
    result.value = await props.importer(selectedFile())
    showPreview.value = false
    emit('imported', result.value)
    if (result.value.errors.length) notify.warning(`${result.value.message} Revisa las filas con errores.`)
    else notify.success(result.value.message)
  } catch (error) {
    errorMessage.value = getErrorMessage(error, 'Ocurrió un error al importar el archivo.')
    notify.error(errorMessage.value)
  }
}
</script>

<style scoped>
.preview-search {
  max-width: 320px;
  min-width: 220px;
}
</style>
