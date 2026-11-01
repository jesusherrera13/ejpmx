<!-- src/components/BoxscoreModal.vue -->
<!-- Captura del box score de un partido: Score, Line Score, Bateo, Bateo detallado y Scorecard -->
<template>
  <v-dialog v-model="dialog" max-width="1440px" persistent scrollable>
    <v-card class="rounded-xl pa-2" theme="light">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <div>
          <div class="text-h6 font-weight-black text-grey-darken-4">Box Score</div>
          <div v-if="game" class="text-subtitle-1 font-weight-bold text-grey-darken-4">
            {{ game.away_team.name }} <span class="text-grey font-weight-regular">@</span> {{ game.home_team.name }}
          </div>
          <div v-if="game" class="text-body-2 text-grey-darken-1">
            Partido #{{ game.game_number }} · {{ formatDate(game.scheduled_at) }}<template v-if="formatTime(game.scheduled_at)"> {{ formatTime(game.scheduled_at) }}</template>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" density="comfortable" :disabled="saving" @click="dialog = false" />
      </v-card-title>

      <v-divider class="my-2" />

      <v-card-text class="pt-2">
        <div v-if="loading" class="pa-8 d-flex justify-center">
          <v-progress-circular indeterminate color="primary" size="28" />
        </div>

        <template v-else-if="ready">
          <!-- Modalidad -->
          <v-btn-toggle v-model="mode" mandatory density="compact" variant="outlined" divided color="primary" class="mb-4 flex-wrap">
            <v-btn v-for="item in MODES" :key="item.value" :value="item.value" size="small" class="text-none">{{ item.title }}</v-btn>
          </v-btn-toggle>
          <div class="text-caption text-grey-darken-1 mb-3">{{ currentMode.hint }}</div>

          <!-- Marcador: entradas (Line Score) + R H E -->
          <div class="box-wrap mb-4">
            <table class="box-table">
              <thead>
                <tr>
                  <th class="text-left">Equipo</th>
                  <template v-if="showInnings">
                    <th v-for="(_, index) in lineScore.away" :key="index" class="inning">{{ index + 1 }}</th>
                  </template>
                  <th class="total" title="Carreras">R</th>
                  <th class="total" title="Hits">H</th>
                  <th class="total" title="Errores">E</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="side in SIDES" :key="side">
                  <td class="font-weight-bold text-black text-no-wrap">{{ teamOf(side).name }}</td>
                  <template v-if="mode === 'scorecard'">
                    <td v-for="(_, index) in lineScore[side]" :key="index" class="inning font-weight-bold" title="Carreras anotadas en el scorecard">
                      {{ scorecardRuns(side, index) ?? 'X' }}
                    </td>
                  </template>
                  <template v-else-if="mode === 'linescore'">
                    <td v-for="(_, index) in lineScore[side]" :key="index" class="inning">
                      <input
                        v-model="lineScore[side][index]"
                        type="number"
                        min="0"
                        class="stat-input"
                        :placeholder="side === 'home' && index === lineScore.home.length - 1 ? 'X' : '0'"
                        :title="side === 'home' && index === lineScore.home.length - 1 ? 'Vacío = X (el local no batea por ir ganando)' : ''"
                      />
                    </td>
                  </template>
                  <td class="total">
                    <span v-if="mode === 'linescore'" class="font-weight-black" title="Suma de las entradas">{{ inningRuns(side) }}</span>
                    <span v-else-if="fromLineup(side)" class="font-weight-black" title="Suma de las carreras del lineup">{{ columnTotal(side, 'r') }}</span>
                    <input v-else v-model="totals[side].r" type="number" min="0" class="stat-input stat-input--total" />
                  </td>
                  <td class="total">
                    <span v-if="fromLineup(side)" class="font-weight-black" title="Suma de 1B, 2B, 3B y HR del lineup">{{ columnTotal(side, 'h') }}</span>
                    <input v-else v-model="totals[side].h" type="number" min="0" class="stat-input stat-input--total" />
                  </td>
                  <td class="total"><input v-model="totals[side].e" type="number" min="0" class="stat-input stat-input--total" /></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="showInnings" class="d-flex ga-2 mb-4">
            <v-btn size="small" variant="outlined" class="text-none" prepend-icon="mdi-plus" :disabled="lineScore.away.length >= 30" @click="addInning">Entrada</v-btn>
            <v-btn
              size="small"
              variant="outlined"
              class="text-none"
              prepend-icon="mdi-minus"
              :disabled="lineScore.away.length <= 1 || lastInningHasPlays"
              :title="lastInningHasPlays ? 'La última entrada tiene turnos capturados' : ''"
              @click="removeInning"
            >
              Entrada
            </v-btn>
          </div>

          <!-- Bateo por equipo -->
          <template v-if="isBatting">
            <v-tabs v-model="battingSide" color="primary" density="compact" class="mb-2">
              <v-tab v-for="side in SIDES" :key="side" :value="side" class="text-none">
                {{ teamOf(side).name }}
                <v-chip size="x-small" class="ml-2" variant="tonal">{{ batting[side].length }}</v-chip>
              </v-tab>
            </v-tabs>

            <v-alert
              v-for="warning in battingWarnings"
              :key="warning"
              type="warning"
              variant="tonal"
              density="compact"
              class="mb-2 text-body-2"
            >
              {{ warning }}
            </v-alert>

            <div class="box-wrap">
              <table class="box-table">
                <thead>
                  <tr>
                    <th class="order">#</th>
                    <th class="text-left player">Jugador</th>
                    <th class="position">Pos</th>
                    <template v-if="mode === 'scorecard'">
                      <th v-for="inning in innings" :key="`inning-${inning}`" class="sc-inning">{{ inning }}</th>
                    </template>
                    <th v-for="column in battingColumns" :key="column.key" :title="column.title" :class="{ derived: column.derived }">{{ column.label }}</th>
                    <th class="row-actions"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, index) in batting[battingSide]" :key="line.uid" :class="{ 'substitute-row': line.is_substitute }">
                    <td class="order text-grey-darken-1">{{ line.is_substitute ? '' : slotNumber(index) }}</td>
                    <td class="player">
                      <div class="d-flex align-center">
                        <v-icon v-if="line.is_substitute" icon="mdi-subdirectory-arrow-right" size="18" class="text-grey-darken-1 mr-1" title="Entró de cambio" />
                        <v-autocomplete
                          v-model="line.player_id"
                          :items="playerOptions(line)"
                          item-title="name"
                          item-value="id"
                          placeholder="Selecciona jugador"
                          variant="outlined"
                          density="compact"
                          hide-details
                          class="cell-field flex-grow-1"
                        />
                      </div>
                    </td>
                    <td class="position">
                      <v-select v-model="line.position" :items="positions" variant="outlined" density="compact" hide-details clearable class="cell-field" />
                    </td>
                    <template v-if="mode === 'scorecard'">
                      <td
                        v-for="inning in innings"
                        :key="`inning-${inning}`"
                        class="sc-cell"
                        :title="`Capturar el turno de la entrada ${inning}`"
                        @click="openCell(line, inning)"
                      >
                        <div v-for="(play, playIndex) in cellPlays(line, inning)" :key="playIndex" class="sc-play" :class="{ 'sc-play--hit': isHit(play) }">
                          <v-icon v-if="play.scored || play.result === 'HR'" icon="mdi-rhombus" size="10" color="success" class="mr-1" title="Anotó carrera" />{{ playLabel(play) }}<sup v-if="play.rbi" class="sc-rbi" :title="`${play.rbi} impulsada(s)`">{{ play.rbi }}</sup>
                        </div>
                      </td>
                    </template>
                    <td v-for="column in battingColumns" :key="column.key" :class="{ derived: column.derived }">
                      <span v-if="column.derived" class="font-weight-bold">{{ statValue(line, column.key) }}</span>
                      <input v-else v-model="line[column.key]" type="number" min="0" class="stat-input" />
                    </td>
                    <td class="row-actions text-no-wrap">
                      <v-btn icon="mdi-arrow-up" size="x-small" variant="text" :disabled="index === 0" title="Subir en el orden al bat" @click="move(index, -1)" />
                      <v-btn icon="mdi-arrow-down" size="x-small" variant="text" :disabled="index === batting[battingSide].length - 1" title="Bajar en el orden al bat" @click="move(index, 1)" />
                      <v-menu location="bottom end">
                        <template v-slot:activator="{ props: menuProps }">
                          <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="x-small" variant="text" color="grey-darken-1" title="Opciones" />
                        </template>
                        <v-list density="compact" class="py-1">
                          <v-list-item prepend-icon="mdi-account-switch" title="Agregar cambio" @click="addSubstitute(index)" />
                          <v-list-item
                            v-if="index > 0"
                            :prepend-icon="line.is_substitute ? 'mdi-account-arrow-up' : 'mdi-subdirectory-arrow-right'"
                            :title="line.is_substitute ? 'Marcar como titular' : 'Marcar como cambio'"
                            @click="line.is_substitute = !line.is_substitute"
                          />
                          <v-divider class="my-1" />
                          <v-list-item prepend-icon="mdi-close" title="Quitar del lineup" base-color="error" @click="batting[battingSide].splice(index, 1)" />
                        </v-list>
                      </v-menu>
                    </td>
                  </tr>
                  <tr v-if="batting[battingSide].length === 0">
                    <td :colspan="battingColumns.length + 4 + (mode === 'scorecard' ? innings : 0)" class="text-center text-grey-darken-1 pa-4">
                      Sin bateadores. Usa <strong>Agregar bateador</strong> para armar el lineup.
                    </td>
                  </tr>
                  <tr v-else class="totals-row">
                    <td></td>
                    <td class="text-left font-weight-bold">Totales</td>
                    <td></td>
                    <template v-if="mode === 'scorecard'">
                      <td v-for="inning in innings" :key="`inning-${inning}`" class="font-weight-bold" title="Carreras de la entrada">{{ scorecardRuns(battingSide, inning - 1) ?? 'X' }}</td>
                    </template>
                    <td v-for="column in battingColumns" :key="column.key" class="font-weight-bold">{{ columnTotal(battingSide, column.key) }}</td>
                    <td></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <v-btn
              size="small"
              variant="outlined"
              color="success"
              class="text-none font-weight-bold mt-3"
              prepend-icon="mdi-account-plus"
              @click="addBatter"
            >
              Agregar bateador
            </v-btn>
          </template>
        </template>
      </v-card-text>

      <!-- Scorecard: turnos de un bateador en una entrada -->
      <v-dialog v-model="cellDialog" max-width="640px">
        <v-card v-if="editingCell" class="rounded-xl pa-2" theme="light">
          <v-card-title class="pt-4 px-4">
            <div class="text-h6 font-weight-black text-grey-darken-4">{{ playerName(editingCell.line) }}</div>
            <div class="text-body-2 text-grey-darken-1">Entrada {{ editingCell.inning }} · {{ teamOf(battingSide).name }}</div>
          </v-card-title>
          <v-card-text>
            <div v-for="(play, index) in editingPlays" :key="index" class="d-flex flex-wrap align-center ga-2 mb-3">
              <v-select
                v-model="play.result"
                :items="RESULTS"
                label="Jugada"
                variant="outlined"
                density="compact"
                hide-details
                class="play-result"
                @update:model-value="onResultChange(play)"
              />
              <v-text-field
                v-model="play.notation"
                label="Anotación"
                placeholder="6-3, F8"
                maxlength="20"
                variant="outlined"
                density="compact"
                hide-details
                class="play-notation"
              />
              <v-text-field v-model="play.rbi" type="number" min="0" max="4" label="RBI" variant="outlined" density="compact" hide-details class="play-rbi" />
              <v-checkbox v-model="play.scored" label="Anotó" color="success" density="compact" hide-details :disabled="play.result === 'HR'" />
              <v-btn icon="mdi-delete-outline" size="small" variant="text" color="grey-darken-1" title="Quitar turno" @click="removePlay(play)" />
            </div>
            <v-btn size="small" variant="outlined" class="text-none" prepend-icon="mdi-plus" @click="addPlay">
              Otro turno en esta entrada
            </v-btn>
            <div class="text-caption text-grey-darken-1 mt-3">
              La anotación es opcional y se muestra en la casilla (6-3 rodado a primera, F8 elevado al central, E6 error del short...).
            </div>
          </v-card-text>
          <v-card-actions class="pa-4 pt-0 justify-end">
            <v-btn color="black" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" @click="cellDialog = false">Listo</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Actions -->
      <v-card-actions class="pa-4 pt-0 justify-end ga-2">
        <v-btn variant="outlined" color="grey-darken-1" class="text-capitalize rounded-pill px-5" :disabled="saving" @click="dialog = false">
          Cancelar
        </v-btn>
        <v-btn
          color="black"
          variant="flat"
          class="text-capitalize rounded-pill px-5 font-weight-bold"
          :loading="saving"
          :disabled="!ready"
          @click="handleSubmit"
        >
          Guardar Box Score
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { formatDate, formatTime } from '@/utils/dates'

