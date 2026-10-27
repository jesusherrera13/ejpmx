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
          Selecciona un archivo de Excel cuya primera fila tenga los siguientes encabezados.
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
          @click="handleImport"
        >
          Cargar
        </v-btn>
      </v-card-actions>
    </v-card>
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
  loading: Boolean
})

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

const handleImport = async () => {
  if (!file.value) return
  errorMessage.value = ''
  result.value = null

  try {
    // v-file-input puede regresar un File o un arreglo según la versión de Vuetify
    const selected = Array.isArray(file.value) ? file.value[0] : file.value
    result.value = await props.importer(selected)
    emit('imported', result.value)
    if (result.value.errors.length) notify.warning(`${result.value.message} Revisa las filas con errores.`)
    else notify.success(result.value.message)
  } catch (error) {
    errorMessage.value = getErrorMessage(error, 'Ocurrió un error al importar el archivo.')
    notify.error(errorMessage.value)
  }
}
</script>
