<!-- src/components/BoxscoreEditor.vue -->
<!-- Captura del box score de un partido: Score, Line Score, Bateo, Bateo detallado y Scorecard.
     Es el contenido de la página GameBoxscore (antes se abría en un modal) -->
<template>
  <div class="boxscore-editor" @focusout="onFocusOut">
    <v-card class="rounded-xl pa-2" theme="ejp">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <div class="text-h6 text-grey-darken-4 text-wrap">
          <span class="font-weight-black">Box Score</span>
          <template v-if="game">
            <span class="text-grey font-weight-regular"> · </span>
            <span class="font-weight-bold">{{ game.away_team.name }} <span class="text-grey font-weight-regular">@</span> {{ game.home_team.name }}</span>
            <span class="text-grey-darken-1 font-weight-regular">
              · Partido #{{ game.game_number }} · {{ formatDate(game.scheduled_at) }}<template v-if="formatTime(game.scheduled_at)"> {{ formatTime(game.scheduled_at) }}</template>
            </span>
          </template>
        </div>
      </v-card-title>

      <v-divider class="my-2" />

      <v-card-text class="pt-2">
        <div v-if="loading" class="pa-8 d-flex justify-center">
          <v-progress-circular indeterminate color="primary" size="28" />
        </div>

        <template v-else-if="ready">
          <!-- Modalidad y, a la derecha, el scorecard en PDF (se genera con lo guardado del partido) -->
          <div class="d-flex flex-wrap align-center ga-2 mb-4">
            <v-btn-toggle v-model="mode" mandatory density="compact" variant="outlined" divided color="primary" class="flex-wrap">
              <v-btn v-for="item in MODES" :key="item.value" :value="item.value" size="small" class="text-none">{{ item.title }}</v-btn>
            </v-btn-toggle>
            <v-spacer />
            <!-- Agregar o quitar entradas (Line Score y Scorecard), a la izquierda de Acciones -->
            <template v-if="showInnings">
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
            </template>
            <v-menu location="bottom end">
              <template v-slot:activator="{ props: menuProps }">
                <v-btn
                  v-bind="menuProps"
                  color="black"
                  size="small"
                  class="text-capitalize rounded-pill font-weight-bold px-4"
                  append-icon="mdi-chevron-down"
                  :loading="exportingPdf"
                  :disabled="saving"
                  flat
                >
                  Acciones
                </v-btn>
              </template>
              <v-list density="compact" class="py-1">
                <!-- PDF de la pestaña que se está viendo, con lo guardado del partido -->
                <v-list-subheader :title="pdfUnavailable">{{ currentMode.title }} guardado en PDF</v-list-subheader>
                <template v-if="modeHasLineup">
                  <v-list-item prepend-icon="mdi-file-pdf-box" title="Ambos equipos" :disabled="!!pdfUnavailable" @click="exportBoxscore('both')" />
                  <v-list-item prepend-icon="mdi-file-pdf-box" :title="game.away_team.name" :disabled="!!pdfUnavailable" @click="exportBoxscore('away')" />
                  <v-list-item prepend-icon="mdi-file-pdf-box" :title="game.home_team.name" :disabled="!!pdfUnavailable" @click="exportBoxscore('home')" />
                </template>
                <v-list-item v-else prepend-icon="mdi-file-pdf-box" title="PDF" :disabled="!!pdfUnavailable" @click="exportBoxscore('both')" />
                <v-divider class="my-1" />
                <v-list-item prepend-icon="mdi-book-open-variant" title="Glosario" subtitle="Significado de las abreviaciones" @click="glossaryDialog = true" />
              </v-list>
            </v-menu>
          </div>
          <div class="modal-note text-grey-darken-1 mb-3">{{ currentMode.hint }}</div>

          <!-- Marcador: entradas (Line Score) + R H E -->
          <div class="box-wrap mb-4">
            <table class="box-table box-table--score">
              <thead>
                <tr>
                  <th class="text-left">Equipo</th>
                  <template v-if="showInnings">
                    <th v-for="(_, index) in lineScore.away" :key="index" class="inning">{{ index + 1 }}</th>
                  </template>
                  <th class="total" title="Carreras">R</th>
                  <th class="total" title="Hits">H</th>
                  <th class="total" title="Errores">E</th>
                  <th class="total" title="Dejados en base (Left On Base)">LOB</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="side in SIDES" :key="side">
                  <td class="font-weight-bold text-black text-no-wrap">{{ teamOf(side).name }}</td>
                  <template v-if="mode === 'scorecard'">
                    <td
                      v-for="(_, index) in lineScore[side]"
                      :key="index"
                      class="inning font-weight-bold"
                      :class="{ 'inning--pending': inningState(side, index + 1) === 'pending' }"
                      :title="inningState(side, index + 1) === 'pending' ? 'Entrada sin jugar' : 'Carreras anotadas en el scorecard'"
                    >
                      {{ inningRunsLabel(side, index + 1) }}
                    </td>
                  </template>
                  <template v-else-if="mode === 'linescore'">
                    <td v-for="(_, index) in lineScore[side]" :key="index" class="inning">
                      <input
                        v-model="lineScore[side][index]"
                        type="number"
                        :autocomplete="NO_AUTOFILL"
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
                    <input v-else v-model="totals[side].r" type="number" min="0" :autocomplete="NO_AUTOFILL" class="stat-input stat-input--total" />
                  </td>
                  <td class="total">
                    <span v-if="fromLineup(side)" class="font-weight-black" title="Suma de 1B, 2B, 3B y HR del lineup">{{ columnTotal(side, 'h') }}</span>
                    <input v-else v-model="totals[side].h" type="number" min="0" :autocomplete="NO_AUTOFILL" class="stat-input stat-input--total" />
                  </td>
                  <td class="total"><input v-model="totals[side].e" type="number" min="0" :autocomplete="NO_AUTOFILL" class="stat-input stat-input--total" /></td>
                  <td class="total">
                    <span v-if="mode === 'scorecard'" class="font-weight-black" title="Corredores que quedaron en base al terminar las entradas jugadas">{{ teamLob(side) }}</span>
                    <input v-else v-model="totals[side].lob" type="number" min="0" :autocomplete="NO_AUTOFILL" class="stat-input stat-input--total" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>


          <!-- Bateo por equipo -->
          <template v-if="isBatting">
            <v-tabs v-model="battingSide" color="primary" density="compact" class="mb-2 team-tabs">
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

            <v-alert
              v-if="isBatting && startersCount(battingSide) > MAX_STARTERS"
              type="error"
              variant="tonal"
              density="compact"
              class="mb-2 text-body-2"
              icon="mdi-account-alert-outline"
            >
              {{ teamOf(battingSide).name }} tiene {{ startersCount(battingSide) }} titulares y solo puede tener {{ MAX_STARTERS }}.
              Marca como cambio o quita a los que sobran para poder guardar.
            </v-alert>

            <v-alert
              v-if="rosterIsEmpty"
              type="warning"
              variant="tonal"
              density="compact"
              class="mb-2 text-body-2"
              icon="mdi-account-off-outline"
            >
              {{ teamOf(battingSide).name }} no tiene jugadores en su roster de este torneo. Agrégalos en Equipos › ⋮ › Configuración;
              mientras tanto puedes capturar en los turnos sin jugador y asignarlos después.
            </v-alert>

            <v-alert
              v-if="provisionalCount"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-2 text-body-2"
              icon="mdi-account-question-outline"
            >
              {{ provisionalCount }} {{ provisionalCount === 1 ? 'bateador provisional' : 'bateadores provisionales' }} en el lineup de {{ teamOf(battingSide).name }}.
              Cuando tengas el lineup, elige al jugador real en cada renglón: lo capturado se conserva.
            </v-alert>

            <!-- Scorecard: captura turno por turno en el orden al bat -->
            <div v-if="mode === 'scorecard'" class="play-panel mb-3">
              <div v-if="batting[battingSide].length === 0" class="d-flex flex-wrap align-center ga-2 modal-note text-grey-darken-1 pa-3">
                <span class="order-last ml-auto text-right">Agrega el lineup de {{ teamOf(battingSide).name }} para capturar sus turnos.</span>
                <v-btn size="small" variant="flat" color="success" class="text-none" prepend-icon="mdi-account-plus" @click="addBatter">Agregar bateador</v-btn>
                <v-btn
                  size="small"
                  variant="flat"
                  color="success"
                  class="text-none"
                  prepend-icon="mdi-clipboard-account"
                  :disabled="rosterIsEmpty"
                  @click="openRosterDialog"
                >
                  Agregar del roster
                </v-btn>
              </div>
              <div v-else-if="substitutesWithoutEntry.length" class="d-flex flex-wrap align-center ga-2 modal-note pa-3">
                <span class="order-last ml-auto text-right text-error">
                  <v-icon icon="mdi-alert-circle-outline" size="18" class="mr-1" />
                  Indica en qué entrada entró {{ substitutesWithoutEntry.map(playerName).join(', ') }} (columna "Entró" del lineup) para seguir capturando.
                </span>
                <v-btn size="small" variant="outlined" color="success" class="text-none font-weight-bold" prepend-icon="mdi-account-plus" @click="addBatter">Agregar bateador</v-btn>
                <v-btn
                  size="small"
                  variant="outlined"
                  color="success"
                  class="text-none font-weight-bold"
                  prepend-icon="mdi-clipboard-account"
                  title="Abre el roster del equipo; con doble clic en un jugador se agrega al lineup"
                  :disabled="rosterIsEmpty"
                  @click="openRosterDialog"
                >
                  Agregar del roster
                </v-btn>
              </div>
              <div v-else-if="!panelTurn" class="d-flex flex-wrap align-center ga-2 modal-note pa-3">
                <span class="order-last ml-auto text-right text-grey-darken-1">No hay turnos por capturar.</span>
                <v-btn size="small" variant="outlined" color="success" class="text-none font-weight-bold" prepend-icon="mdi-account-plus" @click="addBatter">Agregar bateador</v-btn>
                <v-btn
                  size="small"
                  variant="outlined"
                  color="success"
                  class="text-none font-weight-bold"
                  prepend-icon="mdi-clipboard-account"
                  title="Abre el roster del equipo; con doble clic en un jugador se agrega al lineup"
                  :disabled="rosterIsEmpty"
                  @click="openRosterDialog"
                >
                  Agregar del roster
                </v-btn>
              </div>
              <div v-else-if="panelTurn.inning > innings" class="d-flex flex-wrap align-center ga-2 pa-3 modal-note">
                <span class="order-last ml-auto text-right">Se capturaron las {{ innings }} entradas de {{ teamOf(battingSide).name }}.</span>
                <v-btn size="small" variant="outlined" color="success" class="text-none font-weight-bold" prepend-icon="mdi-account-plus" @click="addBatter">Agregar bateador</v-btn>
                <v-btn
                  size="small"
                  variant="outlined"
                  color="success"
                  class="text-none font-weight-bold"
                  prepend-icon="mdi-clipboard-account"
                  title="Abre el roster del equipo; con doble clic en un jugador se agrega al lineup"
                  :disabled="rosterIsEmpty"
                  @click="openRosterDialog"
                >
                  Agregar del roster
                </v-btn>
                <v-btn size="small" variant="outlined" class="text-none" prepend-icon="mdi-book-open-variant" title="Significado de las abreviaciones" @click="glossaryDialog = true">Glosario</v-btn>
                <v-btn size="small" variant="outlined" class="text-none" prepend-icon="mdi-arrow-left" :disabled="!canGoBack" @click="goBack">Anterior</v-btn>
                <v-btn size="small" variant="outlined" class="text-none" prepend-icon="mdi-plus" :disabled="innings >= 30" @click="addInning">Entrada extra</v-btn>
              </div>
              <template v-else>
                <div class="play-panel__header">
                  <div class="mr-auto">
                    <span class="text-caption text-grey-darken-1 mr-1">Turno {{ panelTurn.slot + 1 }}</span>
                    <span class="font-weight-black text-grey-darken-4">{{ playerName(panelTurn.line) }}</span>
                    <span class="text-grey-darken-1"> · Entrada {{ panelTurn.inning }} · {{ teamOf(battingSide).name }}</span>
                    <span class="ml-3 text-caption text-grey-darken-1" :title="`${panelTurn.outsBefore} out(s) antes de este turno`">
                      Outs
                      <v-icon v-for="out in 3" :key="out" :icon="out <= panelTurn.outsBefore ? 'mdi-circle' : 'mdi-circle-outline'" size="12" color="error" />
                    </span>
                    <v-chip v-if="turnIndex === null" size="x-small" color="primary" variant="tonal" class="ml-2">Por capturar</v-chip>
                    <!-- Cuenta del turno por capturar: 4 bolas = base por bolas (avanzan los forzados), 3 strikes = ponche -->
                    <span v-if="turnIndex === null && !panelPlay.result" class="ml-3 text-caption text-grey-darken-1 text-no-wrap">
                      Bolas
                      <v-icon
                        v-for="ball in MAX_BALLS"
                        :key="`ball-${ball}`"
                        :icon="ball <= count.balls ? 'mdi-circle' : 'mdi-circle-outline'"
                        size="12"
                        color="success"
                        class="count-dot"
                        :title="`${ball} bola(s)`"
                        @click="setCount('balls', ball)"
                      />
                      <span class="ml-2">Strikes</span>
                      <v-icon
                        v-for="strike in MAX_STRIKES"
                        :key="`strike-${strike}`"
                        :icon="strike <= count.strikes ? 'mdi-circle' : 'mdi-circle-outline'"
                        size="12"
                        color="warning"
                        class="count-dot"
                        :title="`${strike} strike(s)`"
                        @click="setCount('strikes', strike)"
                      />
                      <span v-if="countStatus" class="ml-2 font-weight-bold text-warning">{{ countStatus }}</span>
                    </span>
                    <!-- Estadísticas del bateador en el partido -->
                    <div class="play-panel__stats">
                      <span v-for="stat in panelLineStats" :key="stat.key" :title="stat.title">
                        <span class="text-grey-darken-1">{{ stat.label }}</span> <strong>{{ stat.value }}</strong>
                      </span>
                    </div>
                  </div>
                  <v-btn size="small" variant="outlined" class="text-none" prepend-icon="mdi-book-open-variant" title="Significado de las abreviaciones" @click="glossaryDialog = true">Glosario</v-btn>
                  <v-menu location="bottom end">
                    <template v-slot:activator="{ props: menuProps }">
                      <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="small" variant="text" title="Más acciones" />
                    </template>
                    <v-list density="compact" class="py-1">
                      <v-list-item prepend-icon="mdi-account-plus" title="Agregar bateador" @click="addBatter" />
                      <v-list-item
                        prepend-icon="mdi-clipboard-account"
                        title="Agregar del roster"
                        :disabled="rosterIsEmpty"
                        @click="openRosterDialog"
                      />
                      <v-divider class="my-1" />
                      <v-list-item
                        prepend-icon="mdi-flag-checkered"
                        title="Terminar entrada"
                        subtitle="Outs en las bases (robos, pick-offs...)"
                        :disabled="turnIndex !== null"
                        @click="closeInning"
                      />
                      <v-list-item
                        prepend-icon="mdi-undo"
                        title="Deshacer último turno"
                        base-color="error"
                        :disabled="sequence.turns.length === 0"
                        @click="undoLastTurn"
                      />
                    </v-list>
                  </v-menu>
                </div>

              </template>
            </div>

            <!-- Escritorio: barra y tabla a la izquierda, botones de la jugada en una columna a la derecha (como en TPB) -->
            <div class="sc-layout" :class="{ 'sc-layout--scorecard': mode === 'scorecard' }">
            <div class="sc-layout__main">
            <!-- Barra del scorecard (como en The Perfect Boxscore): jugada (limpiar, deshacer, rehacer, bateador anterior /
                 siguiente), vista compacta y lineup (subir, bajar, sustituto, quitar) sobre el renglón seleccionado -->
            <div v-if="mode === 'scorecard' && batting[battingSide].length" class="sc-toolbar mb-2">
              <v-btn icon="mdi-broom" size="small" variant="tonal" title="Limpiar la jugada del turno" :disabled="!canClear" @click="clearPlay" />
              <v-btn icon="mdi-undo" size="small" variant="tonal" title="Deshacer el último cambio de jugada" :disabled="!undoStack.length" @click="undo" />
              <v-btn icon="mdi-redo" size="small" variant="tonal" title="Rehacer el cambio deshecho" :disabled="!redoStack.length" @click="redo" />
              <v-btn icon="mdi-skip-previous" size="small" variant="tonal" title="Bateador anterior" :disabled="!canGoBack" @click="goBack" />
              <v-btn icon="mdi-skip-next" size="small" variant="flat" color="black" title="Siguiente bateador" :disabled="turnIndex === null" @click="goNext" />
              <span class="sc-toolbar__gap" />
              <v-btn
                :icon="slim ? 'mdi-arrow-expand-horizontal' : 'mdi-arrow-collapse-horizontal'"
                size="small"
                :variant="slim ? 'flat' : 'tonal'"
                :color="slim ? 'primary' : undefined"
                :title="slim ? 'Vista completa: todas las entradas' : 'Vista compacta: solo la entrada en juego'"
                @click="toggleSlim"
              />
              <template v-if="slim">
                <v-btn icon="mdi-chevron-left" size="small" variant="tonal" :title="`Entrada anterior (viendo la ${viewInning}ª)`" :disabled="viewInning <= 1" @click="viewInning--" />
                <v-btn icon="mdi-chevron-right" size="small" variant="tonal" :title="`Entrada siguiente (viendo la ${viewInning}ª)`" :disabled="viewInning >= lastInning" @click="viewInning++" />
              </template>
              <span class="sc-toolbar__gap" />
              <v-btn icon="mdi-arrow-up" size="small" variant="tonal" title="Subir el registro seleccionado" :disabled="!canMoveSelected(-1)" @click="moveSelected(-1)" />
              <v-btn icon="mdi-arrow-down" size="small" variant="tonal" title="Bajar el registro seleccionado" :disabled="!canMoveSelected(1)" @click="moveSelected(1)" />
              <v-btn icon="mdi-swap-horizontal" size="small" variant="tonal" title="Agregar un sustituto en este turno (entra / sale)" :disabled="!selectedLine" @click="addSubToSelected" />
              <v-btn
                :icon="selectedLine?.is_substitute ? 'mdi-account-arrow-up' : 'mdi-subdirectory-arrow-right'"
                size="small"
                variant="tonal"
                :title="selectedLine?.is_substitute ? 'Marcar el registro seleccionado como titular' : 'Marcar el registro seleccionado como cambio del turno de arriba'"
                :disabled="!canToggleSubstitute"
                @click="toggleSubstitute"
              />
              <v-btn icon="mdi-close" size="small" variant="tonal" color="error" :title="removeTitle" :disabled="!canRemoveSelected" @click="removeSelected" />
              <span v-if="selectedLine" class="text-caption text-grey-darken-1 ml-2">Seleccionado: {{ playerName(selectedLine) }}</span>
            </div>

            <div class="box-wrap">
              <table class="box-table box-table--lineup">
                <thead>
                  <tr>
                    <th class="order">#</th>
                    <th class="text-left player">Jugador</th>
                    <th v-if="!slimActive" class="position">Pos</th>
                    <template v-if="mode === 'scorecard'">
                      <th
                        v-for="inning in visibleInnings"
                        :key="`inning-${inning}`"
                        class="sc-inning"
                        :class="`sc-inning--${inningState(battingSide, inning)}`"
                        :title="INNING_STATES[inningState(battingSide, inning)]"
                      >
                        {{ inning }}
                      </th>
                    </template>
                    <th v-for="column in battingColumns" :key="column.key" :title="column.title" :class="{ derived: column.derived }">{{ column.label }}</th>
                    <th v-if="mode !== 'scorecard'" class="row-actions"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(line, index) in batting[battingSide]"
                    :key="line.uid"
                    class="lineup-row"
                    :class="{ 'substitute-row': line.is_substitute, 'lineup-row--selected': mode === 'scorecard' && selectedLine === line }"
                    @click="selectedLine = line"
                  >
                    <td class="order text-grey-darken-1">{{ line.is_substitute ? '' : slotNumber(index) }}</td>
                    <td class="player">
                      <div class="d-flex align-center">
                        <v-icon v-if="line.is_substitute" icon="mdi-subdirectory-arrow-right" size="18" class="text-grey-darken-1 mr-1" title="Entró de cambio" />
                        <!-- Sin el cuadro de sugerencias del navegador encima de la lista (ver NO_AUTOFILL) -->
                        <v-autocomplete
                          v-model="line.player_id"
                          :autocomplete="NO_AUTOFILL"
                          :name="fieldName(`player-${line.uid}`)"
                          :items="playerOptions(line)"
                          :custom-filter="playerFilter"
                          item-title="name"
                          item-value="id"
                          :placeholder="line.placeholder ? `${line.placeholder} (provisional)` : line.is_substitute ? 'Selecciona jugador' : `Bateador ${slotNumber(index)}`"
                          :title="!line.player_id && line.placeholder ? 'Bateador provisional: elige al jugador real cuando lo tengas' : ''"
                          variant="outlined"
                          density="compact"
                          hide-details
                          class="cell-field flex-grow-1"
                          :class="{ 'cell-field--provisional': !line.player_id && line.placeholder }"
                        />
                        <v-select
                          v-if="line.is_substitute"
                          v-model="line.entered_inning"
                          :items="enteredInningOptions"
                          placeholder="Entró"
                          title="Entrada en la que entró a jugar"
                          variant="outlined"
                          density="compact"
                          hide-details
                          class="cell-field entered-field ml-1"
                          :error="mode === 'scorecard' && !line.entered_inning && line.plays.some(play => play.result)"
                        />
                        <v-btn
                          v-if="mode === 'scorecard' && !slimActive"
                          icon="mdi-chart-box-outline"
                          size="x-small"
                          variant="text"
                          color="grey-darken-2"
                          class="ml-1"
                          title="Game Stats: estadísticas del jugador en el partido"
                          @click.stop="openGameStats(line)"
                        />
                      </div>
                    </td>
                    <td v-if="!slimActive" class="position">
                      <!-- Sin botón de limpiar (no deja ver la posición en la columna angosta): se quita con "—" -->
                      <v-select
                        v-model="line.position"
                        :items="positionOptions"
                        variant="outlined"
                        density="compact"
                        hide-details
                        :title="line.position ? `Posición: ${line.position}` : 'Posición defensiva'"
                        class="cell-field position-field"
                      />
                    </td>
                    <template v-if="mode === 'scorecard'">
                      <td
                        v-for="inning in visibleInnings"
                        :key="`inning-${inning}`"
                        class="sc-cell"
                        :class="[
                          `sc-cell--${inningState(battingSide, inning)}`,
                          cellPlays(line, inning).length === 1 ? `sc-cell--style-${playStyle(cellPlays(line, inning)[0])}` : '',
                          {
                            'sc-cell--active': panelTurn && panelTurn.line === line && panelTurn.inning === inning,
                            'sc-cell--empty': cellPlays(line, inning).length === 0,
                            'sc-cell--played': cellPlays(line, inning).length > 0,
                            'sc-cell--unused': isUnusedCell(battingSide, line, inning) || isInactiveCell(battingSide, line, inning),
                            'sc-cell--next': nextUpCells.get(line)?.has(inning)
                          }
                        ]"
                        :title="cellPlays(line, inning).length
                          ? 'Ver este turno'
                          : isInactiveCell(battingSide, line, inning) ? 'No estaba en el juego en esta entrada'
                          : isUnusedCell(battingSide, line, inning) ? 'Se terminó la entrada antes de su turno' : 'Los turnos se capturan en orden con Anterior / Siguiente'"
                        @click="openTurn(line, inning)"
                      >
                        <div v-for="(play, playIndex) in cellPlays(line, inning)" :key="playIndex" class="sc-play" :class="`sc-play--${playStyle(play)}`">
                          <!-- Diamante como en The Perfect Boxscore: recorrido (morado el tramo robado), X si fue out en base,
                               punto en la base en que se quedó (naranja si quedó LOB) y relleno si anotó -->
                          <svg v-if="play.bases > 0" viewBox="0 0 20 20" class="sc-diamond" :class="{ 'sc-diamond--scored': play.bases === 4 }">
                            <title>{{ basesTitle(play) }}</title>
                            <polygon points="10,2 18,10 10,18 2,10" class="sc-diamond__field" />
                            <line
                              v-for="(segment, segmentIndex) in diamondSegments(play)"
                              :key="segmentIndex"
                              :x1="segment.from[0]"
                              :y1="segment.from[1]"
                              :x2="segment.to[0]"
                              :y2="segment.to[1]"
                              class="sc-diamond__path"
                              :class="{ 'sc-diamond__path--stolen': segment.stolen }"
                            />
                            <template v-if="play.bases <= 3">
                              <path
                                v-if="play.out_on_base || dpOutOf(play)"
                                :d="`M${diamondBase(play)[0] - 3} ${diamondBase(play)[1] - 3} L${diamondBase(play)[0] + 3} ${diamondBase(play)[1] + 3} M${diamondBase(play)[0] - 3} ${diamondBase(play)[1] + 3} L${diamondBase(play)[0] + 3} ${diamondBase(play)[1] - 3}`"
                                class="sc-diamond__out"
                              />
                              <circle v-else :cx="diamondBase(play)[0]" :cy="diamondBase(play)[1]" r="2.9" class="sc-diamond__runner" :class="{ 'sc-diamond__runner--stranded': isStranded(play) }" />
                            </template>
                          </svg>
                          <span class="sc-play__text">
                            <span class="sc-play__main">{{ playLabel(play) }}<sup v-if="play.rbi" class="sc-rbi" :title="`${play.rbi} impulsada(s)`">{{ play.rbi }}</sup></span>
                            <!-- Segunda línea: robos, DP, PK, pisa y corre, out en base o LOB -->
                            <span v-if="stealsLabel(play) || dpOutOf(play) || play.out_on_base || isStranded(play)" class="sc-play__extra">
                              <span v-if="stealsLabel(play)" class="sc-steal" :title="STEALS.filter(steal => play.stolen_bases.includes(steal.value)).map(steal => steal.label).join(', ')">{{ stealsLabel(play) }}</span>
                              <span v-if="dpOutOf(play)" class="sc-runner-out" :title="`Out en doble play (${dpLabel(play)})`">{{ dpLabel(play) }}</span>
                              <span v-else-if="play.out_on_base" class="sc-runner-out" :title="runnerOutTitle(play)"><v-icon v-if="!play.out_type" icon="mdi-close" size="10" />{{ runnerOutLabel(play) }}</span>
                              <span v-else-if="isStranded(play)" class="sc-stranded" title="Se quedó en base al terminar la entrada">LOB</span>
                            </span>
                          </span>
                        </div>
                        <div
                          v-if="nextUpCells.get(line)?.has(inning)"
                          class="sc-next"
                          :title="`Se quedó en turno al cerrar la entrada: abre la ${inning + 1}ª`"
                        >
                          <v-icon icon="mdi-arrow-right" size="12" />{{ inning + 1 }}ª
                        </div>
                      </td>
                    </template>
                    <td v-for="column in battingColumns" :key="column.key" :class="{ derived: column.derived }">
                      <span v-if="column.derived" class="font-weight-bold">{{ statValue(line, column.key) }}</span>
                      <input v-else v-model="line[column.key]" type="number" min="0" :autocomplete="NO_AUTOFILL" class="stat-input" />
                    </td>
                    <td v-if="mode !== 'scorecard'" class="row-actions text-no-wrap">
                      <v-btn icon="mdi-arrow-up" size="x-small" variant="text" :disabled="index === 0" title="Subir en el orden al bat" @click="move(index, -1)" />
                      <v-btn icon="mdi-arrow-down" size="x-small" variant="text" :disabled="index === batting[battingSide].length - 1" title="Bajar en el orden al bat" @click="move(index, 1)" />
                      <v-btn icon="mdi-account-switch" size="x-small" variant="text" color="grey-darken-2" title="Agregar cambio" @click="addSubstitute(index)" />
                      <!-- El primer bateador siempre es titular -->
                      <v-btn
                        :icon="line.is_substitute ? 'mdi-account-arrow-up' : 'mdi-subdirectory-arrow-right'"
                        size="x-small"
                        variant="text"
                        color="grey-darken-2"
                        :disabled="index === 0"
                        :title="line.is_substitute ? 'Marcar como titular' : 'Marcar como cambio'"
                        @click="line.is_substitute = !line.is_substitute"
                      />
                      <v-btn icon="mdi-close" size="x-small" variant="text" color="error" title="Quitar del lineup" @click="batting[battingSide].splice(index, 1)" />
                    </td>
                  </tr>
                  <tr v-if="batting[battingSide].length === 0">
                    <td :colspan="mode === 'scorecard' ? battingColumns.length + visibleInnings.length + (slimActive ? 2 : 3) : battingColumns.length + 4" class="text-center text-grey-darken-1 pa-4">
                      Sin bateadores. Usa <strong>Agregar bateador</strong> para armar el lineup.
                    </td>
                  </tr>
                  <tr v-else class="totals-row">
                    <td></td>
                    <td class="text-left font-weight-bold">Totales</td>
                    <td v-if="!slimActive"></td>
                    <template v-if="mode === 'scorecard'">
                      <td v-for="inning in visibleInnings" :key="`inning-${inning}`" class="font-weight-bold" :class="{ 'inning--pending': inningState(battingSide, inning) === 'pending' }" title="Carreras de la entrada">{{ inningRunsLabel(battingSide, inning) }}</td>
                    </template>
                    <td v-for="column in battingColumns" :key="column.key" class="font-weight-bold">{{ columnTotal(battingSide, column.key) }}</td>
                    <td v-if="mode !== 'scorecard'"></td>
                  </tr>
                  <tr v-if="mode === 'scorecard' && batting[battingSide].length" class="totals-row lob-row">
                    <td></td>
                    <td class="text-left font-weight-bold" title="Dejados en base (Left On Base)">LOB</td>
                    <td v-if="!slimActive"></td>
                    <td
                      v-for="inning in visibleInnings"
                      :key="`lob-${inning}`"
                      :class="{ 'inning--pending': inningState(battingSide, inning) !== 'played' }"
                      :title="inningState(battingSide, inning) === 'played' ? 'Corredores que quedaron en base al terminar la entrada' : 'La entrada no ha terminado'"
                    >
                      {{ inningState(battingSide, inning) === 'played' ? inningLob(battingSide, inning) : '–' }}
                    </td>
                    <td :colspan="battingColumns.length" class="text-left font-weight-bold">{{ teamLob(battingSide) }} en el juego</td>
                  </tr>
                </tbody>
              </table>
            </div>
            </div>

            <!-- Captura de la jugada del turno: Jugada, Anotación, Corrido, Impulsadas (y DP, out en base...) -->
            <aside v-if="mode === 'scorecard'" class="sc-layout__side play-panel">
                <div v-if="showPlayColumn" class="play-panel__play">
                  <div class="play-panel__label">Jugada</div>
                  <div class="play-panel__options">
                    <div v-for="group in RESULT_GROUPS" :key="group.title" class="play-panel__group">
                      <span class="text-caption text-grey-darken-1 mr-1">{{ group.title }}</span>
                      <v-chip
                        v-for="item in group.items"
                        :key="item.value"
                        size="small"
                        label
                        :title="item.title"
                        :color="panelPlay.result === item.value ? group.color : undefined"
                        :variant="panelPlay.result === item.value ? 'flat' : 'outlined'"
                        class="font-weight-bold"
                        @click="chooseResult(item.value)"
                      >
                        {{ item.label || item.value }}
                      </v-chip>
                    </div>
                  </div>

                  <div class="play-panel__label">Anotación</div>
                  <div v-if="!panelPlay.result" class="play-panel__options" title="Elige primero la jugada">
                    <v-chip v-for="position in NOTATION_BUTTONS" :key="position" size="small" label variant="outlined" disabled>{{ position }}</v-chip>
                  </div>
                  <template v-if="panelPlay.result">
                    <div class="play-panel__options">
                      <template v-for="(notation, notationIndex) in notationOptions(panelPlay)" :key="notation.value">
                        <span
                          v-if="notation.group && notation.group !== notationOptions(panelPlay)[notationIndex - 1]?.group"
                          class="text-caption text-grey-darken-1 ml-1 mr-1"
                        >{{ notation.group }}</span>
                        <v-chip
                          size="small"
                          label
                          :title="notation.title"
                          :color="panelPlay.notation === notation.value ? 'primary' : undefined"
                          :variant="panelPlay.notation === notation.value ? 'flat' : 'outlined'"
                          @click="panelPlay.notation = panelPlay.notation === notation.value ? '' : notation.value"
                        >
                          {{ notation.group === 'Desatendido (U)' ? notation.value.slice(0, -1) : notation.value }}
                        </v-chip>
                      </template>
                      <input v-model="panelPlay.notation" maxlength="20" placeholder="Otra..." class="notation-input" :autocomplete="NO_AUTOFILL" title="Anotación libre" />
                    </div>

                    <!-- Doble play: qué corredor fue el otro out (se le cuenta antes que el del bateador) -->
                    <template v-if="panelPlay.result === 'DP'">
                      <div class="play-panel__label">Doble play</div>
                      <div class="play-panel__options">
                        <span class="text-caption text-grey-darken-1 mr-1">Out del corredor en</span>
                        <v-chip
                          v-for="runner in panelDpCandidates"
                          :key="runner.line.uid"
                          size="small"
                          label
                          prepend-icon="mdi-close-circle-outline"
                          :title="`${playerName(runner.line)} fue el otro out del doble play`"
                          :color="panelPlay.dp_runner === runner.line ? 'error' : undefined"
                          :variant="panelPlay.dp_runner === runner.line ? 'flat' : 'outlined'"
                          @click="setDpRunner(runner.line)"
                        >
                          {{ BASES.find(base => base.value === runner.play.bases)?.label }} · {{ playerName(runner.line) }}
                        </v-chip>
                        <span v-if="panelDpCandidates.length === 0" class="text-caption text-grey-darken-1 ml-1">
                          Sin corredores en base: el DP cuenta solo el out del bateador.
                        </span>
                      </div>
                    </template>

                    <template v-if="RUNNER_OUTS_IN_PLAY[panelPlay.result]">
                      <div class="play-panel__label">Corredor out</div>
                      <div class="play-panel__options">
                        <v-chip
                          v-for="runner in runnerCandidates"
                          :key="runner.line.uid"
                          size="small"
                          label
                          prepend-icon="mdi-close-circle-outline"
                          :title="`${playerName(runner.line)} fue el corredor puesto out en esta jugada`"
                          :color="isRunnerOutHere(runner.play) ? 'error' : undefined"
                          :variant="isRunnerOutHere(runner.play) ? 'flat' : 'outlined'"
                          @click="toggleRunnerOut(runner.play)"
                        >
                          {{ playerName(runner.line) }}
                        </v-chip>
                        <span v-if="runnerCandidates.length === 0" class="text-caption text-grey-darken-1 ml-1">
                          No hay corredores en base en esta entrada.
                        </span>
                      </div>
                    </template>

                  </template>

                    <div class="play-panel__label">Corrido</div>
                    <div class="play-panel__options">
                      <span class="text-caption text-grey-darken-1 mr-1">Llegó a</span>
                      <v-chip
                        v-for="base in BASES"
                        :key="base.value"
                        size="small"
                        label
                        :title="base.title"
                        :color="panelPlay.bases === base.value ? (base.value === 4 ? 'success' : 'primary') : undefined"
                        :variant="panelPlay.bases === base.value ? 'flat' : 'outlined'"
                        :disabled="!reachesBase(panelPlay) || panelPlay.result === 'HR'"
                        @click="setBases(panelPlay, base.value)"
                      >
                        {{ base.label }}
                      </v-chip>
                      <v-chip
                        size="small"
                        label
                        class="ml-3"
                        prepend-icon="mdi-close-circle-outline"
                        title="Lo pusieron out en las bases en una jugada posterior. Indica en el turno de quién: si fue en un DP o FC, ese out ya es parte de la jugada y no se cuenta dos veces."
                        :color="panelPlay.out_on_base ? 'error' : undefined"
                        :variant="panelPlay.out_on_base ? 'flat' : 'outlined'"
                        :disabled="panelPlay.bases < 1 || panelPlay.bases > 3 || !!dpOutOf(panelPlay)"
                        @click="setOutOnBase(panelPlay, !panelPlay.out_on_base)"
                      >
                        Out en base
                      </v-chip>
                      <v-chip
                        size="small"
                        label
                        title="Out por revire (pickoff): el lanzador o el receptor lo sorprendió fuera de la base"
                        :color="panelPlay.out_on_base && panelPlay.out_type === 'PK' ? 'error' : undefined"
                        :variant="panelPlay.out_on_base && panelPlay.out_type === 'PK' ? 'flat' : 'outlined'"
                        :disabled="panelPlay.bases < 1 || panelPlay.bases > 3 || !!dpOutOf(panelPlay)"
                        @click="togglePickoff(panelPlay)"
                      >
                        PK
                      </v-chip>
                      <v-chip
                        size="small"
                        label
                        title="Out en doble play: lo forzaron en la jugada de un bateador posterior"
                        :color="dpOutOf(panelPlay) ? 'error' : undefined"
                        :variant="dpOutOf(panelPlay) ? 'flat' : 'outlined'"
                        :disabled="turnIndex === null || !(dpOutOf(panelPlay) || (panelDpAvailable && !panelPlay.out_on_base))"
                        @click="toggleRunnerDp"
                      >
                        DP
                      </v-chip>
                      <span class="text-caption text-grey-darken-1 ml-3 mr-1 steal-caption">Robó</span>
                      <v-chip
                        v-for="steal in STEALS"
                        :key="steal.value"
                        size="small"
                        label
                        :title="steal.title"
                        :color="panelPlay.stolen_bases?.includes(steal.value) ? 'deep-purple' : undefined"
                        :variant="panelPlay.stolen_bases?.includes(steal.value) ? 'flat' : 'outlined'"
                        :disabled="!reachesBase(panelPlay)"
                        @click="toggleSteal(panelPlay, steal.value)"
                      >
                        {{ steal.label }}
                      </v-chip>
                    </div>

                    <!-- Corredor out en un DP: su parte de la jugada (6-4, 5-4...) -->
                    <template v-if="dpOutOf(panelPlay)">
                      <div class="play-panel__label">Out en DP</div>
                      <div class="play-panel__options">
                        <template v-for="(notation, notationIndex) in DP_RUNNER_NOTATIONS" :key="`${notation.group}-${notation.value}`">
                          <span v-if="notation.group !== DP_RUNNER_NOTATIONS[notationIndex - 1]?.group" class="text-caption text-grey-darken-1 ml-1 mr-1">{{ notation.group }}</span>
                          <v-chip
                            size="small"
                            label
                            :title="notation.title"
                            :color="panelPlay.dp_notation === notation.value ? 'error' : undefined"
                            :variant="panelPlay.dp_notation === notation.value ? 'flat' : 'outlined'"
                            @click="panelPlay.dp_notation = panelPlay.dp_notation === notation.value ? '' : notation.value"
                          >
                            {{ notation.group === 'Desatendido (U)' ? notation.value.slice(0, -1) : notation.value }}
                          </v-chip>
                        </template>
                        <input v-model="panelPlay.dp_notation" maxlength="20" placeholder="Otra..." class="notation-input" :autocomplete="NO_AUTOFILL" title="Out del corredor en el doble play" />
                      </div>
                    </template>

                    <template v-if="panelPlay.out_on_base">
                      <template v-if="panelPlay.out_type === 'PK'">
                        <div class="play-panel__label">Revire</div>
                        <div class="play-panel__options">
                          <v-chip
                            v-for="notation in PICKOFFS"
                            :key="notation.value"
                            size="small"
                            label
                            :title="notation.title"
                            :color="panelPlay.out_notation === notation.value ? 'error' : undefined"
                            :variant="panelPlay.out_notation === notation.value ? 'flat' : 'outlined'"
                            @click="setOutNotation(panelPlay, notation.value, 'PK')"
                          >
                            {{ notation.value }}
                          </v-chip>
                          <input v-model="panelPlay.out_notation" maxlength="20" placeholder="1-3" class="notation-input" :autocomplete="NO_AUTOFILL" title="Secuencia del revire: 1-3, 1-6, 2-6, 1-6-4..." />
                        </div>
                      </template>
                      <template v-else>
                        <div class="play-panel__label">Pisa y corre</div>
                        <div class="play-panel__options">
                          <span class="text-caption text-grey-darken-1 mr-1">Out en</span>
                          <v-chip
                            v-for="notation in TAG_OUTS"
                            :key="notation.value"
                            size="small"
                            label
                            :title="notation.title"
                            :color="panelPlay.out_type === 'PC' && panelPlay.out_notation === notation.value ? 'error' : undefined"
                            :variant="panelPlay.out_type === 'PC' && panelPlay.out_notation === notation.value ? 'flat' : 'outlined'"
                            @click="setOutNotation(panelPlay, notation.value, 'PC')"
                          >
                            {{ notation.value }}
                          </v-chip>
                        </div>
                        <div class="play-panel__label">Otro out</div>
                        <div class="play-panel__options">
                          <v-chip
                            v-for="notation in RUNNER_OUTS"
                            :key="notation.value"
                            size="small"
                            label
                            :title="notation.title"
                            :color="!panelPlay.out_type && panelPlay.out_notation === notation.value ? 'error' : undefined"
                            :variant="!panelPlay.out_type && panelPlay.out_notation === notation.value ? 'flat' : 'outlined'"
                            @click="setOutNotation(panelPlay, notation.value)"
                          >
                            {{ notation.value }}
                          </v-chip>
                          <input v-model="panelPlay.out_notation" maxlength="20" placeholder="Otra..." class="notation-input" :autocomplete="NO_AUTOFILL" title="Cómo lo pusieron out" />
                        </div>
                      </template>

                      <div class="play-panel__label">En el turno de</div>
                      <div class="play-panel__options">
                        <v-chip
                          size="small"
                          label
                          title="El out ocurrió durante el turno de este mismo bateador"
                          :color="!panelPlay.out_during ? 'error' : undefined"
                          :variant="!panelPlay.out_during ? 'flat' : 'outlined'"
                          @click="panelPlay.out_during = null"
                        >
                          Este turno
                        </v-chip>
                        <v-chip
                          v-for="candidate in outDuringOptions"
                          :key="candidate.uid"
                          size="small"
                          label
                          :color="panelPlay.out_during === candidate ? 'error' : undefined"
                          :variant="panelPlay.out_during === candidate ? 'flat' : 'outlined'"
                          @click="panelPlay.out_during = candidate"
                        >
                          {{ playerName(candidate) }}
                        </v-chip>
                        <span v-if="outDuringOptions.length === 0" class="text-caption text-grey-darken-1 ml-1">
                          Si fue en el turno de otro bateador, captura ese turno y regresa con Anterior para indicarlo.
                        </span>
                      </div>
                    </template>

                    <div class="play-panel__label">Impulsadas</div>
                    <div class="play-panel__options">
                      <span class="text-caption text-grey-darken-1 mr-1">RBI</span>
                      <v-chip
                        v-for="rbi in [0, 1, 2, 3, 4]"
                        :key="rbi"
                        size="small"
                        label
                        :color="panelPlay.result && toInt(panelPlay.rbi) === rbi ? 'success' : undefined"
                        :variant="panelPlay.result && toInt(panelPlay.rbi) === rbi ? 'flat' : 'outlined'"
                        :disabled="!panelPlay.result || (rbi === 0 && panelPlay.result === 'HR')"
                        @click="setRbi(panelPlay, rbi)"
                      >
                        {{ rbi }}
                      </v-chip>
                    </div>
                </div>
              <div v-else class="pa-3 text-body-2 text-grey-darken-1">Haz clic en una casilla de entrada para capturar la jugada.</div>
            </aside>
            </div>

            <!-- En el Scorecard estos botones están en el panel de captura -->
            <template v-if="mode !== 'scorecard'">
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
            <v-btn
              size="small"
              variant="outlined"
              color="success"
              class="text-none font-weight-bold mt-3 ml-2"
              prepend-icon="mdi-clipboard-account"
              title="Abre el roster del equipo; con doble clic en un jugador se agrega al lineup"
              :disabled="rosterIsEmpty"
              @click="openRosterDialog"
            >
              Agregar del roster
            </v-btn>
            </template>
          </template>
        </template>
      </v-card-text>

      <!-- Glosario: significado de las abreviaciones del scorecard -->
      <v-dialog v-model="glossaryDialog" max-width="900px" scrollable>
        <v-card class="rounded-xl pa-2" theme="ejp">
          <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
            <span class="text-h6 font-weight-black text-grey-darken-4">Glosario</span>
            <v-btn icon="mdi-close" variant="text" density="comfortable" @click="glossaryDialog = false" />
          </v-card-title>
          <v-divider class="my-2" />
          <v-card-text class="pt-2">
            <v-row dense>
              <v-col v-for="section in GLOSSARY" :key="section.title" cols="12" md="6">
                <div class="glossary-title">{{ section.title }}</div>
                <div v-if="section.note" class="modal-note text-grey-darken-1 mb-1">{{ section.note }}</div>
                <table class="glossary-table">
                  <tbody>
                    <tr v-for="entry in section.entries" :key="entry.term">
                      <td class="glossary-term">{{ entry.term }}</td>
                      <td>{{ entry.meaning }}</td>
                    </tr>
                  </tbody>
                </table>
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="pa-4 pt-0 justify-end ga-2">
            <v-btn
              variant="outlined"
              color="black"
              class="text-capitalize rounded-pill px-5 font-weight-bold"
              prepend-icon="mdi-file-pdf-box"
              :loading="exportingGlossary"
              @click="exportGlossary"
            >
              PDF
            </v-btn>
            <v-btn color="black" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" @click="glossaryDialog = false">Listo</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Game Stats: estadísticas del jugador en el partido (como en The Perfect Boxscore) -->
      <v-dialog v-model="gameStatsDialog" max-width="760px">
        <v-card v-if="gameStatsLine && game" class="rounded-xl pa-2" theme="ejp">
          <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
            <span class="text-h6 font-weight-black text-grey-darken-4">Game Stats</span>
            <v-btn icon="mdi-close" variant="text" density="comfortable" @click="gameStatsDialog = false" />
          </v-card-title>
          <v-card-text class="d-flex align-center ga-5">
            <v-avatar size="96" color="grey-lighten-2" class="text-h5 font-weight-black text-grey-darken-2">
              {{ initials(playerName(gameStatsLine)) }}
            </v-avatar>
            <div class="flex-grow-1">
              <div class="text-h6 font-weight-black text-grey-darken-4">{{ playerName(gameStatsLine) }}</div>
              <div class="text-body-2 text-grey-darken-1 mb-3">{{ gameStatsInfo }}</div>
              <table class="box-table game-stats-table">
                <thead>
                  <tr><th v-for="column in SCORECARD_COLUMNS" :key="column.key" :title="column.title">{{ column.label }}</th></tr>
                </thead>
                <tbody>
                  <tr><td v-for="column in SCORECARD_COLUMNS" :key="column.key" class="font-weight-bold">{{ statValue(gameStatsLine, column.key) }}</td></tr>
                </tbody>
              </table>
            </div>
          </v-card-text>
          <v-card-actions class="pa-4 pt-0 justify-end">
            <v-btn color="black" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" @click="gameStatsDialog = false">Listo</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Roster del equipo: doble clic en un jugador para agregarlo al lineup -->
      <v-dialog v-model="rosterDialog" max-width="720px" scrollable>
        <v-card v-if="game" class="rounded-xl pa-2" theme="ejp">
          <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
            <div>
              <div class="text-h6 font-weight-black text-grey-darken-4">Roster de {{ teamOf(battingSide).name }}</div>
              <div class="text-body-2 text-grey-darken-1">Doble clic en un jugador para agregarlo al lineup</div>
            </div>
            <v-btn icon="mdi-close" variant="text" density="comfortable" @click="rosterDialog = false" />
          </v-card-title>
          <v-card-text>
            <v-text-field
              v-model="rosterSearch"
              :autocomplete="NO_AUTOFILL"
              :name="fieldName('roster-search')"
              prepend-inner-icon="mdi-magnify"
              placeholder="Buscar jugador..."
              variant="outlined"
              density="compact"
              hide-details
              clearable
              class="mb-3"
            />
            <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white overflow-hidden">
              <v-data-table
                :headers="ROSTER_HEADERS"
                :items="rosterRows"
                :search="rosterSearch"
                :items-per-page="10"
                item-value="id"
                class="roster-table bg-white"
                density="compact"
                hover
                no-data-text="Todos los jugadores del roster ya están en el lineup."
                @dblclick:row="(_, { item }) => addFromRoster(item)"
              >
                <template v-slot:item.name="{ item }">
                  <span class="font-weight-bold text-black">{{ item.name }}</span>
                </template>
                <template v-slot:item.actions="{ item }">
                  <v-btn icon="mdi-plus" size="x-small" variant="text" color="success" title="Agregar al lineup" @click="addFromRoster(item)" />
                </template>
              </v-data-table>
            </v-card>
            <div class="text-caption text-grey-darken-1 mt-2">
              Si hay turnos sin jugador, el jugador ocupa el primero de ellos; si no, se agrega al final como titular.
            </div>
          </v-card-text>
          <v-card-actions class="pa-4 pt-0 justify-end">
            <v-btn color="black" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" @click="rosterDialog = false">Listo</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Sin Cancelar / Guardar: los cambios se guardan solos (ver la página GameBoxscore) -->
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { formatDate, formatTime } from '@/utils/dates'
import { downloadFile } from '@/utils/download'
import { getErrorMessage } from '@/utils/errors'
import { useNotificationsStore } from '@/stores/notifications'