const props = defineProps({
  modelValue: Boolean,
  // Respuesta de GET /boxscore: { game, players, positions }
  boxscore: Object,
  loading: Boolean,
  saving: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])

const SIDES = ['away', 'home']

const MODES = [
  { value: 'score', title: 'Score', hint: 'Marcador final por equipo: carreras (R), hits (H) y errores (E).' },
  { value: 'linescore', title: 'Line Score', hint: 'Carreras por entrada y R H E. Las carreras se suman de las entradas; deja vacía la última del local si no batea (X).' },
  { value: 'batting', title: 'Bateo', hint: 'R H E y el lineup de cada equipo con AB, H, R, RBI y K.' },
  { value: 'batting_full', title: 'Bateo detallado', hint: 'Lineup con PA, AB, 1B, 2B, 3B, HR, R, RBI, BB, IBB, HBP, SF, SH, KS, KC y E. H y K se calculan; las carreras y hits del equipo son la suma de su lineup.' },
  { value: 'scorecard', title: 'Scorecard', hint: 'Haz clic en la casilla del bateador en la entrada para capturar la jugada de su turno. El bateo, las carreras por entrada, R y H se calculan de los turnos; E se captura en el marcador.' }
]

// Jugadas del scorecard (mismas claves que GamePlateAppearance::RESULTS); label = lo que se ve en la casilla
const RESULTS = [
  { value: '1B', title: '1B · Sencillo' },
  { value: '2B', title: '2B · Doble' },
  { value: '3B', title: '3B · Triple' },
  { value: 'HR', title: 'HR · Home run' },
  { value: 'BB', title: 'BB · Base por bolas' },
  { value: 'IBB', title: 'IBB · Base por bolas intencional' },
  { value: 'HBP', title: 'HBP · Golpeado por lanzamiento' },
  { value: 'KS', title: 'K · Ponche tirándole', label: 'K' },
  { value: 'KC', title: 'ꓘ · Ponche cantado', label: 'ꓘ' },
  { value: 'GO', title: 'GO · Rodado de out' },
  { value: 'FO', title: 'FO · Elevado de out' },
  { value: 'LO', title: 'LO · Línea de out' },
  { value: 'DP', title: 'DP · Doble play' },
  { value: 'FC', title: 'FC · Bola ocupada' },
  { value: 'E', title: 'E · Se embasó por error' },
  { value: 'SF', title: 'SF · Elevado de sacrificio' },
  { value: 'SH', title: 'SH · Toque de sacrificio' }
]

