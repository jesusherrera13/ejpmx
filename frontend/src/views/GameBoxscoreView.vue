<!-- src/views/GameBoxscoreView.vue -->
<!-- Página del box score de un partido (antes era un modal en la configuración del torneo). Los cambios se autoguardan -->
<template>
  <!-- Tema claro fijo, como la configuración del torneo -->
  <v-app class="bg-admin-grey" theme="ejp">

    <!-- BARRA SUPERIOR -->
    <v-app-bar flat color="brand-blue" class="px-4" height="64">
      <div class="font-weight-black text-white text-h6 d-flex align-center">
        <v-icon icon="mdi-shield-crown" color="white" class="mr-2" size="26" />
        Panel de Administración
      </div>

      <v-spacer></v-spacer>

      <!-- Estado del autoguardado -->
      <div class="save-status text-white mr-3" :title="saveError || ''">
        <template v-if="saveState === 'saving'">
          <v-progress-circular indeterminate size="14" width="2" color="white" class="mr-1" />Guardando…
        </template>
        <template v-else-if="saveState === 'pending'">
          <v-icon icon="mdi-content-save-edit-outline" size="16" class="mr-1" />Cambios por guardar…
        </template>
        <template v-else-if="saveState === 'saved'">
          <v-icon icon="mdi-check-circle-outline" size="16" class="mr-1" />Guardado{{ savedAt ? ` · ${savedAt}` : '' }}
        </template>
        <template v-else-if="saveState === 'error'">
          <v-icon icon="mdi-alert-circle-outline" size="16" class="mr-1" />Error al guardar
          <v-btn variant="text" size="x-small" color="white" class="text-none font-weight-bold ml-1" @click="retrySave">Reintentar</v-btn>
        </template>
      </div>

      <v-btn
        variant="text"
        size="small"
        class="text-capitalize font-weight-bold text-white"
        prepend-icon="mdi-arrow-left"
        :loading="leaving"
        @click="goBack"
      >
        Volver al torneo
      </v-btn>
    </v-app-bar>

    <v-main>
      <v-container class="pa-6" fluid>
        <!-- Encabezado: Torneos / Calendario del torneo / Box score -->
        <div class="d-flex align-center mb-4">
          <v-icon icon="mdi-trophy" size="20" class="text-grey-darken-1 mr-2" />
          <router-link :to="{ name: 'AdminDashboard', query: { tab: 'torneos' } }" class="text-subtitle-1 text-primary text-decoration-none">
            Torneos
          </router-link>
          <span class="text-subtitle-1 text-grey mx-2">/</span>
          <router-link :to="tournamentRoute" class="text-subtitle-1 text-primary text-decoration-none">
            Calendario del torneo
          </router-link>
          <span class="text-subtitle-1 text-grey mx-2">/</span>
          <h1 class="text-subtitle-1 font-weight-bold text-grey-darken-4">Box Score</h1>
        </div>

        <v-alert v-if="loadError" type="error" variant="tonal" class="mb-4">
          {{ loadError }}
          <template v-slot:append>
            <v-btn variant="outlined" size="small" class="text-none" @click="loadBoxscore">Reintentar</v-btn>
          </template>
        </v-alert>

        <v-alert v-if="saveState === 'error'" type="error" variant="tonal" density="compact" class="mb-4 text-body-2">
          No se pudo guardar el último cambio: {{ saveError }} Lo capturado se conserva en la página; corrígelo o usa Reintentar.
          <template v-slot:append>
            <v-btn variant="outlined" size="small" class="text-none" @click="retrySave">Reintentar</v-btn>
          </template>
        </v-alert>

        <BoxscoreEditor
          v-if="!loadError"
          ref="editor"
          :boxscore="boxscoreData"
          :loading="isLoading"
          :saving="saveState === 'saving'"
          @change="queueSave"
        />
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, onBeforeRouteLeave } from 'vue-router'
import BoxscoreEditor from '@/components/BoxscoreEditor.vue'
import { useGamesStore } from '@/stores/games'
import { getErrorMessage } from '@/utils/errors'

const props = defineProps({
  // Torneo (mismo nombre de parámetro que la ruta TournamentConfiguration) y partido
  id: { type: [String, Number], required: true },
  gameId: { type: [String, Number], required: true }
})

const router = useRouter()
const gamesStore = useGamesStore()

const editor = ref(null)
const boxscoreData = ref(null)
const isLoading = ref(false)
const loadError = ref('')

// Al regresar se abre la configuración del torneo en el calendario, donde está el partido
const tournamentRoute = computed(() => ({ name: 'TournamentConfiguration', params: { id: props.id }, query: { tab: 'calendario' } }))