const props = defineProps({
  // Respuesta de GET /boxscore: { game, players, positions }
  boxscore: Object,
  loading: Boolean,
  saving: Boolean
})

// change: el box score cambió; trae lo que hay que guardar (la página lo autoguarda)
const emit = defineEmits(['change'])

const SIDES = ['away', 'home']

const MODES = [
  { value: 'score', title: 'Score', hint: 'Marcador final por equipo: carreras (R), hits (H) y errores (E).' },
  { value: 'linescore', title: 'Line Score', hint: 'Carreras por entrada y R H E. Las carreras se suman de las entradas; deja vacía la última del local si no batea (X).' },
  { value: 'batting', title: 'Bateo', hint: 'R H E y el lineup de cada equipo con AB, H, R, RBI y K.' },
  { value: 'batting_full', title: 'Bateo detallado', hint: 'Lineup con PA, AB, 1B, 2B, 3B, HR, R, RBI, BB, IBB, HBP, SF, SH, ROE, KS, KC. H y K se calculan; las carreras y hits del equipo son la suma de su lineup.' },
  { value: 'scorecard', title: 'Scorecard', hint: 'Haz clic en la casilla del bateador en la entrada y elige su jugada en el panel de captura. El bateo, las carreras por entrada, R y H se calculan de los turnos; E se captura en el marcador.' }
]