// Estadística que suma cada jugada; BB, IBB, HBP, SF y SH no cuentan como turno oficial (AB)
const RESULT_STATS = {
  '1B': ['singles', 'h'],
  '2B': ['doubles', 'h'],
  '3B': ['triples', 'h'],
  HR: ['hr', 'h'],
  BB: ['bb'],
  IBB: ['bb', 'ibb'],
  HBP: ['hbp'],
  KS: ['so_swinging', 'so'],
  KC: ['so_looking', 'so'],
  SF: ['sf'],
  SH: ['sh']
}
const NOT_AT_BAT = ['BB', 'IBB', 'HBP', 'SF', 'SH']

// Columnas de bateo; derived = se calcula a partir de otras
const BASIC_COLUMNS = [
  { key: 'ab', label: 'AB', title: 'Turnos oficiales al bat' },
  { key: 'h', label: 'H', title: 'Hits' },
  { key: 'r', label: 'R', title: 'Carreras anotadas' },
  { key: 'rbi', label: 'RBI', title: 'Carreras impulsadas' },
  { key: 'so', label: 'K', title: 'Ponches' }
]

const FULL_COLUMNS = [
  { key: 'pa', label: 'PA', title: 'Apariciones al plato' },
  { key: 'ab', label: 'AB', title: 'Turnos oficiales al bat' },
  { key: 'h', label: 'H', title: 'Hits (1B + 2B + 3B + HR)', derived: true },
  { key: 'singles', label: '1B', title: 'Sencillos' },
  { key: 'doubles', label: '2B', title: 'Dobles' },
  { key: 'triples', label: '3B', title: 'Triples' },
  { key: 'hr', label: 'HR', title: 'Home runs' },
  { key: 'r', label: 'R', title: 'Carreras anotadas' },
  { key: 'rbi', label: 'RBI', title: 'Carreras impulsadas' },
  { key: 'bb', label: 'BB', title: 'Bases por bolas (incluye intencionales)' },
  { key: 'ibb', label: 'IBB', title: 'Bases por bolas intencionales' },
  { key: 'hbp', label: 'HBP', title: 'Golpeado por lanzamiento' },
  { key: 'sf', label: 'SF', title: 'Elevado de sacrificio' },
  { key: 'sh', label: 'SH', title: 'Toque de sacrificio' },
  { key: 'so', label: 'K', title: 'Ponches (KS + KC)', derived: true },
  { key: 'so_swinging', label: 'KS', title: 'Ponches tirándole' },
  { key: 'so_looking', label: 'KC', title: 'Ponches sin tirarle (cantados)' },
  { key: 'e', label: 'E', title: 'Errores' }
]