const loadBoxscore = async () => {
  isLoading.value = true
  loadError.value = ''
  boxscoreData.value = null
  resetSaveQueue()
  try {
    boxscoreData.value = await gamesStore.fetchBoxscore(props.id, props.gameId)
  } catch (error) {
    loadError.value = getErrorMessage(error, 'Ocurrió un error al cargar el box score')
  } finally {
    isLoading.value = false
  }
}

// ---------- AUTOGUARDADO ----------
// El endpoint guarda el box score completo (reemplaza el lineup y sus turnos en una transacción), así que repetirlo da
// el mismo resultado. Los cambios seguidos se juntan (debounce) y se manda solo el último; si llega uno mientras otro
// request está en curso, se queda en cola y sale al terminar. Nunca hay dos requests a la vez.
const DEBOUNCE_MS = 600
const saveState = ref('idle') // idle | pending | saving | saved | error
const saveError = ref('')
const savedAt = ref('')
const leaving = ref(false)
let pendingPayload = null // último cambio por guardar
let lastSavedJson = null
let debounceTimer = null
let inFlight = null // promesa del request en curso

const resetSaveQueue = () => {
  clearTimeout(debounceTimer)
  debounceTimer = null
  pendingPayload = null
  lastSavedJson = null
  saveState.value = 'idle'
  saveError.value = ''
}

const queueSave = (payload) => {
  pendingPayload = payload
  if (saveState.value !== 'saving') saveState.value = 'pending'
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    debounceTimer = null
    sendPending()
  }, DEBOUNCE_MS)
}

const sendPending = async () => {
  if (inFlight || !pendingPayload) return inFlight
  const payload = pendingPayload
  pendingPayload = null
  const json = JSON.stringify(payload)
  if (json === lastSavedJson) {
    saveState.value = 'saved'
    return
  }
  saveState.value = 'saving'
  inFlight = (async () => {
    try {
      await gamesStore.saveBoxscore(props.id, props.gameId, payload, { reloadSchedule: false })
      lastSavedJson = json
      saveError.value = ''
      savedAt.value = new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
      saveState.value = pendingPayload ? 'pending' : 'saved'
    } catch (error) {
      // No se pierde: si no llegó otro cambio, este vuelve a quedar pendiente para Reintentar
      if (!pendingPayload) pendingPayload = payload
      saveError.value = getErrorMessage(error, 'Ocurrió un error al guardar el box score')
      saveState.value = 'error'
    } finally {
      inFlight = null
    }
  })()
  await inFlight
  // Lo que llegó mientras se guardaba sale en cuanto termina (si no hubo error y no está esperando su debounce)
  if (saveState.value !== 'error' && pendingPayload && !debounceTimer) return sendPending()
}

const retrySave = () => {
  clearTimeout(debounceTimer)
  debounceTimer = null
  return sendPending()
}

// Guarda ya todo lo pendiente (lo escrito en un campo con foco, el debounce y la cola) y espera a que termine
const flushSaves = async () => {
  editor.value?.flushChange?.()
  clearTimeout(debounceTimer)
  debounceTimer = null
  for (let guard = 0; guard < 5 && (inFlight || pendingPayload); guard++) {
    if (inFlight) await inFlight
    else await sendPending()
    if (saveState.value === 'error') break
  }
}

const hasUnsavedChanges = () => !!(pendingPayload || inFlight || debounceTimer || saveState.value === 'error' || editor.value?.isDirty?.())

const goBack = async () => {
  leaving.value = true
  try {
    await flushSaves()
  } finally {
    leaving.value = false
  }
  router.push(tournamentRoute.value)
}

// Al salir se termina el guardado pendiente; solo si falla se pregunta antes de perder el cambio
onBeforeRouteLeave(async () => {
  if (!hasUnsavedChanges()) return true
  await flushSaves()
  if (!hasUnsavedChanges()) return true
  return window.confirm(`No se pudo guardar el último cambio del box score${saveError.value ? ` (${saveError.value})` : ''}. ¿Salir sin guardarlo?`)
})

const onBeforeUnload = (event) => {
  if (!hasUnsavedChanges()) return
  flushSaves()
  event.preventDefault()
  event.returnValue = ''
}

onMounted(() => {
  window.addEventListener('beforeunload', onBeforeUnload)
  loadBoxscore()
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', onBeforeUnload)
  clearTimeout(debounceTimer)
})

// Otro partido en la misma página (cambia la URL sin desmontar)
watch(() => [props.id, props.gameId], loadBoxscore)
</script>

<style scoped>
.save-status {
  display: flex;
  align-items: center;
  font-size: 0.8125rem;
  white-space: nowrap;
  opacity: 0.95;
}
</style>