// Jugadas del scorecard por grupo (mismas claves que GamePlateAppearance::RESULTS); label = lo que se ve en la casilla
const RESULT_GROUPS = [
  {
    title: 'Hit',
    color: 'primary',
    items: [
      { value: '1B', title: 'Sencillo' },
      { value: 'IH', title: 'Hit de cuadro (infield hit): sencillo que no salió del cuadro' },
      { value: '2B', title: 'Doble' },
      { value: '3B', title: 'Triple' },
      { value: 'HR', title: 'Home run' }
    ]
  },
  {
    title: 'Embasado',
    color: 'teal',
    items: [
      { value: 'BB', title: 'Base por bolas' },
      { value: 'IBB', title: 'Base por bolas intencional' },
      { value: 'HBP', title: 'Golpeado por lanzamiento' },
      { value: 'KDS', label: 'K+', title: 'Ponche tirándole, se embasa: tercer strike no retenido (dropped third strike)' },
      { value: 'KDC', label: 'ꓘ+', title: 'Ponche cantado, se embasa: tercer strike no retenido (dropped third strike)' }
    ]
  },
  {
    title: 'Out',
    color: 'grey-darken-3',
    items: [
      { value: 'KS', label: 'K', title: 'Ponche tirándole' },
      { value: 'KC', label: 'ꓘ', title: 'Ponche cantado' },
      { value: 'GO', title: 'Rodado de out' },
      { value: 'FO', title: 'Elevado de out' },
      { value: 'LO', title: 'Línea de out' },
      { value: 'DP', title: 'Doble play' }
    ]
  },
  {
    title: 'Otro',
    color: 'orange-darken-2',
    items: [
      { value: 'FC', title: 'Bola ocupada' },
      { value: 'ROE', title: 'Se embasó por error de la defensiva (Reached On Error)' },
      { value: 'SF', title: 'Elevado de sacrificio' },
      { value: 'SH', title: 'Toque de sacrificio' }
    ]
  }
]