// En el scorecard todo el bateo se calcula de los turnos
const SCORECARD_COLUMNS = [
  { key: 'ab', label: 'AB', title: 'Turnos oficiales al bat', derived: true },
  { key: 'r', label: 'R', title: 'Carreras anotadas', derived: true },
  { key: 'h', label: 'H', title: 'Hits', derived: true },
  { key: 'rbi', label: 'RBI', title: 'Carreras impulsadas', derived: true },
  { key: 'bb', label: 'BB', title: 'Bases por bolas', derived: true },
  { key: 'so', label: 'K', title: 'Ponches', derived: true }
]

const STATS = ['pa', 'ab', 'r', 'h', 'singles', 'doubles', 'triples', 'hr', 'rbi', 'bb', 'ibb', 'hbp', 'sf', 'sh', 'so', 'so_swinging', 'so_looking', 'e']

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const mode = ref('score')
const totals = ref({ away: {}, home: {} })
const lineScore = ref({ away: [], home: [] })
const batting = ref({ away: [], home: [] })
const battingSide = ref('away')
const ready = ref(false)

const game = computed(() => props.boxscore?.game)
const positions = computed(() => props.boxscore?.positions || [])
const currentMode = computed(() => MODES.find(item => item.value === mode.value))
const isBatting = computed(() => ['batting', 'batting_full', 'scorecard'].includes(mode.value))
const battingColumns = computed(() => ({ batting_full: FULL_COLUMNS, scorecard: SCORECARD_COLUMNS })[mode.value] || BASIC_COLUMNS)
const showInnings = computed(() => mode.value === 'linescore' || mode.value === 'scorecard')
const innings = computed(() => lineScore.value.away.length)

const teamOf = side => side === 'away' ? game.value.away_team : game.value.home_team
const teamIdOf = side => side === 'away' ? game.value.away_team_id : game.value.home_team_id

const players = computed(() => (props.boxscore?.players || []).map(player => ({
  id: player.id,
  name: [player.first_name, player.last_name, player.second_last_name].filter(Boolean).join(' ')
})))

let uid = 0
const newLine = (data = {}) => ({
  uid: ++uid,
  player_id: data.player_id ?? null,
  position: data.position ?? null,
  is_substitute: data.is_substitute ?? false,
  // Turnos del scorecard: { inning, result, notation, rbi, scored }
  plays: (data.plate_appearances || []).map(play => ({
    inning: play.inning,
    result: play.result,
    notation: play.notation ?? '',
    rbi: play.rbi ?? 0,
    scored: !!play.scored
  })),
  ...Object.fromEntries(STATS.map(key => [key, data[key] ?? 0]))
})

// Carga el box score guardado cada vez que llega uno nuevo
watch(() => props.boxscore, (data) => {
  ready.value = false
  if (!data) return
  const current = data.game
  mode.value = current.boxscore_mode || 'score'
  totals.value = {
    away: { r: current.away_score ?? 0, h: current.away_hits ?? 0, e: current.away_errors ?? 0 },
    home: { r: current.home_score ?? 0, h: current.home_hits ?? 0, e: current.home_errors ?? 0 }
  }
  const saved = current.line_score
  lineScore.value = saved
    ? { away: [...saved.away], home: saved.home.map(value => value ?? '') }
    : { away: Array(9).fill(''), home: Array(9).fill('') }
  batting.value = {
    away: current.batting_lines.filter(line => line.team_id === current.away_team_id).map(newLine),
    home: current.batting_lines.filter(line => line.team_id === current.home_team_id).map(newLine)
  }
  battingSide.value = 'away'
  ready.value = true
}, { immediate: true })