const RESULTS = RESULT_GROUPS.flatMap(group => group.items)

// Posiciones defensivas del scorebook, con su forma "al / del / el" para las descripciones
const FIELDERS = {
  1: { a: 'al pitcher', de: 'del pitcher', el: 'el pitcher' },
  2: { a: 'al catcher', de: 'del catcher', el: 'el catcher' },
  3: { a: 'a primera base', de: 'de primera base', el: 'el de primera base' },
  4: { a: 'a segunda base', de: 'de segunda base', el: 'el de segunda base' },
  5: { a: 'a tercera base', de: 'de tercera base', el: 'el de tercera base' },
  6: { a: 'al short stop', de: 'del short stop', el: 'el short stop' },
  7: { a: 'al jardín izquierdo', de: 'del jardín izquierdo', el: 'el jardinero izquierdo' },
  8: { a: 'al jardín central', de: 'del jardín central', el: 'el jardinero central' },
  9: { a: 'al jardín derecho', de: 'del jardín derecho', el: 'el jardinero derecho' }
}

// Una anotación por posición, en orden: byPosition('F', f => `Elevado ${f.a}`) -> F1..F9
const byPosition = (prefix, describe, positions = [1, 2, 3, 4, 5, 6, 7, 8, 9]) =>
  positions.map(position => ({ value: `${prefix}${position}`, title: `${prefix}${position}: ${describe(FIELDERS[position])}` }))

const withTitles = items => items.map(([value, title]) => ({ value, title: `${value}: ${title}` }))

// Callejones entre dos jardineros: 78 izquierdo-central (left-center), 89 central-derecho (right-center)
const GAPS = {
  7: { value: '78', name: 'entre el jardín izquierdo y el central' },
  8: { value: '89', name: 'entre el jardín central y el derecho' }
}

// Agrega el callejón después del jardinero con el que empieza (7, 78, 8, 89, 9)
const withGaps = (notations, describe) => notations.flatMap((notation) => {
  const gap = GAPS[notation.value]
  return gap ? [notation, { value: gap.value, title: `${gap.value}: ${describe(gap.name)}` }] : [notation]
})

// Quién fildea y a quién tira (como en The Perfect Boxscore); "U" = desatendido (el mismo fildeador hace el out)
const BASE_OF_GROUP = { 'Out en 1ra': 'primera', 'Cubriendo 1ra': 'primera', 'Forzado en 2da': 'segunda', 'En 3ra': 'tercera', 'En home': 'home' }
const fieldingCodes = (group, codes, prefix = 'Rodado') => codes.map(code => ({
  value: code,
  group,
  title: code.endsWith('U')
    ? `${code}: ${prefix} ${FIELDERS[code[0]].a}, out desatendido`
    : `${code}: ${prefix} ${FIELDERS[code[0]].a}, tira ${FIELDERS[code[code.length - 1]].a}: out en ${BASE_OF_GROUP[group]}`
}))

// Rodado (y la parte del bateador en un DP): el bateador solo puede ser out en primera
const GROUND_OUTS = [
  ...fieldingCodes('Out en 1ra', ['1-3', '2-3', '4-3', '5-3', '6-3', '7-3', '8-3', '9-3']),
  ...fieldingCodes('Cubriendo 1ra', ['3-1', '4-1', '3-4']),
  ...fieldingCodes('Desatendido (U)', ['1U', '2U', '3U', '4U', '5U', '6U'])
]

// Out del corredor en un doble play (en su propio turno): forzado en otra base
const DP_RUNNER_NOTATIONS = [
  ...fieldingCodes('Forzado en 2da', ['1-4', '1-6', '2-4', '2-6', '3-6', '4-6', '5-4', '5-6', '6-4'], 'Corredor out: rodado'),
  ...fieldingCodes('En 3ra', ['1-5', '2-5', '3-5', '4-5', '6-5'], 'Corredor out: rodado'),
  ...fieldingCodes('En home', ['1-2', '3-2', '4-2', '5-2', '6-2'], 'Corredor out: rodado'),
  ...fieldingCodes('Desatendido (U)', ['1U', '2U', '4U', '5U', '6U'], 'Corredor out: rodado')
]

// Out en base en pisa y corre: tiro de un jardinero (7, 8, 9) a una posición del cuadro del 1 al 4 (7-2: el izquierdo tira a home)
const TAG_OUTS = [7, 8, 9].flatMap(outfielder => [1, 2, 3, 4].map(infielder => ({
  value: `${outfielder}-${infielder}`,
  title: `Out en pisa y corre: ${FIELDERS[outfielder].el} tira ${FIELDERS[infielder].a}`
})))

// Secuencias de revire (pickoff) más comunes
const PICKOFFS = withTitles([
  ['1-3', 'El pitcher sorprende al corredor de primera'],
  ['1-6', 'El pitcher sorprende al corredor de segunda (cubre el short)'],
  ['1-4', 'El pitcher sorprende al corredor de segunda (cubre segunda base)'],
  ['2-3', 'El catcher sorprende al corredor de primera'],
  ['2-6', 'El catcher sorprende al corredor de segunda'],
  ['1-6-4', 'Revire con corretiza entre bases']
])

// Bases robadas en el turno: base que se robó -> etiqueta (SB H = robo de home)
const STEALS = [
  { value: 2, label: 'SB 2', short: '2', title: 'Robo de segunda base' },
  { value: 3, label: 'SB 3', short: '3', title: 'Robo de tercera base' },
  { value: 4, label: 'SB H', short: 'H', title: 'Robo de home' }
]

// Número de out encerrado en círculo (DP②)
const CIRCLED = { 1: '①', 2: '②', 3: '③' }

// Anotaciones de scorebook sugeridas por jugada, ordenadas por posición.
// En los hits la anotación es hacia dónde fue la bola y la casilla muestra p. ej. 1B-8 o HR-78
const NOTATIONS = {
  '1B': withGaps(byPosition('', f => `Sencillo ${f.a}`), gap => `Sencillo ${gap}`),
  IH: byPosition('', f => `Hit de cuadro ${f.a}`, [1, 2, 3, 4, 5, 6]),
  '2B': withGaps(byPosition('', f => `Doble ${f.a}`, [5, 7, 8, 9]), gap => `Doble ${gap}`),
  '3B': withGaps(byPosition('', f => `Triple ${f.a}`, [7, 8, 9]), gap => `Triple ${gap}`),
  HR: withGaps(byPosition('', f => `Home run por ${f.a.replace(/^a(l)? /, 'e$1 ')}`, [7, 8, 9]), gap => `Home run ${gap}`),
  GO: GROUND_OUTS,
  FO: byPosition('F', f => `Fly/Elevado ${f.a}`),
  // Línea de out: solo la atrapa el cuadro, sin el catcher (1, 3, 4, 5, 6)
  LO: byPosition('L', f => `Línea ${f.a}`, [1, 3, 4, 5, 6]),
  // En el DP del bateador se anota su parte (4-3); la del corredor (6-4) va en la casilla del corredor
  DP: GROUND_OUTS,
  FC: byPosition('FC', f => `Bola ocupada, la fildea ${f.el}`, [1, 2, 3, 4, 5, 6]),
  ROE: byPosition('E', f => `Error ${f.de}`),
  SF: byPosition('SF', f => `Elevado de sacrificio ${f.a}`, [7, 8, 9]),
  KDS: withTitles([['WP', 'Wild pitch: el lanzamiento se le pasó al catcher'], ['PB', 'Passed ball: el catcher no retuvo la pelota']]),
  KDC: withTitles([['WP', 'Wild pitch: el lanzamiento se le pasó al catcher'], ['PB', 'Passed ball: el catcher no retuvo la pelota']]),
  SH: withTitles([
    ['SH1-3', 'Toque de sacrificio al pitcher, out en primera'],
    ['SH2-3', 'Toque de sacrificio al catcher, out en primera'],
    ['SH3-4', 'Toque de sacrificio a primera, out con segunda cubriendo'],
    ['SH5-3', 'Toque de sacrificio a tercera, out en primera']
  ])
}

// Estadística que suma cada jugada; BB, IBB, HBP, SF y SH no cuentan como turno oficial (AB)
const RESULT_STATS = {
  '1B': ['singles', 'h'],
  IH: ['singles', 'ih', 'h'],
  '2B': ['doubles', 'h'],
  '3B': ['triples', 'h'],
  HR: ['hr', 'h'],
  BB: ['bb'],
  IBB: ['bb', 'ibb'],
  HBP: ['hbp'],
  KS: ['so_swinging', 'so'],
  KC: ['so_looking', 'so'],
  KDS: ['so_swinging', 'so'],
  KDC: ['so_looking', 'so'],
  SF: ['sf'],
  SH: ['sh'],
  ROE: ['roe'],
  // Bateó para doble play (GIDP)
  DP: ['gidp']
}
const NOT_AT_BAT = ['BB', 'IBB', 'HBP', 'SF', 'SH']

// Outs de la jugada del bateador (reglas de The Perfect Boxscore). En un DP es solo el del bateador: el del
// corredor se suma cuando se indica quién fue. En la bola ocupada (FC) el bateador se embasa y no hay out del
// bateador: el corredor puesto out se marca como "Out en base" (o con "Corredor out" en el panel del FC)
const OUTS = { KS: 1, KC: 1, GO: 1, FO: 1, LO: 1, SF: 1, SH: 1, DP: 1 }

// Jugadas en que se puede marcar desde el panel al corredor que pusieron out (queda como su "Out en base")
const RUNNER_OUTS_IN_PLAY = { FC: 1 }

// Jugadas en que el bateador se embasa y la base a la que llega de entrada (luego puede avanzar)
const START_BASE = { '1B': 1, IH: 1, '2B': 2, '3B': 3, HR: 4, BB: 1, IBB: 1, HBP: 1, KDS: 1, KDC: 1, FC: 1, ROE: 1 }

const BASES = [
  { value: 1, label: '1ra', title: 'Se quedó en primera' },
  { value: 2, label: '2da', title: 'Llegó a segunda' },
  { value: 3, label: '3ra', title: 'Llegó a tercera' },
  { value: 4, label: 'Home', title: 'Llegó a home: anotó carrera' }
]


// Outs del corredor en las bases (CS = atrapado robando, PO = pick-off), ordenados por quien inicia la jugada
const RUNNER_OUTS = [
  ['1-5', 'Out en tercera: del pitcher a tercera'],
  ['1-6', 'Out en segunda: del pitcher al short'],
  ['2-5', 'Out en tercera: del catcher a tercera'],
  ['3-6', 'Out en segunda: de primera al short'],
  ['4-6', 'Out en segunda: de segunda base al short'],
  ['5-4', 'Out en segunda: de tercera a segunda base'],
  ['6-4', 'Out en segunda: del short a segunda base'],
  ['6-5', 'Out en tercera: del short a tercera'],
  ['CS2-4', 'Atrapado robando segunda: del catcher a segunda base'],
  ['CS2-6', 'Atrapado robando segunda: del catcher al short'],
  ['CS2-5', 'Atrapado robando tercera: del catcher a tercera']
  // Los tiros de jardineros (7-2) van en "Pisa y corre" y los pick-offs en "PK", como en The Perfect Boxscore
].map(([value, title]) => ({ value, title: `${value}: ${title}` }))

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
  { key: 'ih', label: 'IH', title: 'Hits de cuadro (incluidos en 1B)' },
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
  { key: 'roe', label: 'ROE', title: 'Se embasó por error de la defensiva (Reached On Error)' },
  { key: 'so', label: 'K', title: 'Ponches (KS + KC)', derived: true },
  { key: 'so_swinging', label: 'KS', title: 'Ponches tirándole' },
  { key: 'so_looking', label: 'KC', title: 'Ponches sin tirarle (cantados)' },
  { key: 'lob', label: 'LOB', title: 'Corredores que dejó en base al hacer el tercer out' }
]

// En el scorecard todo el bateo se calcula de los turnos; mismas estadísticas por jugador que The Perfect Boxscore
const SCORECARD_COLUMNS = [
  { key: 'ab', label: 'AB', title: 'Turnos oficiales al bat', derived: true },
  { key: 'r', label: 'R', title: 'Carreras anotadas', derived: true },
  { key: 'h', label: 'H', title: 'Hits', derived: true },
  { key: 'rbi', label: 'RBI', title: 'Carreras impulsadas', derived: true },
  { key: 'bb', label: 'BB', title: 'Bases por bolas', derived: true },
  { key: 'so', label: 'K', title: 'Ponches', derived: true },
  { key: 'sb', label: 'SB', title: 'Bases robadas', derived: true },
  { key: 'pk', label: 'PK', title: 'Outs por revire (pickoff)', derived: true },
  { key: 'gidp', label: 'DP', title: 'Bateó para doble play', derived: true },
  { key: 'lob', label: 'LOB', title: 'Veces que se quedó en base al terminar la entrada', derived: true },
  { key: 'avg', label: 'AVG', title: 'Promedio de bateo (H / AB)', derived: true, rate: true }
]