const toInt = value => Number.parseInt(value, 10) || 0

// ---------- LINE SCORE ----------
const inningRuns = side => lineScore.value[side].reduce((sum, value) => sum + toInt(value), 0)

// Al salir de una modalidad que calcula el marcador, lo calculado pasa al marcador editable
watch(mode, (_, previous) => {
  if (!ready.value) return
  for (const side of SIDES) {
    if (previous === 'linescore') totals.value[side].r = inningRuns(side)
    if (previous === 'scorecard') {
      lineScore.value[side] = lineScore.value[side].map((_, index) => scorecardRuns(side, index) ?? '')
      for (const line of batting.value[side]) Object.assign(line, playStats(line))
      totals.value[side].r = columnTotalFrom(side, 'r')
      totals.value[side].h = columnTotalFrom(side, 'h')
      continue
    }
    if (previous === 'batting_full' && batting.value[side].length > 0) {
      totals.value[side].r = batting.value[side].reduce((sum, line) => sum + toInt(line.r), 0)
      totals.value[side].h = batting.value[side].reduce((sum, line) => sum + derivedValue(line, 'h'), 0)
    }
  }
})

const addInning = () => {
  lineScore.value.away.push('')
  lineScore.value.home.push('')
}

const removeInning = () => {
  lineScore.value.away.pop()
  lineScore.value.home.pop()
}

// ---------- BATEO ----------
// Un jugador solo puede aparecer una vez en el partido (en cualquiera de los dos lineups)
const playerOptions = (line) => {
  const used = new Set([...batting.value.away, ...batting.value.home].filter(item => item !== line).map(item => item.player_id))
  return players.value.filter(player => !used.has(player.id))
}

const addBatter = () => batting.value[battingSide.value].push(newLine())

// Turno al bat del renglón: los cambios comparten el turno del titular que reemplazan
const slotNumber = index => batting.value[battingSide.value].slice(0, index + 1).filter(line => !line.is_substitute).length

// El cambio entra debajo del titular y de los cambios que ya tenga ese turno
const addSubstitute = (index) => {
  const lines = batting.value[battingSide.value]
  let position = index + 1
  while (position < lines.length && lines[position].is_substitute) position++
  lines.splice(position, 0, newLine({ is_substitute: true }))
}

// R y H del equipo salen del lineup: en el scorecard siempre, en Bateo detallado si ya tiene bateadores
const fromLineup = side => mode.value === 'scorecard' || (mode.value === 'batting_full' && batting.value[side].length > 0)

const move = (index, step) => {
  const lines = batting.value[battingSide.value]
  const [line] = lines.splice(index, 1)
  lines.splice(index + step, 0, line)
}

const derivedValue = (line, key) => key === 'h'
  ? toInt(line.singles) + toInt(line.doubles) + toInt(line.triples) + toInt(line.hr)
  : toInt(line.so_swinging) + toInt(line.so_looking)

const statValue = (line, key) => {
  if (mode.value === 'scorecard') return playStats(line)[key] ?? toInt(line[key])
  if (mode.value === 'batting_full' && (key === 'h' || key === 'so')) return derivedValue(line, key)
  return toInt(line[key])
}

const columnTotal = (side, key) => batting.value[side].reduce((sum, line) => sum + statValue(line, key), 0)

// Total de una columna con lo que ya trae cada renglón (tras copiar lo calculado)
const columnTotalFrom = (side, key) => batting.value[side].reduce((sum, line) => sum + toInt(line[key]), 0)

// ---------- SCORECARD ----------
const cellPlays = (line, inning) => line.plays.filter(play => play.inning === inning)

const isHit = play => ['1B', '2B', '3B', 'HR'].includes(play.result)

const playLabel = play => play.notation || RESULTS.find(item => item.value === play.result)?.label || play.result || '?'

const playerName = line => players.value.find(player => player.id === line.player_id)?.name || `Bateador ${batting.value[battingSide.value].indexOf(line) + 1}`

// Bateo de un renglón a partir de sus turnos (sin E, que es defensivo)
const playStats = (line) => {
  const stats = Object.fromEntries(STATS.filter(key => key !== 'e').map(key => [key, 0]))
  for (const play of line.plays.filter(item => item.result)) {
    stats.pa++
    if (!NOT_AT_BAT.includes(play.result)) stats.ab++
    for (const key of RESULT_STATS[play.result] || []) stats[key]++
    stats.rbi += toInt(play.rbi)
    if (play.scored || play.result === 'HR') stats.r++
  }
  return stats
}

// Carreras del equipo en una entrada; null = X (el local no batea la última entrada)
const scorecardRuns = (side, index) => {
  const plays = batting.value[side].flatMap(line => line.plays).filter(play => play.result && play.inning === index + 1)
  if (side === 'home' && index === innings.value - 1 && plays.length === 0) return null
  return plays.filter(play => play.scored || play.result === 'HR').length
}

const lastInningHasPlays = computed(() => mode.value === 'scorecard'
  && [...batting.value.away, ...batting.value.home].some(line => line.plays.some(play => play.inning === innings.value)))

const cellDialog = ref(false)
const editingCell = ref(null)
const editingPlays = computed(() => editingCell.value ? cellPlays(editingCell.value.line, editingCell.value.inning) : [])

const newPlay = inning => ({ inning, result: null, notation: '', rbi: 0, scored: false })

const openCell = (line, inning) => {
  editingCell.value = { line, inning }
  if (cellPlays(line, inning).length === 0) line.plays.push(newPlay(inning))
  cellDialog.value = true
}

const addPlay = () => editingCell.value.line.plays.push(newPlay(editingCell.value.inning))

const removePlay = (play) => {
  const plays = editingCell.value.line.plays
  plays.splice(plays.indexOf(play), 1)
}

// Con home run el bateador anota y al menos se impulsa a sí mismo
const onResultChange = (play) => {
  if (play.result !== 'HR') return
  play.scored = true
  play.rbi = Math.max(toInt(play.rbi), 1)
}

// Al cerrar se descartan los turnos que quedaron sin jugada
watch(cellDialog, (open) => {
  if (open || !editingCell.value) return
  const line = editingCell.value.line
  line.plays = line.plays.filter(play => play.result)
})

// Avisos (no bloquean): los totales del lineup no cuadran con el marcador
const battingWarnings = computed(() => {
  const side = battingSide.value
  if (batting.value[side].length === 0 || fromLineup(side)) return []
  const name = teamOf(side).name
  const warnings = []
  const runs = columnTotal(side, 'r')
  const hits = columnTotal(side, 'h')
  if (runs !== toInt(totals.value[side].r)) warnings.push(`${name}: el lineup suma ${runs} carreras y el marcador tiene ${toInt(totals.value[side].r)}.`)
  if (hits !== toInt(totals.value[side].h)) warnings.push(`${name}: el lineup suma ${hits} hits y el marcador tiene ${toInt(totals.value[side].h)}.`)
  return warnings
})