// Glosario: jugadas, posiciones, anotaciones, la casilla del scorecard y las columnas de bateo
const glossaryDialog = ref(false)
const FIELDER_NAMES = ['Pitcher', 'Catcher', 'Primera base', 'Segunda base', 'Tercera base', 'Short stop', 'Jardín izquierdo', 'Jardín central', 'Jardín derecho']
const GLOSSARY = [
  ...RESULT_GROUPS.map(group => ({
    title: `Jugadas: ${group.title}`,
    entries: group.items.map(item => ({ term: item.label || item.value, meaning: item.title }))
  })),
  {
    title: 'Posiciones',
    note: 'Cada fildeador se anota con su número.',
    entries: [
      ...FIELDER_NAMES.map((name, index) => ({ term: String(index + 1), meaning: name })),
      ...Object.values(GAPS).map(gap => ({ term: gap.value, meaning: `Callejón ${gap.name}` }))
    ]
  },
  {
    title: 'Anotaciones',
    entries: [
      { term: '1B-8', meaning: 'Hit y hacia dónde fue la bola (sencillo al jardín central)' },
      { term: '6-3', meaning: 'Rodado: quién fildeó y a quién tiró para el out (short a primera)' },
      { term: '3U', meaning: 'Out sin asistencia: el mismo fildeador hizo el out' },
      { term: 'F8', meaning: 'Elevado (fly) de out al fildeador indicado' },
      { term: 'L6', meaning: 'Línea de out al fildeador indicado' },
      { term: '6-4-3 DP', meaning: 'Doble play con la secuencia de fildeadores' },
      { term: 'SF8', meaning: 'Elevado de sacrificio al fildeador indicado' },
      { term: 'SH1-3', meaning: 'Toque de sacrificio: quién lo fildeó y dónde fue el out' },
      { term: 'FC6', meaning: 'Bola ocupada: el fildeador prefirió sacar a otro corredor' },
      { term: 'E6', meaning: 'Error del fildeador indicado' },
      { term: 'WP / PB', meaning: 'Wild pitch del pitcher / passed ball del catcher' },
      { term: 'CS2-6', meaning: 'Atrapado robando: del catcher al short' },
      { term: 'PK 1-3', meaning: 'Pick-off (revire): del pitcher a primera' },
      { term: 'PC 7-2', meaning: 'Pisa y corre: el jardinero izquierdo tira a home' }
    ]
  },
  {
    title: 'Casilla del scorecard',
    entries: [
      { term: '◇', meaning: 'Corrido del bateador: hasta qué base llegó (relleno verde si anotó; tramo morado si robó esa base)' },
      { term: '•', meaning: 'Base en la que se quedó (naranja si se quedó en base al terminar la entrada)' },
      { term: '¹ ² ³', meaning: 'Carreras impulsadas en el turno' },
      { term: 'SB2·3', meaning: 'Bases que robó en el turno (H = home)' },
      { term: '4-3 DP', meaning: 'Bateó para doble play: su parte de la jugada' },
      { term: '6-4 DP②', meaning: 'Corredor puesto out en el doble play y el número de out' },
      { term: 'PK 1-3', meaning: 'Out por revire (pickoff) y su secuencia' },
      { term: 'PC 7-2', meaning: 'Out en base en pisa y corre: tiro del jardinero al cuadro' },
      { term: '× 6-4', meaning: 'Lo pusieron out en las bases y cómo' },
      { term: 'LOB', meaning: 'Se quedó en base al terminar la entrada' },
      { term: '→ 4ª', meaning: 'Se terminó la entrada con él en turno: abre la siguiente' },
      { term: '▨', meaning: 'Casilla sin usar: la entrada se cerró antes de su turno o el jugador no estaba en el juego' },
      { term: '↳', meaning: 'Cambio: entró en el turno al bat del jugador de arriba' }
    ]
  },
  {
    title: 'Columnas de bateo',
    entries: [...FULL_COLUMNS, ...SCORECARD_COLUMNS.filter(column => ['sb', 'pk', 'gidp', 'avg'].includes(column.key)), { label: 'E', title: 'Errores a la defensiva' }]
      .map(column => ({ term: column.label, meaning: column.key === 'lob' ? 'Dejados en base (en el scorecard: veces que se quedó en base al terminar la entrada)' : column.title }))
  },
  {
    title: 'Marcador',
    entries: [
      { term: 'R', meaning: 'Carreras' },
      { term: 'H', meaning: 'Hits' },
      { term: 'E', meaning: 'Errores' },
      { term: 'LOB', meaning: 'Dejados en base del equipo' },
      { term: 'X', meaning: 'El local no batea la última entrada porque ya va ganando' }
    ]
  }
]

// El glosario en PDF: el servidor lo arma con las mismas secciones que se ven en el diálogo
const exportingGlossary = ref(false)
const exportGlossary = async () => {
  exportingGlossary.value = true
  try {
    await downloadFile('/glossary/pdf', 'glosario_scorecard.pdf', { title: 'Glosario del scorecard', sections: GLOSSARY })
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al generar el PDF del glosario'))
  } finally {
    exportingGlossary.value = false
  }
}

// Sin autocompletado del navegador: Chrome ignora autocomplete="off" y sugiere usuarios / correos guardados (empata los
// campos por name o id, y Vuetify repite ids como input-v-78 en otros formularios). Un valor no estándar evita que los
// clasifique como usuario o correo y un name distinto en cada carga evita que empate con lo guardado
const NO_AUTOFILL = 'ejp-no-autofill'
const NAME_SEED = Date.now().toString(36)
const fieldName = key => `bx-${key}-${NAME_SEED}`

const STATS = ['pa', 'ab', 'r', 'h', 'singles', 'ih', 'doubles', 'triples', 'hr', 'rbi', 'bb', 'ibb', 'hbp', 'sf', 'sh', 'roe', 'so', 'so_swinging', 'so_looking', 'sb', 'pk', 'gidp', 'lob', 'e']

// Formato beisbolero: .288, 1.000
const formatRate = value => {
  const text = value.toFixed(3)
  return text.startsWith('0') ? text.slice(1) : text
}


const mode = ref('score')
const totals = ref({ away: {}, home: {} })
const lineScore = ref({ away: [], home: [] })
const batting = ref({ away: [], home: [] })
const battingSide = ref('away')
const ready = ref(false)
// Scorecard: entradas terminadas a mano (outs en las bases, no se guardan) y turno que se ve en el panel
const closedInnings = ref({ away: [], home: [] })
const turnIndex = ref(null)

const game = computed(() => props.boxscore?.game)

// Descarga del scorecard en PDF (team: away, home o both); usa lo guardado, no lo que se está editando
const notify = useNotificationsStore()
const exportingPdf = ref(false)
const hasSavedLineup = computed(() => (game.value?.batting_lines || []).length > 0)
const hasSavedScore = computed(() => game.value?.away_score != null && game.value?.home_score != null)
// Bateo, Bateo detallado y Scorecard se imprimen por equipo; Score y Line Score son del partido
const modeHasLineup = computed(() => ['batting', 'batting_full', 'scorecard'].includes(mode.value))
const pdfUnavailable = computed(() => {
  if (modeHasLineup.value && !hasSavedLineup.value) return 'Guarda el lineup del partido para generar su PDF'
  if (mode.value !== 'scorecard' && !hasSavedScore.value) return 'Guarda el marcador del partido para generar su PDF'
  return ''
})
// Nombre: [partido]_[visitante]_vs_[local]_[aaaa-mm-dd].pdf; las modalidades distintas del Scorecard llevan su sufijo
const PDF_SUFFIXES = { score: '_score', linescore: '_line_score', batting: '_bateo', batting_full: '_bateo_detallado', scorecard: '' }
const exportBoxscore = async (team) => {
  const slug = text => String(text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '').toLowerCase()
  const filename = `${game.value.game_number}_${slug(game.value.away_team.name)}_vs_${slug(game.value.home_team.name)}_${String(game.value.scheduled_at).substring(0, 10)}${PDF_SUFFIXES[mode.value]}.pdf`
  exportingPdf.value = true
  try {
    await downloadFile(
      `/tournaments/${game.value.tournament_id}/games/${game.value.id}/boxscore/pdf?mode=${mode.value}&team=${team}`,
      filename
    )
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al generar el PDF del box score'))
  } finally {
    exportingPdf.value = false
  }
}
const positions = computed(() => props.boxscore?.positions || [])
// Combo Pos: "—" quita la posición
const positionOptions = computed(() => [{ title: '—', value: null }, ...positions.value.map(position => ({ title: position, value: position }))])
const currentMode = computed(() => MODES.find(item => item.value === mode.value))
const isBatting = computed(() => ['batting', 'batting_full', 'scorecard'].includes(mode.value))
const battingColumns = computed(() => ({ batting_full: FULL_COLUMNS, scorecard: SCORECARD_COLUMNS })[mode.value] || BASIC_COLUMNS)
const showInnings = computed(() => mode.value === 'linescore' || mode.value === 'scorecard')
const innings = computed(() => lineScore.value.away.length)

const teamOf = side => side === 'away' ? game.value.away_team : game.value.home_team
const teamIdOf = side => side === 'away' ? game.value.away_team_id : game.value.home_team_id

// Jugadores como "Apellidos Nombres" (apellido paterno, materno y nombres), ordenados por apellidos
const players = computed(() => (props.boxscore?.players || [])
  .map(player => ({
    id: player.id,
    name: [player.last_name, player.second_last_name, player.first_name].filter(Boolean).join(' ')
  }))
  .sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })))

// Búsqueda del combo de jugador: cada palabra escrita debe aparecer en el nombre, en cualquier orden y sin importar
// acentos ni mayúsculas ("carlos flores" encuentra a "Flores Figueroa Carlos Adrián")
const normalizeText = text => String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const playerFilter = (value, query) => {
  const name = normalizeText(value)
  return normalizeText(query).split(/\s+/).filter(Boolean).every(word => name.includes(word))
}