// ---------- GUARDAR ----------
const handleSubmit = () => {
  const payload = { mode: mode.value }
  for (const side of SIDES) {
    payload[`${side}_score`] = mode.value === 'linescore'
      ? inningRuns(side)
      : fromLineup(side) ? columnTotal(side, 'r') : toInt(totals.value[side].r)
    payload[`${side}_hits`] = fromLineup(side) ? columnTotal(side, 'h') : toInt(totals.value[side].h)
    payload[`${side}_errors`] = toInt(totals.value[side].e)
  }

  if (mode.value === 'scorecard') payload.innings = innings.value

  if (mode.value === 'linescore') {
    const lastIndex = lineScore.value.home.length - 1
    payload.line_score = {
      away: lineScore.value.away.map(toInt),
      home: lineScore.value.home.map((value, index) => index === lastIndex && value === '' ? null : toInt(value))
    }
  }

  if (isBatting.value) {
    // Se envían todas las columnas para no perder lo capturado en la otra modalidad de bateo
    payload.batting = SIDES.flatMap(side => batting.value[side]
      .filter(line => line.player_id)
      .map(line => ({
        team_id: teamIdOf(side),
        player_id: line.player_id,
        position: line.position,
        is_substitute: line.is_substitute,
        plate_appearances: line.plays.filter(play => play.result).map(play => ({
          inning: play.inning,
          result: play.result,
          notation: play.notation || null,
          rbi: toInt(play.rbi),
          scored: play.scored || play.result === 'HR'
        })),
        ...Object.fromEntries(STATS.map(key => [key, toInt(line[key])]))
      })))
  }

  emit('save', payload)
}
</script>

<style scoped>
.box-wrap {
  overflow-x: auto;
  border: 1px solid #d0d7de;
  border-radius: 6px;
}

.box-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.box-table th {
  padding: 6px 4px;
  background: #f8fafc;
  color: #475569;
  font-weight: 700;
  text-align: center;
  border-bottom: 2px solid #e2e8f0;
  white-space: nowrap;
}

.box-table td {
  padding: 3px 4px;
  text-align: center;
  border-bottom: 1px solid #f1f5f9;
}

.box-table th:first-child,
.box-table td:first-child {
  padding-left: 12px;
}

.box-table .total {
  background: #f4f7fb;
  width: 56px;
}

.box-table .derived {
  background: #f4f7fb;
}

.box-table .order {
  width: 32px;
}

.box-table .player {
  min-width: 220px;
  text-align: left;
}

.box-table .position {
  width: 96px;
}

.substitute-row td {
  background: #fbfcfe;
}

.substitute-row .player {
  padding-left: 20px;
}

.sc-inning {
  min-width: 56px;
}

.sc-cell {
  min-width: 56px;
  height: 40px;
  border-left: 1px solid #eaeef2;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.75rem;
  color: #334155;
}

.sc-cell:hover {
  background: #eef6ff;
}

.sc-play {
  white-space: nowrap;
  line-height: 1.2;
}

.sc-play--hit {
  color: #1565c0;
}

.sc-rbi {
  margin-left: 1px;
  color: #2e7d32;
}

.play-result {
  flex: 1 1 220px;
}

.play-notation {
  flex: 0 0 120px;
}

.play-rbi {
  flex: 0 0 80px;
}

.totals-row td {
  background: #eef2f7;
  border-top: 1px solid #e2e8f0;
}

.stat-input {
  width: 40px;
  padding: 4px 2px;
  border: 1px solid #d0d7de;
  border-radius: 4px;
  text-align: center;
  background: #ffffff;
  color: #212121;
  font: inherit;
}

.stat-input:focus {
  outline: 2px solid #1976d2;
  outline-offset: -1px;
}

.stat-input--total {
  font-weight: 700;
}

/* Sin flechas del input number para que quepan las columnas */
.stat-input::-webkit-outer-spin-button,
.stat-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.stat-input[type='number'] {
  -moz-appearance: textfield;
  appearance: textfield;
}

.cell-field :deep(.v-field__input) {
  min-height: 32px;
  padding-top: 2px;
  padding-bottom: 2px;
  font-size: 0.8125rem;
}
</style>