let uid = 0
const newLine = (data = {}) => ({
  uid: ++uid,
  player_id: data.player_id ?? null,
  // Nombre provisional mientras no se asigna al jugador real ("Bateador 3")
  placeholder: data.placeholder ?? null,
  position: data.position ?? null,
  is_substitute: data.is_substitute ?? false,
  entered_inning: data.entered_inning ?? null,
  // Turnos del scorecard: { inning, result, notation, rbi, scored }
  plays: (data.plate_appearances || []).map(play => ({
    inning: play.inning,
    result: play.result,
    notation: play.notation ?? '',
    // Con home run se impulsa al menos a sí mismo (corrige turnos guardados con 0)
    rbi: play.result === 'HR' ? Math.max(play.rbi ?? 0, 1) : (play.rbi ?? 0),
    bases: play.bases ?? (play.scored ? 4 : 0),
    scored: !!play.scored,
    out_on_base: !!play.out_on_base,
    out_notation: play.out_notation ?? '',
    // PK revire, PC pisa y corre (null = otro out en base)
    out_type: play.out_type ?? null,
    // Renglón del bateador en cuyo turno lo pusieron out (se resuelve al cargar el lineup)
    out_during: null,
    out_during_order: play.out_during_order ?? null,
    // Bases robadas en el turno (2, 3, 4)
    stolen_bases: String(play.stolen_bases ?? '').split(',').map(Number).filter(base => [2, 3, 4].includes(base)),
    // DP del bateador: renglón del corredor que fue el otro out (se resuelve al cargar el lineup)
    dp_runner: null,
    dp_runner_order: play.dp_runner_order ?? null,
    // Turno del corredor out en un DP: su parte de la jugada (6-4)
    dp_notation: play.dp_notation ?? ''
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
    away: { r: current.away_score ?? 0, h: current.away_hits ?? 0, e: current.away_errors ?? 0, lob: current.away_lob ?? 0 },
    home: { r: current.home_score ?? 0, h: current.home_hits ?? 0, e: current.home_errors ?? 0, lob: current.home_lob ?? 0 }
  }
  const saved = current.line_score
  lineScore.value = saved
    ? { away: [...saved.away], home: saved.home.map(value => value ?? '') }
    : { away: Array(9).fill(''), home: Array(9).fill('') }
  batting.value = {
    away: current.batting_lines.filter(line => line.team_id === current.away_team_id).map(newLine),
    home: current.batting_lines.filter(line => line.team_id === current.home_team_id).map(newLine)
  }
  for (const side of SIDES) {
    for (const play of batting.value[side].flatMap(line => line.plays)) {
      play.out_during = play.out_during_order ? batting.value[side][play.out_during_order - 1] || null : null
      play.dp_runner = play.dp_runner_order ? batting.value[side][play.dp_runner_order - 1] || null : null
    }
  }
  if (mode.value === 'scorecard') fillLineup()
  resetHistory()
  battingSide.value = 'away'
  closedInnings.value = { away: [], home: [] }
  turnIndex.value = null
  ready.value = true
}, { immediate: true })

const toInt = value => Number.parseInt(value, 10) || 0

// ---------- LINE SCORE ----------
const inningRuns = side => lineScore.value[side].reduce((sum, value) => sum + toInt(value), 0)

// Al salir de una modalidad que calcula el marcador, lo calculado pasa al marcador editable
watch(mode, (current, previous) => {
  if (!ready.value) return
  if (current === 'scorecard') fillLineup()
  for (const side of SIDES) {
    if (previous === 'linescore') totals.value[side].r = inningRuns(side)
    if (previous === 'scorecard') {
      lineScore.value[side] = lineScore.value[side].map((_, index) => scorecardRuns(side, index) ?? '')
      for (const line of batting.value[side]) Object.assign(line, playStats(line), { lob: lineLob(line) })
      totals.value[side].r = columnTotalFrom(side, 'r')
      totals.value[side].h = columnTotalFrom(side, 'h')
      totals.value[side].lob = teamLob(side)
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
// Solo jugadores del roster del equipo en el torneo (y el que ya tenía el renglón guardado);
// un jugador solo puede aparecer una vez en el partido
const rosterIds = side => new Set(props.boxscore?.rosters?.[teamIdOf(side)] || [])

const playerOptions = (line) => {
  const side = SIDES.find(item => batting.value[item].includes(line)) || battingSide.value
  const allowed = rosterIds(side)
  const used = new Set([...batting.value.away, ...batting.value.home].filter(item => item !== line).map(item => item.player_id))
  return players.value.filter(player => (allowed.has(player.id) || player.id === line.player_id) && !used.has(player.id))
}

const rosterIsEmpty = computed(() => ready.value && rosterIds(battingSide.value).size === 0)

// Cada renglón nuevo lleva un nombre provisional para que se guarde aunque aún no tenga jugador
const addBatter = () => {
  const lines = batting.value[battingSide.value]
  lines.push(newLine({ placeholder: `Bateador ${lines.filter(line => !line.is_substitute).length + 1}` }))
}

// Como en The Perfect Boxscore, el scorecard abre con los 9 turnos del lineup. Los que faltan se agregan vacíos
// (sin jugador ni nombre provisional): se ven como "Bateador N" y solo se guardan si se usan
const LINEUP_SIZE = 9
const fillLineup = () => {
  for (const side of SIDES) {
    const lines = batting.value[side]
    const starters = lines.filter(line => !line.is_substitute).length
    for (let index = starters; index < LINEUP_SIZE; index++) lines.push(newLine())
  }
}

// Renglón agregado por default que no se ha usado: sin jugador, sin nombre provisional, sin turnos ni estadísticas
const isBlankLine = line => !line.player_id && !line.placeholder
  && !line.plays.some(play => play.result) && STATS.every(key => !toInt(line[key]))

// ---------- AGREGAR DEL ROSTER ----------
const ROSTER_HEADERS = [
  { title: 'Jugador', key: 'name' },
  { title: '', key: 'actions', align: 'end', sortable: false }
]

const rosterDialog = ref(false)
const rosterSearch = ref('')

const openRosterDialog = () => {
  rosterSearch.value = ''
  rosterDialog.value = true
}

// Jugadores del roster del equipo que aún no están en ningún lineup del partido
const rosterRows = computed(() => {
  const allowed = rosterIds(battingSide.value)
  const used = new Set([...batting.value.away, ...batting.value.home].map(line => line.player_id))
  return players.value.filter(player => allowed.has(player.id) && !used.has(player.id))
})

// El jugador ocupa el primer titular provisional (conserva lo capturado); si no hay, se agrega al final
const addFromRoster = (player) => {
  const lines = batting.value[battingSide.value]
  const provisional = lines.find(line => !line.player_id && !line.is_substitute)
  if (provisional) provisional.player_id = player.id
  else lines.push(newLine({ player_id: player.id }))
}

const MAX_STARTERS = 9

// Titulares de cada equipo que se guardarían (los renglones sin jugador ni nombre no se guardan)
const startersCount = side => batting.value[side].filter(line => !line.is_substitute && !isBlankLine(line)).length

// Renglones que se guardan: hasta el último que se usó (los turnos vacíos del final no se guardan). Un turno vacío
// intermedio o uno usado sin jugador se guarda con su nombre provisional "Bateador N" para conservar el orden al bat
const linesToSave = (side) => {
  const lines = batting.value[side]
  let last = lines.length - 1
  while (last >= 0 && isBlankLine(lines[last])) last--
  return lines.slice(0, last + 1)
}
const savedPlaceholder = (side, line) => {
  if (line.player_id) return null
  if (line.placeholder) return line.placeholder
  const index = batting.value[side].indexOf(line)
  return line.is_substitute ? `Cambio ${index + 1}` : `Bateador ${batting.value[side].slice(0, index + 1).filter(item => !item.is_substitute).length}`
}

const tooManyStarters = computed(() => isBatting.value && SIDES.some(side => startersCount(side) > MAX_STARTERS))

// Bateadores con nombre provisional o ya usados sin jugador asignado (los 9 turnos vacíos por default no cuentan)
const provisionalCount = computed(() => batting.value[battingSide.value].filter(line => !line.player_id && !isBlankLine(line)).length)

// Turno al bat del renglón: los cambios comparten el turno del titular que reemplazan
const slotNumber = index => batting.value[battingSide.value].slice(0, index + 1).filter(line => !line.is_substitute).length

// El cambio entra debajo del titular y de los cambios que ya tenga ese turno
const addSubstitute = (index) => {
  const lines = batting.value[battingSide.value]
  let position = index + 1
  while (position < lines.length && lines[position].is_substitute) position++
  lines.splice(position, 0, newLine({ is_substitute: true, placeholder: `Cambio ${lines.filter(line => line.is_substitute).length + 1}` }))
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
  if (mode.value === 'scorecard') {
    if (key === 'lob') return lineLob(line)
    const stats = playStats(line)
    if (key === 'avg') return formatRate(stats.ab ? stats.h / stats.ab : 0)
    return stats[key] ?? toInt(line[key])
  }
  if (mode.value === 'batting_full' && (key === 'h' || key === 'so')) return derivedValue(line, key)
  return toInt(line[key])
}

// AVG del equipo: H / AB de los totales
const columnTotal = (side, key) => {
  if (key === 'avg') {
    const ab = columnTotal(side, 'ab')
    return formatRate(ab ? columnTotal(side, 'h') / ab : 0)
  }
  return batting.value[side].reduce((sum, line) => sum + statValue(line, key), 0)
}

// Total de una columna con lo que ya trae cada renglón (tras copiar lo calculado)
const columnTotalFrom = (side, key) => batting.value[side].reduce((sum, line) => sum + toInt(line[key]), 0)

// ---------- SCORECARD ----------
const cellPlays = (line, inning) => line.plays.filter(play => play.inning === inning)

const isHit = play => ['1B', 'IH', '2B', '3B', 'HR'].includes(play.result)

const reachesBase = play => play.result in START_BASE

// Llegar a home es anotar; solo un corredor que se quedó en las bases puede ser puesto out después
const setBases = (play, bases) => {
  play.bases = bases
  play.scored = bases === 4
  if (bases < 1 || bases > 3) setOutOnBase(play, false)
}

const setOutOnBase = (play, value) => {
  play.out_on_base = value
  if (!value) {
    // Ya no fue out: tampoco por revire ni en pisa y corre
    play.out_notation = ''
    play.out_type = null
    play.out_during = null
  }
}

// PK: out en base por revire (marca también "Out en base")
const togglePickoff = (play) => {
  if (play.out_on_base && play.out_type === 'PK') {
    play.out_type = null
    play.out_notation = ''
    return
  }
  play.out_on_base = true
  play.out_type = 'PK'
  play.out_notation = ''
}

// Notación del out en base: PC (pisa y corre) u otra; con la misma se desmarca
const setOutNotation = (play, notation, type = null) => {
  const same = play.out_notation === notation && play.out_type === type
  play.out_notation = same ? '' : notation
  play.out_type = same ? null : type
}

// Robo de base: el robo lo deja al menos en esa base (SB H = anotó)
const toggleSteal = (play, base) => {
  const steals = new Set(play.stolen_bases)
  if (steals.has(base)) steals.delete(base)
  else {
    steals.add(base)
    if (play.bases < base) setBases(play, base)
  }
  play.stolen_bases = [...steals].sort()
}

const runnerOutTitle = (play) => {
  const during = play.out_during ? ` en el turno de ${playerName(play.out_during)}` : ''
  if (play.out_type === 'PK') return `Out por revire${play.out_notation ? ` (PK ${play.out_notation})` : ''}${during}`
  if (play.out_type === 'PC') return `Out en pisa y corre${play.out_notation ? ` (${play.out_notation})` : ''}${during}`
  return `Out en base${play.out_notation ? ` (${play.out_notation})` : ''}${during}`
}

// Texto del out en base en la casilla: PK 1-3, PC 7-2 u otra anotación
const runnerOutLabel = (play) => {
  if (play.out_type === 'PK') return play.out_notation ? `PK ${play.out_notation}` : 'PK'
  if (play.out_type === 'PC') return play.out_notation ? `PC ${play.out_notation}` : 'PC'
  return play.out_notation || 'Out'
}

const basesTitle = (play) => {
  if (play.bases === 4) return 'Anotó carrera'
  const base = BASES.find(item => item.value === play.bases)?.title || ''
  if (dpOutOf(play)) return `${base}; después fue out en doble play`
  return play.out_on_base ? `${base}; después lo pusieron out` : base
}

// ---------- Reglas de The Perfect Boxscore ----------
// Corredores puestos out en un doble play (turno del corredor => { out: número de out, line: bateador del DP })
const dpOuts = computed(() => {
  const result = new Map()
  for (const side of SIDES) {
    for (const [play, info] of turnSequence(side).dpOuts) result.set(play, info)
  }
  return result
})

const dpOutOf = play => dpOuts.value.get(play)

// Sigue en base (1ra a 3ra) sin anotar ni ser out (en base o en un doble play)
const onBase = play => play.bases >= 1 && play.bases <= 3 && !play.out_on_base && !dpOutOf(play)

// "6-4 DP②" en la casilla del corredor puesto out en un doble play
const dpLabel = (play) => {
  const info = dpOutOf(play)
  if (!info) return ''
  const dp = `DP${CIRCLED[info.out] || ''}`
  return play.dp_notation ? `${play.dp_notation} ${dp}` : dp
}

// SB2·3 en la casilla
const stealsLabel = play => play.stolen_bases?.length
  ? `SB${STEALS.filter(steal => play.stolen_bases.includes(steal.value)).map(steal => steal.short).join('·')}`
  : ''

// Estilo de la casilla como en The Perfect Boxscore: hit, anotó, out, en base, out en base
const playStyle = (play) => {
  if (dpOutOf(play) || play.out_on_base) return 'out-on-base'
  if (play.bases === 4) return 'scored'
  if (play.bases >= 1 && play.bases <= 3) return isHit(play) ? 'hit' : 'on-base'
  return 'out'
}

// Vértices del diamante en orden de corrido: home, primera, segunda, tercera, home
const DIAMOND_POINTS = [[10, 18], [18, 10], [10, 2], [2, 10], [10, 18]]
const diamondSegments = play => Array.from({ length: Math.min(play.bases, 4) }, (_, index) => ({
  from: DIAMOND_POINTS[index],
  to: DIAMOND_POINTS[index + 1],
  stolen: play.stolen_bases?.includes(index + 1)
}))
const diamondBase = play => DIAMOND_POINTS[play.bases]

// Bateadores que pudieron ser el otro out del DP del turno del panel: turnos anteriores de la entrada que seguían en base
const dpCandidates = (turnIdx) => {
  const turns = sequence.value.turns
  const turn = turns[turnIdx]
  if (!turn) return []
  const own = turn.play.result === 'DP' ? turn.play.dp_runner : null
  const found = new Map()
  for (const candidate of turns.slice(0, turnIdx).filter(item => item.inning === turn.inning)) {
    const info = dpOutOf(candidate.play)
    const mine = info && info.line === turn.line && candidate.line === own
    if (onBase(candidate.play) || mine) found.set(candidate.line, candidate)
    else found.delete(candidate.line)
  }
  return [...found.values()]
}

// Turno del bateador que bateó el DP en el que el corredor del panel pudo ser el otro out
// (el primero de la entrada que no tenga ya a otro corredor)
const dpBatterFor = (turnIdx) => {
  const turns = sequence.value.turns
  const runner = turns[turnIdx]
  if (!runner) return -1
  for (let index = turnIdx + 1; index < turns.length && turns[index].inning === runner.inning; index++) {
    const play = turns[index].play
    if (play.result === 'DP' && (!play.dp_runner || play.dp_runner === runner.line)
      && dpCandidates(index).some(candidate => candidate.line === runner.line)) return index
  }
  return -1
}

// Botón DP en el turno del corredor: lo enlaza (o desenlaza) como el otro out del DP del bateador posterior
const toggleRunnerDp = () => {
  const runner = panelTurn.value
  const info = dpOutOf(runner.play)
  if (info) {
    const batter = sequence.value.turns.find(turn => turn.line === info.line && turn.inning === runner.inning && turn.play.dp_runner === runner.line)
    if (batter) batter.play.dp_runner = null
    return
  }
  const index = dpBatterFor(turnIndex.value)
  if (index !== -1) sequence.value.turns[index].play.dp_runner = runner.line
}

// Base por bolas por la cuenta: el bateador llega a 1ra y avanzan los corredores forzados
// (1ra→2da; si también hay en 2da, 2da→3ra; con casa llena el de 3ra anota y el bateador lleva 1 RBI)
const applyWalk = (play, inning, turnsBefore) => {
  const runners = new Map()
  for (const turn of turnsBefore.filter(item => item.inning === inning)) {
    if (onBase(turn.play)) runners.set(turn.play.bases, turn.play)
  }
  const forced = []
  for (const base of [1, 2, 3]) {
    if (!runners.has(base)) break
    forced.push(base)
  }
  // Se mueve primero el corredor más adelantado
  for (const base of [...forced].reverse()) setBases(runners.get(base), base + 1)
  if (forced.includes(3)) play.rbi = Math.max(toInt(play.rbi), 1)
}

const resultLabel = result => RESULTS.find(item => item.value === result)?.label || result || '?'

// En los hits la anotación es la dirección (1B-8); en las demás jugadas la anotación ya dice qué pasó (6-3, F8).
// El doble play lleva su secuencia y la marca DP (6-5-3 DP)
const playLabel = (play) => {
  if (!play.notation) return resultLabel(play.result)
  if (isHit(play)) return `${resultLabel(play.result)}-${play.notation}`
  if (play.result === 'KDS' || play.result === 'KDC') return `${resultLabel(play.result)}${play.notation}`
  if (play.result === 'DP') return `${play.notation} DP`
  return play.notation
}

const playerName = line => players.value.find(player => player.id === line.player_id)?.name
  || line.placeholder
  || savedPlaceholder(SIDES.find(side => batting.value[side].includes(line)) || battingSide.value, line)

// Bateo de un renglón a partir de sus turnos (sin E, que es defensivo), con las reglas de The Perfect Boxscore:
// SB bases robadas, PK outs por revire, DP (gidp) bateó para doble play
const playStats = (line) => {
  const stats = Object.fromEntries(STATS.filter(key => key !== 'e' && key !== 'lob').map(key => [key, 0]))
  for (const play of line.plays.filter(item => item.result)) {
    stats.pa++
    if (!NOT_AT_BAT.includes(play.result)) stats.ab++
    for (const key of RESULT_STATS[play.result] || []) stats[key]++
    stats.rbi += toInt(play.rbi)
    if (play.scored || play.result === 'HR') stats.r++
    stats.sb += play.stolen_bases?.length || 0
    if (play.out_on_base && play.out_type === 'PK') stats.pk++
  }
  return stats
}

// Carreras del equipo en una entrada; null = X (el local no batea la última entrada)
const scorecardRuns = (side, index) => {
  const plays = batting.value[side].flatMap(line => line.plays).filter(play => play.result && play.inning === index + 1)
  if (side === 'home' && index === innings.value - 1 && plays.length === 0) return null
  return plays.filter(play => play.scored || play.result === 'HR').length
}

// Entrada que está en juego para cada equipo (la del próximo turno por capturar); 0 si no tiene lineup
const currentInning = computed(() => Object.fromEntries(SIDES.map(side => [side, turnSequence(side).next?.inning ?? 0])))

const INNING_STATES = {
  played: 'Entrada jugada',
  current: 'Entrada en juego',
  pending: 'Entrada sin jugar'
}

// played: ya se jugó (o tiene turnos); current: se está jugando; pending: aún no se juega
const inningState = (side, inning) => {
  const current = currentInning.value[side]
  if (inning === current) return 'current'
  if (inning < current) return 'played'
  const hasPlays = batting.value[side].some(line => line.plays.some(play => play.result && play.inning === inning))
  return hasPlays ? 'played' : 'pending'
}

// Carreras de la entrada para mostrar: "–" si no se ha jugado; X si el local no batea la última porque no hizo falta
const inningRunsLabel = (side, inning) => {
  if (inningState(side, inning) !== 'pending') return scorecardRuns(side, inning - 1) ?? 0
  const awayFinished = currentInning.value.away > innings.value
  return side === 'home' && inning === innings.value && awayFinished ? 'X' : '–'
}

// Dejados en base: corredores que llegaron a 1ra, 2da o 3ra y no anotaron ni fueron puestos out (en base o en DP).
// Solo cuentan las entradas que ya terminaron (la que está en juego aún puede cambiar)
const inningLob = (side, inning) => batting.value[side]
  .flatMap(line => line.plays)
  .filter(play => play.result && play.inning === inning && onBase(play))
  .length

// Corredor que se quedó en base al terminar su entrada
const isStranded = play => onBase(play)
  && inningState(SIDES.find(side => batting.value[side].some(line => line.plays.includes(play))), play.inning) === 'played'

// Bateador que se quedó en turno al cerrar cada entrada (abre la siguiente): línea => entradas cerradas
const nextUpCells = computed(() => {
  const result = new Map()
  for (const side of SIDES) {
    const slots = battingSlots(side)
    for (const turn of turnSequence(side).turns.filter(item => item.endsInning && item.inning < innings.value)) {
      const line = batterFor(slots[(turn.slot + 1) % slots.length], turn.inning)
      if (!result.has(line)) result.set(line, new Set())
      result.get(line).add(turn.inning)
    }
  }
  return result
})

// Entradas que ya se cerraron con tres outs, por equipo
const closedInningsBySide = computed(() => Object.fromEntries(SIDES.map(side => [
  side,
  new Set(turnSequence(side).turns.filter(turn => turn.endsInning).map(turn => turn.inning))
])))

// En una entrada cerrada, la casilla de quien no bateó (ni se quedó en turno) ya no se usa
const isUnusedCell = (side, line, inning) => closedInningsBySide.value[side].has(inning)
  && cellPlays(line, inning).length === 0
  && !nextUpCells.value.get(line)?.has(inning)

// LOB del jugador (como en The Perfect Boxscore): veces que él se quedó en base al terminar la entrada
const lineLob = line => line.plays.filter(play => play.result && isStranded(play)).length

// En la entrada, el jugador no estaba en el juego en ese turno (aún no entraba de cambio, o ya lo cambiaron)
const isInactiveCell = (side, line, inning) => {
  const slot = battingSlots(side).find(item => item.includes(line))
  return !!slot && slot.length > 1 && batterFor(slot, inning) !== line && cellPlays(line, inning).length === 0
}

const teamLob = (side) => {
  let lob = 0
  for (let inning = 1; inning <= innings.value; inning++) {
    if (inningState(side, inning) === 'played') lob += inningLob(side, inning)
  }
  return lob
}

const lastInningHasPlays = computed(() => mode.value === 'scorecard'
  && [...batting.value.away, ...batting.value.home].some(line => line.plays.some(play => play.inning === innings.value)))

// Turnos al bat del lineup: cada uno es el titular seguido de sus cambios
const battingSlots = (side) => {
  const slots = []
  for (const line of batting.value[side]) {
    if (line.is_substitute && slots.length) slots[slots.length - 1].push(line)
    else slots.push([line])
  }
  return slots
}

// Quién batea en un turno en cierta entrada: el último cambio que ya había entrado, o el titular
const batterFor = (slot, inning) => slot.reduce((batter, line) => (
  line.is_substitute && toInt(line.entered_inning) && toInt(line.entered_inning) <= inning ? line : batter
), slot[0])

/**
 * Secuencia de turnos de un equipo a partir de lo capturado: el orden al bat corre de forma continua,
 * con tres outs pasa a la siguiente entrada con el bateador que sigue, y al final queda el próximo
 * turno por capturar (next).
 */
const turnSequence = (side) => {
  const slots = battingSlots(side)
  const turns = []
  // Corredores puestos out en un doble play: turno del corredor => { out: número de out (1-3), line: bateador del DP }
  const dpOuts = new Map()
  if (slots.length === 0) return { turns, next: null, dpOuts }

  const used = new Set()
  const countedRunnerOuts = new Set()
  const pending = () => batting.value[side].flatMap(line => line.plays).filter(play => play.result && !used.has(play))
  let slot = 0
  let inning = 1
  let outs = 0

  for (let guard = 0; guard < 1000; guard++) {
    const line = batterFor(slots[slot], inning)
    const play = line.plays.find(item => item.result && item.inning === inning && !used.has(item))

    if (play) {
      used.add(play)
      turns.push({ line, inning, slot, play, outsBefore: outs })

      // Reglas de The Perfect Boxscore: en un DP, si se indicó qué corredor fue el otro out y seguía en base,
      // su out se cuenta antes que el del bateador
      if (play.result === 'DP' && play.dp_runner) {
        const runner = turns.slice(0, -1).reverse().find(turn => turn.inning === inning && turn.line === play.dp_runner)
        if (runner && runner.play.bases >= 1 && runner.play.bases <= 3 && !runner.play.out_on_base && !dpOuts.has(runner.play)) {
          outs++
          dpOuts.set(runner.play, { out: Math.min(outs, 3), line })
        }
      }

      // Outs de la jugada del bateador + corredores puestos out en las bases (el propio bateador si lo pusieron
      // out después sin indicar en qué turno, o los que indican que fue en este turno)
      outs += (OUTS[play.result] || 0) + runnerOutsIn(side, line, inning, play, used, countedRunnerOuts)

      slot = (slot + 1) % slots.length
      if (outs >= 3) {
        turns[turns.length - 1].endsInning = true
        inning++
        outs = 0
      }
      continue
    }

    // La entrada se terminó a mano, o ya hay turnos capturados en entradas posteriores
    const later = pending().filter(item => item.inning > inning)
    if (closedInnings.value[side].includes(inning) || later.length) {
      inning = later.length && !closedInnings.value[side].includes(inning) ? Math.min(...later.map(item => item.inning)) : inning + 1
      outs = 0
      // Si quien toca no tiene turno en esa entrada, sigue quien tenga el primero capturado
      if (!batterFor(slots[slot], inning).plays.some(item => item.result && item.inning === inning && !used.has(item))) {
        const found = slots.findIndex(candidate => batterFor(candidate, inning).plays.some(item => item.result && item.inning === inning && !used.has(item)))
        if (found !== -1) slot = found
      }
      continue
    }

    return { turns, next: { line, inning, slot, play: null, outsBefore: outs }, dpOuts }
  }

  return { turns, next: null, dpOuts }
}

const sequence = computed(() => turnSequence(battingSide.value))

// Corredores puestos out durante el turno de un bateador: los que lo indican con out_during,
// y el propio bateador si lo pusieron out después sin indicar en qué turno (se cuenta en el suyo)
const runnerOutsIn = (side, line, inning, play, used, counted) => {
  let outs = 0
  for (const runner of batting.value[side].flatMap(item => item.plays)) {
    if (!runner.out_on_base || runner.inning !== inning || !used.has(runner) || counted.has(runner)) continue
    if (runner.out_during === line || (runner === play && !runner.out_during)) {
      counted.add(runner)
      outs++
    }
  }
  return outs
}

// Corredores en base antes del turno del panel en la misma entrada: los que pudo poner out un DP o FC
const runnerCandidates = computed(() => {
  if (turnIndex.value === null || !panelTurn.value) return []
  const { line, inning } = panelTurn.value
  return sequence.value.turns.slice(0, turnIndex.value)
    .filter(turn => turn.inning === inning && turn.play.bases >= 1 && turn.play.bases <= 3 && !dpOutOf(turn.play))
    .filter(turn => !turn.play.out_on_base || !turn.play.out_during || turn.play.out_during === line)
})

const isRunnerOutHere = runnerPlay => runnerPlay.out_on_base && runnerPlay.out_during === panelTurn.value.line

// Marca al corredor como out en esta jugada; su anotación se toma del inicio de la del DP (6-5-3 -> 6-5)
const toggleRunnerOut = (runnerPlay) => {
  if (isRunnerOutHere(runnerPlay)) {
    setOutOnBase(runnerPlay, false)
    return
  }
  setOutOnBase(runnerPlay, true)
  runnerPlay.out_during = panelTurn.value.line
}

// Bateadores que batearon (o batean ahora) después del turno del panel en la misma entrada
const outDuringOptions = computed(() => {
  if (turnIndex.value === null || !panelTurn.value) return []
  const { turns, next } = sequence.value
  const inning = panelTurn.value.inning
  const later = turns.slice(turnIndex.value + 1).filter(turn => turn.inning === inning).map(turn => turn.line)
  if (next && next.inning === inning) later.push(next.line)
  return [...new Set(later)].filter(line => line !== panelTurn.value.line)
})

// Turno que se ve en el panel: uno ya capturado (turnIndex) o el siguiente por capturar (null)
const draftPlay = ref(null)
const newPlay = () => ({
  inning: null, result: null, notation: '', rbi: 0, bases: 0, scored: false, out_on_base: false, out_notation: '', out_type: null, out_during: null,
  stolen_bases: [], dp_runner: null, dp_notation: ''
})

const panelTurn = computed(() => turnIndex.value === null ? sequence.value.next : sequence.value.turns[turnIndex.value])
const panelPlay = computed(() => panelTurn.value?.play || draftPlay.value)

// Si cambia el turno por capturar se empieza una jugada nueva
watch(() => sequence.value.next && [sequence.value.next.line, sequence.value.next.inning], () => {
  draftPlay.value = newPlay()
}, { immediate: true })

const canGoBack = computed(() => turnIndex.value === null ? sequence.value.turns.length > 0 : turnIndex.value > 0)

const goBack = () => {
  turnIndex.value = turnIndex.value === null ? sequence.value.turns.length - 1 : turnIndex.value - 1
}

const goNext = () => {
  if (turnIndex.value === null) return
  turnIndex.value = turnIndex.value + 1 < sequence.value.turns.length ? turnIndex.value + 1 : null
}

// ---------- BARRA DEL SCORECARD (como en The Perfect Boxscore) ----------
// Renglón seleccionado: sobre él actúan subir, bajar, sustituto y quitar. Abrir un turno selecciona a su bateador
const selectedLine = ref(null)
watch(() => panelTurn.value?.line, (line) => {
  if (line) selectedLine.value = line
})
watch(battingSide, () => {
  selectedLine.value = null
})

const selectedPosition = () => {
  const line = selectedLine.value
  if (!line) return null
  const slots = battingSlots(battingSide.value)
  const slotIdx = slots.findIndex(slot => slot.includes(line))
  return slotIdx === -1 ? null : { slots, slotIdx, subIdx: slots[slotIdx].indexOf(line) }
}

// Subir / bajar: el titular se mueve con sus cambios entre los turnos del orden al bat; un cambio, dentro de su turno
const canMoveSelected = (delta) => {
  const pos = selectedPosition()
  if (!pos) return false
  if (pos.subIdx === 0) return pos.slotIdx + delta >= 0 && pos.slotIdx + delta < pos.slots.length
  return pos.subIdx + delta >= 1 && pos.subIdx + delta < pos.slots[pos.slotIdx].length
}

const moveSelected = (delta) => {
  if (!canMoveSelected(delta)) return
  const { slots, slotIdx, subIdx } = selectedPosition()
  const seq = subIdx === 0 ? slots : slots[slotIdx]
  const i = subIdx === 0 ? slotIdx : subIdx
  ;[seq[i], seq[i + delta]] = [seq[i + delta], seq[i]]
  batting.value[battingSide.value].splice(0, Infinity, ...slots.flat())
}

// Sustituto (entra / sale): se agrega en el mismo turno, justo debajo del registro seleccionado
const addSubToSelected = () => {
  const line = selectedLine.value
  if (!line) return
  const lines = batting.value[battingSide.value]
  const sub = newLine({ is_substitute: true, placeholder: `Cambio ${lines.filter(item => item.is_substitute).length + 1}` })
  lines.splice(lines.indexOf(line) + 1, 0, sub)
  selectedLine.value = sub
}

// Marcar como cambio / titular (EJPMX): para acomodar un bateador agregado de más; el primero siempre es titular
const canToggleSubstitute = computed(() => !!selectedLine.value && batting.value[battingSide.value].indexOf(selectedLine.value) > 0)
const toggleSubstitute = () => {
  if (canToggleSubstitute.value) selectedLine.value.is_substitute = !selectedLine.value.is_substitute
}

// Quitar: como en The Perfect Boxscore los titulares no se quitan (se cambia al jugador en su renglón); solo los cambios
// y, si el equipo tiene más de 9 titulares, el titular sobrante que no tenga cambios
const canRemoveSelected = computed(() => {
  const pos = selectedPosition()
  if (!pos) return false
  if (pos.subIdx > 0) return true
  return pos.slots.length > LINEUP_SIZE && pos.slots[pos.slotIdx].length === 1
})
const removeTitle = computed(() => (selectedPosition()?.subIdx === 0 && !canRemoveSelected.value)
  ? 'Los titulares no se quitan: cambia al jugador en su renglón o agrega un sustituto'
  : 'Quitar el registro seleccionado')
const removeSelected = () => {
  if (!canRemoveSelected.value) return
  const { slots, slotIdx, subIdx } = selectedPosition()
  const lines = batting.value[battingSide.value]
  const line = selectedLine.value
  lines.splice(lines.indexOf(line), 1)
  // Queda seleccionado el registro de arriba en el mismo turno (o el turno anterior)
  selectedLine.value = subIdx > 0 ? slots[slotIdx][subIdx - 1] : (slots[slotIdx - 1]?.[0] || null)
  if (panelTurn.value?.line === line) turnIndex.value = null
}

// Limpiar: deja vacía la jugada del turno que se ve en el panel (se puede deshacer); el turno queda por capturar
const canClear = computed(() => turnIndex.value !== null && !!panelTurn.value?.play)
const clearPlay = () => {
  if (!canClear.value) return
  const { line, play } = panelTurn.value
  line.plays.splice(line.plays.indexOf(play), 1)
  turnIndex.value = null
}

// Vista compacta: solo la columna de la entrada en juego (sin Pos ni Game Stats), con ◀ ▶ para ver las ya jugadas
const slim = ref(false)
const viewInning = ref(1)
const slimActive = computed(() => slim.value && mode.value === 'scorecard')
const lastInning = computed(() => {
  const played = batting.value[battingSide.value].flatMap(line => line.plays).filter(play => play.result).map(play => play.inning)
  return Math.min(Math.max(panelTurn.value?.inning || 1, ...played), innings.value)
})
const visibleInnings = computed(() => slimActive.value
  ? [Math.min(Math.max(viewInning.value, 1), innings.value)]
  : Array.from({ length: innings.value }, (_, index) => index + 1))
const toggleSlim = () => {
  slim.value = !slim.value
  viewInning.value = Math.min(panelTurn.value?.inning || lastInning.value, innings.value)
}
// La vista compacta sigue a la entrada en juego
watch(() => panelTurn.value?.inning, (inning) => {
  if (inning && inning <= innings.value) viewInning.value = inning
})

// Deshacer / rehacer: fotos de las jugadas de ambos equipos antes de cada cambio, con el turno en que se hizo.
// Los cambios seguidos en el mismo turno (p. ej. al escribir una anotación) se juntan en un solo paso
const UNDO_LIMIT = 200
const MERGE_MS = 800
const undoStack = ref([])
const redoStack = ref([])
let playsSnapshot = ''
let lastChange = { at: 0, cell: null }

const currentCell = () => panelTurn.value?.play ? { uid: panelTurn.value.line.uid, inning: panelTurn.value.inning } : null
// Solo las jugadas (como en The Perfect Boxscore): mover o agregar renglones no es un paso de deshacer
const serializePlays = () => JSON.stringify(SIDES.map(side => batting.value[side]
  .filter(line => line.plays.some(play => play.result))
  .map(line => [
    line.uid,
    line.plays.filter(play => play.result).map(play => ({ ...play, out_during: play.out_during?.uid ?? null, dp_runner: play.dp_runner?.uid ?? null }))
  ])
  .sort((a, b) => a[0] - b[0])))

const restorePlays = (snapshot, cell) => {
  const saved = JSON.parse(snapshot)
  SIDES.forEach((side, index) => {
    const byUid = new Map(batting.value[side].map(line => [line.uid, line]))
    const plays = new Map(saved[index])
    for (const line of batting.value[side]) {
      line.plays = (plays.get(line.uid) || []).map(play => ({
        ...play,
        out_during: byUid.get(play.out_during) || null,
        dp_runner: byUid.get(play.dp_runner) || null
      }))
    }
  })
  playsSnapshot = serializePlays()
  lastChange = { at: 0, cell: null }
  // Regresa al turno donde se hizo el cambio
  const index = cell ? sequence.value.turns.findIndex(turn => turn.line.uid === cell.uid && turn.inning === cell.inning) : -1
  turnIndex.value = index === -1 ? null : index
}

watch(serializePlays, (snapshot) => {
  if (snapshot === playsSnapshot) return
  const cell = currentCell()
  const now = Date.now()
  const merge = now - lastChange.at < MERGE_MS && JSON.stringify(cell) === JSON.stringify(lastChange.cell) && undoStack.value.length
  if (!merge) {
    undoStack.value.push({ snapshot: playsSnapshot, cell })
    if (undoStack.value.length > UNDO_LIMIT) undoStack.value.shift()
  }
  redoStack.value = []
  playsSnapshot = snapshot
  lastChange = { at: now, cell }
})

const undo = () => {
  const step = undoStack.value.pop()
  if (!step) return
  redoStack.value.push({ snapshot: playsSnapshot, cell: step.cell })
  restorePlays(step.snapshot, step.cell)
}

const redo = () => {
  const step = redoStack.value.pop()
  if (!step) return
  undoStack.value.push({ snapshot: playsSnapshot, cell: step.cell })
  restorePlays(step.snapshot, step.cell)
}

// Al cargar otro partido se empieza sin historial
const resetHistory = () => {
  undoStack.value = []
  redoStack.value = []
  playsSnapshot = serializePlays()
  lastChange = { at: 0, cell: null }
}

// Las impulsadas se marcan y desmarcan con el mismo chip (sin selección son 0);
// con home run no se pueden quitar porque se impulsa al menos a sí mismo
// RBI 0-4 como en The Perfect Boxscore (con home run al menos 1)
const setRbi = (play, rbi) => {
  play.rbi = play.result === 'HR' ? Math.max(rbi, 1) : rbi
}

// Botones de posición / trayectoria de TPB; se ven deshabilitados hasta elegir la jugada
const NOTATION_BUTTONS = ['1', '2', '3', '4', '5', '6', '7', '78', '8', '89', '9']

// La columna de captura tiene un turno que mostrar (mismas condiciones que el encabezado del turno)
const showPlayColumn = computed(() => batting.value[battingSide.value].length > 0
  && substitutesWithoutEntry.value.length === 0
  && !!panelTurn.value
  && panelTurn.value.inning <= innings.value)

const toggleRbi = (play, rbi) => {
  if (toInt(play.rbi) !== rbi) play.rbi = rbi
  else if (play.result !== 'HR') play.rbi = 0
}

// Al elegir la jugada del turno por capturar, el turno queda registrado en la casilla del bateador
const chooseResult = (result) => {
  const play = panelPlay.value
  // Al cambiar la jugada (como set_result de The Perfect Boxscore) se limpia la anotación, el bateador queda en
  // la base a la que llega con ella, se quitan el out en base, el revire, el pisa y corre y el corredor del DP;
  // si el bateador fue out no corrió las bases (sin robos)
  if (play.result !== result) {
    play.notation = ''
    setBases(play, START_BASE[result] || 0)
    setOutOnBase(play, false)
    play.dp_runner = null
    if (result in OUTS) play.stolen_bases = []
  }
  play.result = result
  // Con home run se impulsa al menos a sí mismo
  if (result === 'HR') play.rbi = Math.max(toInt(play.rbi), 1)

  if (turnIndex.value === null) {
    const { line, inning } = sequence.value.next
    play.inning = inning
    line.plays.push(play)
    turnIndex.value = sequence.value.turns.findIndex(turn => turn.play === play)
  }

  // DP con un solo corredor en base: él fue el otro out
  if (result === 'DP' && !play.dp_runner) {
    const candidates = dpCandidates(turnIndex.value)
    if (candidates.length === 1) play.dp_runner = candidates[0].line
  }
}

// DP: corredor que fue el otro out (con el mismo se desmarca)
const setDpRunner = (line) => {
  panelPlay.value.dp_runner = panelPlay.value.dp_runner === line ? null : line
}

// Candidatos del DP del turno del panel, con la base en que estaban: "1ra · Nombre"
const panelDpCandidates = computed(() => turnIndex.value === null || panelPlay.value?.result !== 'DP' ? [] : dpCandidates(turnIndex.value))
const panelDpAvailable = computed(() => turnIndex.value !== null && dpBatterFor(turnIndex.value) !== -1)

// Estadísticas del bateador del turno (la "fila del turno" de The Perfect Boxscore)
const panelLineStats = computed(() => panelTurn.value ? SCORECARD_COLUMNS.map(column => ({ ...column, value: statValue(panelTurn.value.line, column.key) })) : [])

// ---------- CUENTA (bolas y strikes) ----------
// 4 bolas = base por bolas, 3 strikes = ponche; se aplican tras una espera para poder corregir la cuenta
const MAX_BALLS = 4
const MAX_STRIKES = 3
const COUNT_SECONDS = 5
const count = ref({ balls: 0, strikes: 0 })
const countPending = ref(null)
const countRemaining = ref(0)
let countTimer = null

const stopCount = () => {
  clearInterval(countTimer)
  countTimer = null
  countPending.value = null
}

const resetCount = () => {
  stopCount()
  count.value = { balls: 0, strikes: 0 }
}

// Al presionar el círculo N la cuenta queda en N; si ya era el último encendido, se apaga (para corregir)
const setCount = (kind, value) => {
  count.value[kind] = count.value[kind] === value ? value - 1 : value
  const result = count.value.balls >= MAX_BALLS ? 'BB' : count.value.strikes >= MAX_STRIKES ? 'KS' : null
  if (result === countPending.value) return
  stopCount()
  if (!result) return
  countPending.value = result
  countRemaining.value = COUNT_SECONDS
  countTimer = setInterval(() => {
    countRemaining.value--
    if (countRemaining.value > 0) return
    const pendingResult = countPending.value
    resetCount()
    applyCount(pendingResult)
  }, 1000)
}

// Se completó la cuenta del turno por capturar: BB (con los corredores forzados) o ponche, y pasa al siguiente
const applyCount = (result) => {
  if (turnIndex.value !== null || !sequence.value.next || panelPlay.value.result) return
  const turnsBefore = sequence.value.turns
  const { inning } = sequence.value.next
  const play = panelPlay.value
  chooseResult(result)
  if (result === 'BB') applyWalk(play, inning, turnsBefore)
  turnIndex.value = null
}

const countStatus = computed(() => countPending.value
  ? `${countPending.value === 'BB' ? 'Base por bolas' : 'Ponche'} en ${countRemaining.value} s`
  : '')

// Otro turno: la cuenta empieza en 0-0
watch([() => panelTurn.value?.line, () => panelTurn.value?.inning, turnIndex, battingSide], resetCount)
onBeforeUnmount(stopCount)

const closeInning = () => {
  const next = sequence.value.next
  if (next) closedInnings.value[battingSide.value].push(next.inning)
}

const undoLastTurn = () => {
  const last = sequence.value.turns[sequence.value.turns.length - 1]
  if (!last) return
  last.line.plays.splice(last.line.plays.indexOf(last.play), 1)
  closedInnings.value[battingSide.value] = closedInnings.value[battingSide.value].filter(inning => inning < last.inning)
  turnIndex.value = null
}

// Una casilla ya capturada abre su turno; las vacías no (se captura en orden)
const openTurn = (line, inning) => {
  const index = sequence.value.turns.findIndex(turn => turn.line === line && turn.inning === inning)
  if (index !== -1) turnIndex.value = index
}

const notationOptions = play => NOTATIONS[play.result] || []

// ---------- GAME STATS ----------
const gameStatsDialog = ref(false)
const gameStatsLine = ref(null)
const openGameStats = (line) => {
  gameStatsLine.value = line
  gameStatsDialog.value = true
}
const initials = name => String(name || '').split(/\s+/).filter(Boolean).slice(0, 2).map(word => word[0].toUpperCase()).join('')
// Posición · Turno N · equipo
const gameStatsInfo = computed(() => {
  const line = gameStatsLine.value
  if (!line) return ''
  const side = SIDES.find(item => batting.value[item].includes(line))
  if (!side) return ''
  const slot = battingSlots(side).findIndex(item => item.includes(line))
  return [line.position, `Turno ${slot + 1}`, teamOf(side).name].filter(Boolean).join('  ·  ')
})

const enteredInningOptions = computed(() => Array.from({ length: innings.value }, (_, index) => index + 1))

// Cambios que ya batearon sin indicar su entrada; los de la banca (sin turnos) pueden esperar
const substitutesWithoutEntry = computed(() => batting.value[battingSide.value].filter(line => line.is_substitute && !line.entered_inning && line.plays.some(play => play.result)))

// Al cambiar de equipo o de modalidad se regresa al turno por capturar
watch([battingSide, mode], () => {
  turnIndex.value = null
})
watch(() => sequence.value.turns.length, (length) => {
  if (turnIndex.value !== null && turnIndex.value >= length) turnIndex.value = null
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
const buildPayload = () => {
  const payload = { mode: mode.value }
  for (const side of SIDES) {
    payload[`${side}_score`] = mode.value === 'linescore'
      ? inningRuns(side)
      : fromLineup(side) ? columnTotal(side, 'r') : toInt(totals.value[side].r)
    payload[`${side}_hits`] = fromLineup(side) ? columnTotal(side, 'h') : toInt(totals.value[side].h)
    payload[`${side}_errors`] = toInt(totals.value[side].e)
    payload[`${side}_lob`] = mode.value === 'scorecard' ? teamLob(side) : toInt(totals.value[side].lob)
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
    payload.batting = SIDES.flatMap(side => linesToSave(side)
      .map((line, _, sent) => ({
        team_id: teamIdOf(side),
        player_id: line.player_id,
        placeholder: savedPlaceholder(side, line),
        position: line.position,
        is_substitute: line.is_substitute,
        entered_inning: line.is_substitute ? line.entered_inning : null,
        plate_appearances: line.plays.filter(play => play.result).map(play => ({
          inning: play.inning,
          result: play.result,
          notation: play.notation || null,
          rbi: toInt(play.rbi),
          bases: play.bases,
          scored: play.bases === 4,
          out_on_base: play.out_on_base,
          out_notation: play.out_on_base ? play.out_notation || null : null,
          out_during_order: play.out_on_base && sent.includes(play.out_during) ? sent.indexOf(play.out_during) + 1 : null,
          out_type: play.out_on_base ? play.out_type : null,
          stolen_bases: play.stolen_bases || [],
          dp_runner_order: play.result === 'DP' && sent.includes(play.dp_runner) ? sent.indexOf(play.dp_runner) + 1 : null,
          dp_notation: dpOutOf(play) ? play.dp_notation || null : null
        })),
        ...Object.fromEntries(STATS.map(key => [key, toInt(line[key])])),
        ...(mode.value === 'scorecard' ? { lob: lineLob(line) } : {})
      })))
  }

  return payload
}

// ---------- AUTOGUARDADO ----------
// Lo que se guardaría ahora. Cada vez que cambia (elegir jugador o posición, registrar una jugada, la barra del
// scorecard, +/− Entrada, la modalidad...) se avisa a la página con "change". Los campos de texto y número (notación,
// E, LOB, line score, estadísticas) avisan hasta que pierden el foco, no en cada tecla
const payloadJson = computed(() => (ready.value ? JSON.stringify(buildPayload()) : null))
let lastEmitted = null
const DEFERRED_FIELDS = 'input.notation-input, input.stat-input'
const isTypingInField = () => !!document.activeElement?.matches?.(DEFERRED_FIELDS)

const emitChange = () => {
  const json = payloadJson.value
  if (json === null || json === lastEmitted) return
  // Lo primero que se calcula al cargar el partido es el punto de partida: no se vuelve a guardar
  if (lastEmitted === null) {
    lastEmitted = json
    return
  }
  if (isTypingInField()) return
  lastEmitted = json
  emit('change', JSON.parse(json))
}

watch(payloadJson, emitChange)

// Al salir de un campo de texto o número se avisa lo que se escribió (el foco ya está en otro elemento)
const onFocusOut = (event) => {
  if (event.target?.matches?.(DEFERRED_FIELDS)) setTimeout(emitChange, 0)
}

// Al cargar otro partido se vuelve a tomar el punto de partida
watch(ready, (value) => {
  if (!value) lastEmitted = null
})

// Hay algo escrito que todavía no se avisó (el campo sigue con el foco)
const isDirty = () => payloadJson.value !== null && lastEmitted !== null && payloadJson.value !== lastEmitted
// Avisa ya lo pendiente (antes de salir de la página)
const flushChange = () => {
  const json = payloadJson.value
  if (json === null || json === lastEmitted) return
  lastEmitted = json
  emit('change', JSON.parse(json))
}

defineExpose({ isDirty, flushChange })
</script>

<style scoped>
.glossary-title {
  font-weight: 700;
  color: #1f2937;
  margin: 8px 0 4px;
}

.glossary-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.75rem;
}

.glossary-table td {
  padding: 3px 6px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}

.glossary-term {
  width: 80px;
  font-weight: 700;
  white-space: nowrap;
}

/* Instrucciones y avisos del panel de captura: texto pequeño para no competir con la cuadrícula */
.modal-note {
  font-size: 0.75rem !important;
  line-height: 1.4;
}

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

/* Marcador: el nombre del equipo alineado a la izquierda */
.box-table--score th:first-child,
.box-table--score td:first-child {
  text-align: left;
}

/* Marcador más compacto: los inputs heredan el tamaño (font: inherit) */
.box-table--score {
  font-size: 0.75rem;
}

.box-table--score td {
  padding-top: 2px;
  padding-bottom: 2px;
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

/* Renglón del lineup bajo el ratón: fondo azul claro y bordes marcados; las casillas del
   scorecard conservan su color de entrada (jugada, en juego, sin jugar) */
.box-table .lineup-row:hover > td:not(.sc-cell) {
  background-color: #e8f1fd;
}

.box-table .lineup-row:hover > td {
  box-shadow: inset 0 1px 0 #90caf9, inset 0 -1px 0 #90caf9;
}

.box-table .lineup-row:hover > td:first-child {
  box-shadow: inset 3px 0 0 #1976d2, inset 0 1px 0 #90caf9, inset 0 -1px 0 #90caf9;
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

/* Solo las casillas ya capturadas se pueden abrir */
.sc-cell--played:hover {
  background: #eef6ff;
}

.sc-play {
  white-space: nowrap;
  line-height: 1.2;
}

.sc-diamond {
  width: 14px;
  height: 14px;
  margin-right: 3px;
  vertical-align: -2px;
}

/* Diamante con los colores de impresión de The Perfect Boxscore (fondo claro) */
.sc-diamond__field {
  fill: none;
  stroke: #8a939e;
  stroke-width: 1.2;
}

.sc-diamond__path {
  stroke: #1f5fbf;
  stroke-width: 2.6;
  stroke-linecap: round;
}

.sc-diamond__path--stolen {
  stroke: #7b3fb8 !important;
}

.sc-diamond__runner {
  fill: #1f5fbf;
}

.sc-diamond__runner--stranded {
  fill: #d98200;
}

.sc-diamond__out {
  stroke: #c62828;
  stroke-width: 2.2;
  stroke-linecap: round;
  fill: none;
}

.sc-diamond--scored .sc-diamond__field {
  fill: rgba(46, 139, 87, 0.27);
}

.sc-diamond--scored .sc-diamond__path {
  stroke: #1e7d46;
}

/* Casilla: diamante a la izquierda; jugada y, debajo en letra chica, robos / DP / PK / PC / LOB */
.sc-play {
  display: flex;
  align-items: center;
  justify-content: center;
}

.sc-play__text {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
}

.sc-play__extra {
  display: inline-flex;
  gap: 3px;
  font-size: 0.625rem;
  line-height: 1.1;
}

.sc-steal {
  color: #7b3fb8;
}

.sc-stranded {
  color: #d98200;
  font-size: 0.625rem;
  letter-spacing: 0.04em;
}

/* Colores por resultado (hit, anotó, en base, out en base), como en The Perfect Boxscore */
.sc-cell.sc-cell--style-hit {
  background: #e8f1ff;
}

.sc-cell.sc-cell--style-scored {
  background: #e7f6ec;
}

.sc-cell.sc-cell--style-on-base {
  background: #f1f5fb;
}

.sc-cell.sc-cell--style-out-on-base {
  background: #fdecec;
}

.sc-play--scored {
  color: #1e7d46;
}

.sc-play--out-on-base {
  color: #c62828;
}

.count-dot {
  cursor: pointer;
}

/* Barra del scorecard: botones de solo icono alineados a la izquierda, como en The Perfect Boxscore */
.sc-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.sc-toolbar__gap {
  width: 10px;
}

.box-table .lineup-row--selected > td {
  background-color: #e8f0fe;
}

.box-table .lineup-row--selected > td:first-child {
  box-shadow: inset 3px 0 0 #1976d2;
}

.play-panel__stats {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 0.75rem;
  margin-top: 2px;
}

.game-stats-table th,
.game-stats-table td {
  text-align: center;
  min-width: 40px;
}

/* Casilla que no se usó en una entrada ya cerrada */
.sc-cell.sc-cell--unused {
  background: repeating-linear-gradient(135deg, #f1f5f9 0 4px, #e2e8f0 4px 5px) !important;
  cursor: default;
}

/* Se quedó en turno: su turno pasa a la siguiente entrada */
.sc-cell.sc-cell--next {
  background: #fef3c7 !important;
}

.sc-next {
  color: #92400e;
  font-weight: 700;
  font-size: 0.625rem;
  white-space: nowrap;
}

.sc-runner-out {
  color: #c62828;
  font-size: 0.6875rem;
}

.sc-play--hit {
  color: #1565c0;
}

.sc-rbi {
  margin-left: 1px;
  color: #2e7d32;
}

/* Entradas del scorecard: en juego (azul), sin jugar (gris) y casillas ya capturadas (verde) */
.sc-inning--current {
  background: #dbeafe !important;
  color: #1e40af !important;
}

.sc-inning--pending {
  background: #eef0f3 !important;
  color: #94a3b8 !important;
}

.sc-cell--current {
  background: #eff6ff;
}

.sc-cell--pending {
  background: repeating-linear-gradient(135deg, #f4f5f7, #f4f5f7 6px, #eceef1 6px, #eceef1 12px);
}

.sc-cell--played {
  background: #f0fdf4;
}

.inning--pending {
  color: #94a3b8;
  font-weight: 400 !important;
}

.sc-cell--empty {
  cursor: default;
}

.entered-field {
  flex: 0 0 76px;
}

.sc-cell--active {
  background: #dbeafe;
  outline: 2px solid #1976d2;
  outline-offset: -2px;
}

.play-panel {
  border: 1px solid #93c5fd;
  border-radius: 6px;
  background: #f8fbff;
}

.play-panel__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-bottom: 1px solid #dbeafe;
}

.play-panel__play {
  display: grid;
  grid-template-columns: 96px 1fr;
  gap: 8px 12px;
  align-items: center;
  padding: 10px 12px;
}

/* Escritorio: tabla a la izquierda (con su scroll horizontal) y botones de la jugada en una columna angosta a la
   derecha, como en The Perfect Boxscore. En pantallas más chicas la columna queda arriba de la tabla, como antes */
.sc-layout--scorecard {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.sc-layout--scorecard .sc-layout__side {
  order: -1;
}

@media (min-width: 1280px) {
  .sc-layout--scorecard {
    flex-direction: row;
    align-items: flex-start;
  }

  .sc-layout--scorecard .sc-layout__main {
    flex: 1 1 auto;
    min-width: 0;
  }

  .sc-layout--scorecard .sc-layout__side {
    order: 0;
    flex: 0 0 316px;
    width: 316px;
    position: sticky;
    top: 72px;
  }

  /* En columna: el título de cada sección arriba y sus botones debajo (5 a 6 por renglón) */
  .sc-layout__side .play-panel__play {
    display: block;
  }

  .sc-layout__side .play-panel__label {
    margin: 10px 0 4px;
    padding-bottom: 2px;
    border-bottom: 1px solid #dbeafe;
  }

  .sc-layout__side .play-panel__label:first-child {
    margin-top: 0;
  }

  .sc-layout__side .play-panel__group {
    margin-right: 0;
    margin-bottom: 4px;
    width: 100%;
  }

  .sc-layout__side .play-panel__group > .text-caption:first-child {
    width: 100%;
  }

  /* Corrido: "Robó" en su propio renglón, como en TPB */
  .sc-layout__side .steal-caption {
    width: 100%;
    margin-left: 0 !important;
    margin-top: 2px;
  }
}

.play-panel__play + .play-panel__play {
  border-top: 1px dashed #bfdbfe;
}

.play-panel__label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
}

.play-panel__options {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}

.play-panel__group {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  margin-right: 12px;
}

.notation-input {
  width: 90px;
  padding: 3px 6px;
  border: 1px solid #d0d7de;
  border-radius: 4px;
  background: #ffffff;
  color: #212121;
  font: inherit;
  font-size: 0.8125rem;
}

.notation-input:focus {
  outline: 2px solid #1976d2;
  outline-offset: -1px;
}

.totals-row.lob-row td {
  background: #f6f8fa;
  font-size: 0.75rem;
  color: #475569;
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

.roster-table {
  font-size: 0.8125rem;
}

.roster-table :deep(tbody tr) {
  cursor: pointer;
  user-select: none;
}

.cell-field--provisional :deep(.v-field) {
  background: #fff8e1;
}

.cell-field--provisional :deep(.v-field__input input::placeholder) {
  color: #b45309;
  opacity: 1;
  font-style: italic;
}

.cell-field :deep(.v-field__input) {
  min-height: 30px;
  padding-top: 2px;
  padding-bottom: 2px;
  font-size: 0.75rem;
}

/* Combo Pos: poco relleno y flecha compacta para que la abreviatura (SS, CF, 1B) quepa en la columna */
.position-field :deep(.v-field) {
  padding-inline-end: 2px;
}

.position-field :deep(.v-field__input) {
  padding-inline: 8px 0;
  flex-wrap: nowrap;
}

.position-field :deep(.v-select__selection) {
  margin: 0;
  max-width: none;
}

.position-field :deep(.v-select__selection-text) {
  overflow: visible;
  font-weight: 700;
  color: #1f2937;
}

.position-field :deep(.v-field__append-inner) {
  padding-inline-start: 0;
}

.position-field :deep(.v-field__append-inner .v-icon) {
  margin-inline-start: 0;
  font-size: 18px;
}

/* Lineup, pestañas de equipo y panel de captura más compactos */
.team-tabs :deep(.v-tab) {
  font-size: 0.8125rem;
}

.box-table--lineup {
  font-size: 0.75rem;
}

.box-table--lineup .sc-cell {
  font-size: 0.6875rem;
  height: 36px;
}

.box-table--lineup .sc-diamond {
  width: 12px;
  height: 12px;
}

.play-panel :deep(.v-btn) {
  font-size: 0.75rem;
}
</style>
