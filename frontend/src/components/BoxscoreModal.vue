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
                  <td class="total">
                    <span v-if="mode === 'scorecard'" class="font-weight-black" title="Corredores que quedaron en base al terminar las entradas jugadas">{{ teamLob(side) }}</span>
                    <input v-else v-model="totals[side].lob" type="number" min="0" class="stat-input stat-input--total" />
                  </td>
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
              mientras tanto puedes capturar con un lineup provisional.
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
              <div v-if="batting[battingSide].length === 0" class="d-flex flex-wrap align-center ga-2 text-body-2 text-grey-darken-1 pa-3">
                <span class="mr-auto">Agrega el lineup de {{ teamOf(battingSide).name }} para capturar sus turnos, o empieza con uno provisional y asigna a los jugadores después.</span>
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
                <v-btn size="small" variant="flat" color="primary" class="text-none" prepend-icon="mdi-account-multiple-plus" @click="addProvisionalLineup">
                  Lineup provisional (9)
                </v-btn>
              </div>
              <div v-else-if="substitutesWithoutEntry.length" class="text-body-2 text-error pa-3">
                <v-icon icon="mdi-alert-circle-outline" size="18" class="mr-1" />
                Indica en qué entrada entró {{ substitutesWithoutEntry.map(playerName).join(', ') }} (columna "Entró" del lineup) para seguir capturando.
              </div>
              <div v-else-if="!panelTurn" class="text-body-2 text-grey-darken-1 pa-3">No hay turnos por capturar.</div>
              <div v-else-if="panelTurn.inning > innings" class="d-flex flex-wrap align-center ga-2 pa-3 text-body-2">
                <span class="mr-auto">Se capturaron las {{ innings }} entradas de {{ teamOf(battingSide).name }}.</span>
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
                  </div>
                  <v-btn size="small" variant="outlined" class="text-none" prepend-icon="mdi-arrow-left" :disabled="!canGoBack" @click="goBack">Anterior</v-btn>
                  <v-btn size="small" variant="flat" color="black" class="text-none" append-icon="mdi-arrow-right" :disabled="turnIndex === null" @click="goNext">Siguiente</v-btn>
                  <v-menu location="bottom end">
                    <template v-slot:activator="{ props: menuProps }">
                      <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="small" variant="text" title="Más acciones" />
                    </template>
                    <v-list density="compact" class="py-1">
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

                <div class="play-panel__play">
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

                  <template v-if="panelPlay.result">
                    <div class="play-panel__label">Anotación</div>
                    <div class="play-panel__options">
                      <v-chip
                        v-for="notation in notationOptions(panelPlay)"
                        :key="notation.value"
                        size="small"
                        label
                        :title="notation.title"
                        :color="panelPlay.notation === notation.value ? 'primary' : undefined"
                        :variant="panelPlay.notation === notation.value ? 'flat' : 'outlined'"
                        @click="panelPlay.notation = panelPlay.notation === notation.value ? '' : notation.value"
                      >
                        {{ notation.value }}
                      </v-chip>
                      <input v-model="panelPlay.notation" maxlength="20" placeholder="Otra..." class="notation-input" title="Anotación libre" />
                    </div>

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
                        :disabled="panelPlay.bases < 1 || panelPlay.bases > 3"
                        @click="setOutOnBase(panelPlay, !panelPlay.out_on_base)"
                      >
                        Out en base
                      </v-chip>
                    </div>

                    <template v-if="panelPlay.out_on_base">
                      <div class="play-panel__label">Out del corredor</div>
                      <div class="play-panel__options">
                        <v-chip
                          v-for="notation in RUNNER_OUTS"
                          :key="notation.value"
                          size="small"
                          label
                          :title="notation.title"
                          :color="panelPlay.out_notation === notation.value ? 'error' : undefined"
                          :variant="panelPlay.out_notation === notation.value ? 'flat' : 'outlined'"
                          @click="panelPlay.out_notation = panelPlay.out_notation === notation.value ? '' : notation.value"
                        >
                          {{ notation.value }}
                        </v-chip>
                        <input v-model="panelPlay.out_notation" maxlength="20" placeholder="Otra..." class="notation-input" title="Cómo lo pusieron out" />
                      </div>

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
                        :color="toInt(panelPlay.rbi) === rbi ? 'success' : undefined"
                        :variant="toInt(panelPlay.rbi) === rbi ? 'flat' : 'outlined'"
                        @click="panelPlay.rbi = rbi"
                      >
                        {{ rbi }}
                      </v-chip>
                    </div>
                  </template>
                </div>
              </template>
            </div>

            <div class="box-wrap">
              <table class="box-table">
                <thead>
                  <tr>
                    <th class="order">#</th>
                    <th class="text-left player">Jugador</th>
                    <th class="position">Pos</th>
                    <template v-if="mode === 'scorecard'">
                      <th
                        v-for="inning in innings"
                        :key="`inning-${inning}`"
                        class="sc-inning"
                        :class="`sc-inning--${inningState(battingSide, inning)}`"
                        :title="INNING_STATES[inningState(battingSide, inning)]"
                      >
                        {{ inning }}
                      </th>
                    </template>
                    <th v-for="column in battingColumns" :key="column.key" :title="column.title" :class="{ derived: column.derived }">{{ column.label }}</th>
                    <th class="row-actions"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, index) in batting[battingSide]" :key="line.uid" class="lineup-row" :class="{ 'substitute-row': line.is_substitute }">
                    <td class="order text-grey-darken-1">{{ line.is_substitute ? '' : slotNumber(index) }}</td>
                    <td class="player">
                      <div class="d-flex align-center">
                        <v-icon v-if="line.is_substitute" icon="mdi-subdirectory-arrow-right" size="18" class="text-grey-darken-1 mr-1" title="Entró de cambio" />
                        <v-autocomplete
                          v-model="line.player_id"
                          :items="playerOptions(line)"
                          item-title="name"
                          item-value="id"
                          :placeholder="line.placeholder ? `${line.placeholder} (provisional)` : 'Selecciona jugador'"
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
                        :class="[
                          `sc-cell--${inningState(battingSide, inning)}`,
                          {
                            'sc-cell--active': panelTurn && panelTurn.line === line && panelTurn.inning === inning,
                            'sc-cell--empty': cellPlays(line, inning).length === 0,
                            'sc-cell--played': cellPlays(line, inning).length > 0
                          }
                        ]"
                        :title="cellPlays(line, inning).length ? 'Ver este turno' : 'Los turnos se capturan en orden con Anterior / Siguiente'"
                        @click="openTurn(line, inning)"
                      >
                        <div v-for="(play, playIndex) in cellPlays(line, inning)" :key="playIndex" class="sc-play" :class="{ 'sc-play--hit': isHit(play) }">
                          <svg v-if="play.bases > 0" viewBox="0 0 20 20" class="sc-diamond" :class="{ 'sc-diamond--scored': play.bases === 4, 'sc-diamond--out': play.out_on_base }">
                            <title>{{ basesTitle(play) }}</title>
                            <polygon points="10,2 18,10 10,18 2,10" class="sc-diamond__field" />
                            <polyline :points="DIAMOND_PATH.slice(0, play.bases + 1).join(' ')" class="sc-diamond__path" />
                          </svg>{{ playLabel(play) }}<sup v-if="play.rbi" class="sc-rbi" :title="`${play.rbi} impulsada(s)`">{{ play.rbi }}</sup>
                          <div v-if="play.out_on_base" class="sc-runner-out" :title="runnerOutTitle(play)">
                            <v-icon icon="mdi-close" size="10" />{{ play.out_notation || 'Out' }}
                          </div>
                          <div v-else-if="isStranded(play)" class="sc-stranded" title="Se quedó en base al terminar la entrada">LOB</div>
                          <div v-if="lobByPlay.get(play)" class="sc-lob" :title="`Hizo el tercer out y dejó ${lobByPlay.get(play)} en base`">
                            Dejó {{ lobByPlay.get(play) }}
                          </div>
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
                    <td :colspan="battingColumns.length + 4 + (mode === 'scorecard' ? innings : 0)" class="text-center text-grey-darken-1 pa-4">
                      Sin bateadores. Usa <strong>Agregar bateador</strong> para armar el lineup.
                    </td>
                  </tr>
                  <tr v-else class="totals-row">
                    <td></td>
                    <td class="text-left font-weight-bold">Totales</td>
                    <td></td>
                    <template v-if="mode === 'scorecard'">
                      <td v-for="inning in innings" :key="`inning-${inning}`" class="font-weight-bold" :class="{ 'inning--pending': inningState(battingSide, inning) === 'pending' }" title="Carreras de la entrada">{{ inningRunsLabel(battingSide, inning) }}</td>
                    </template>
                    <td v-for="column in battingColumns" :key="column.key" class="font-weight-bold">{{ columnTotal(battingSide, column.key) }}</td>
                    <td></td>
                  </tr>
                  <tr v-if="mode === 'scorecard' && batting[battingSide].length" class="totals-row lob-row">
                    <td></td>
                    <td class="text-left font-weight-bold" title="Dejados en base (Left On Base)">LOB</td>
                    <td></td>
                    <td
                      v-for="inning in innings"
                      :key="`lob-${inning}`"
                      :class="{ 'inning--pending': inningState(battingSide, inning) !== 'played' }"
                      :title="inningState(battingSide, inning) === 'played' ? 'Corredores que quedaron en base al terminar la entrada' : 'La entrada no ha terminado'"
                    >
                      {{ inningState(battingSide, inning) === 'played' ? inningLob(battingSide, inning) : '–' }}
                    </td>
                    <td :colspan="battingColumns.length" class="text-left font-weight-bold">{{ teamLob(battingSide) }} en el juego</td>
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
            <v-btn
              v-if="batting[battingSide].length === 0"
              size="small"
              variant="outlined"
              color="primary"
              class="text-none font-weight-bold mt-3 ml-2"
              prepend-icon="mdi-account-multiple-plus"
              title="Agrega 9 bateadores provisionales para capturar sin el lineup; después eliges al jugador real de cada uno"
              @click="addProvisionalLineup"
            >
              Lineup provisional (9)
            </v-btn>
          </template>
        </template>
      </v-card-text>

      <!-- Roster del equipo: doble clic en un jugador para agregarlo al lineup -->
      <v-dialog v-model="rosterDialog" max-width="720px" scrollable>
        <v-card v-if="game" class="rounded-xl pa-2" theme="light">
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
              Si hay bateadores provisionales, el jugador ocupa el primero de ellos; si no, se agrega al final como titular.
            </div>
          </v-card-text>
          <v-card-actions class="pa-4 pt-0 justify-end">
            <v-btn color="black" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" @click="rosterDialog = false">Listo</v-btn>
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
          :disabled="!ready || tooManyStarters"
          :title="tooManyStarters ? `Un equipo tiene más de ${MAX_STARTERS} titulares` : ''"
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

// Anotaciones de scorebook sugeridas por jugada, ordenadas por posición.
// En los hits la anotación es hacia dónde fue la bola y la casilla muestra p. ej. 1B-8 o HR-78
const NOTATIONS = {
  '1B': withGaps(byPosition('', f => `Sencillo ${f.a}`), gap => `Sencillo ${gap}`),
  IH: byPosition('', f => `Hit de cuadro ${f.a}`, [1, 2, 3, 4, 5, 6]),
  '2B': withGaps(byPosition('', f => `Doble ${f.a}`, [5, 7, 8, 9]), gap => `Doble ${gap}`),
  '3B': withGaps(byPosition('', f => `Triple ${f.a}`, [7, 8, 9]), gap => `Triple ${gap}`),
  HR: withGaps(byPosition('', f => `Home run por ${f.a.replace(/^a(l)? /, 'e$1 ')}`, [7, 8, 9]), gap => `Home run ${gap}`),
  GO: withTitles([
    ['1-3', 'Rodado al pitcher, out en primera'],
    ['3U', 'Rodado a primera, out sin asistencia'],
    ['3-1', 'Rodado a primera, out con el pitcher cubriendo'],
    ['4-3', 'Rodado a segunda, out en primera'],
    ['4-6', 'Rodado a segunda, out forzado en segunda'],
    ['5-3', 'Rodado a tercera, out en primera'],
    ['6-3', 'Rodado al short, out en primera'],
    ['6-4', 'Rodado al short, out forzado en segunda']
  ]),
  FO: byPosition('F', f => `Fly/Elevado ${f.a}`),
  LO: byPosition('L', f => `Línea ${f.a}`),
  // Secuencia completa del doble play; el out del corredor se anota también en su casilla (6-5)
  DP: withTitles([
    ['1-2-3', 'Doble play: pitcher a home, catcher a primera'],
    ['1-6-3', 'Doble play: pitcher a segunda, short a primera'],
    ['3-6-1', 'Doble play: primera a segunda, el pitcher cubre primera'],
    ['3-6-3', 'Doble play: primera a segunda, short a primera'],
    ['4-4-3', 'Doble play: segunda pisa la base y tira a primera'],
    ['4-6-3', 'Doble play: segunda al short, short a primera'],
    ['5-2-3', 'Doble play: tercera a home, catcher a primera'],
    ['5-4-3', 'Doble play: tercera a segunda, segunda a primera'],
    ['5-5-3', 'Doble play: tercera pisa la base y tira a primera'],
    ['6-4-3', 'Doble play: short a segunda, segunda a primera'],
    ['6-5-3', 'Doble play: short a tercera, tercera a primera'],
    ['6-6-3', 'Doble play: short pisa segunda y tira a primera']
  ]),
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
  ROE: ['roe']
}
const NOT_AT_BAT = ['BB', 'IBB', 'HBP', 'SF', 'SH']

// Outs que hace cada jugada; las que no aparecen dejan al bateador en base
const OUTS = { KS: 1, KC: 1, GO: 1, FO: 1, LO: 1, DP: 2, FC: 1, SF: 1, SH: 1 }

// De esos outs, los que son de un corredor (en DP el corredor y el bateador; en FC solo el corredor)
const RUNNER_OUTS_IN_PLAY = { DP: 1, FC: 1 }

// Jugadas en que el bateador se embasa y la base a la que llega de entrada (luego puede avanzar)
const START_BASE = { '1B': 1, IH: 1, '2B': 2, '3B': 3, HR: 4, BB: 1, IBB: 1, HBP: 1, KDS: 1, KDC: 1, FC: 1, ROE: 1 }

const BASES = [
  { value: 1, label: '1ra', title: 'Se quedó en primera' },
  { value: 2, label: '2da', title: 'Llegó a segunda' },
  { value: 3, label: '3ra', title: 'Llegó a tercera' },
  { value: 4, label: 'Home', title: 'Llegó a home: anotó carrera' }
]

// Vértices del diamante en orden de corrido: home, primera, segunda, tercera, home
const DIAMOND_PATH = ['10,18', '18,10', '10,2', '2,10', '10,18']

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
  ['7-2', 'Out en home: tiro del jardín izquierdo'],
  ['8-2', 'Out en home: tiro del jardín central'],
  ['9-2', 'Out en home: tiro del jardín derecho'],
  ['CS2-4', 'Atrapado robando segunda: del catcher a segunda base'],
  ['CS2-6', 'Atrapado robando segunda: del catcher al short'],
  ['CS2-5', 'Atrapado robando tercera: del catcher a tercera'],
  ['PO1-3', 'Pick-off en primera: del pitcher a primera'],
  ['PO2-6', 'Pick-off en segunda: del catcher al short']
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

// En el scorecard todo el bateo se calcula de los turnos
const SCORECARD_COLUMNS = [
  { key: 'ab', label: 'AB', title: 'Turnos oficiales al bat', derived: true },
  { key: 'r', label: 'R', title: 'Carreras anotadas', derived: true },
  { key: 'h', label: 'H', title: 'Hits', derived: true },
  { key: 'rbi', label: 'RBI', title: 'Carreras impulsadas', derived: true },
  { key: 'bb', label: 'BB', title: 'Bases por bolas', derived: true },
  { key: 'so', label: 'K', title: 'Ponches', derived: true },
  { key: 'lob', label: 'LOB', title: 'Corredores que dejó en base al hacer el tercer out', derived: true }
]

const STATS = ['pa', 'ab', 'r', 'h', 'singles', 'ih', 'doubles', 'triples', 'hr', 'rbi', 'bb', 'ibb', 'hbp', 'sf', 'sh', 'roe', 'so', 'so_swinging', 'so_looking', 'lob', 'e']

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
// Scorecard: entradas terminadas a mano (outs en las bases, no se guardan) y turno que se ve en el panel
const closedInnings = ref({ away: [], home: [] })
const turnIndex = ref(null)

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
    rbi: play.rbi ?? 0,
    bases: play.bases ?? (play.scored ? 4 : 0),
    scored: !!play.scored,
    out_on_base: !!play.out_on_base,
    out_notation: play.out_notation ?? '',
    // Renglón del bateador en cuyo turno lo pusieron out (se resuelve al cargar el lineup)
    out_during: null,
    out_during_order: play.out_during_order ?? null
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
    }
  }
  battingSide.value = 'away'
  closedInnings.value = { away: [], home: [] }
  turnIndex.value = null
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

// Nueve bateadores provisionales para capturar en vivo sin tener el lineup
const addProvisionalLineup = () => {
  batting.value[battingSide.value].push(...Array.from({ length: 9 }, (_, index) => newLine({ placeholder: `Bateador ${index + 1}` })))
}

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
const startersCount = side => batting.value[side].filter(line => !line.is_substitute && (line.player_id || line.placeholder)).length

const tooManyStarters = computed(() => isBatting.value && SIDES.some(side => startersCount(side) > MAX_STARTERS))

const provisionalCount = computed(() => batting.value[battingSide.value].filter(line => !line.player_id).length)

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
  if (mode.value === 'scorecard') return key === 'lob' ? lineLob(line) : playStats(line)[key] ?? toInt(line[key])
  if (mode.value === 'batting_full' && (key === 'h' || key === 'so')) return derivedValue(line, key)
  return toInt(line[key])
}

const columnTotal = (side, key) => batting.value[side].reduce((sum, line) => sum + statValue(line, key), 0)

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
    play.out_notation = ''
    play.out_during = null
  }
}

const runnerOutTitle = (play) => {
  const during = play.out_during ? ` en el turno de ${playerName(play.out_during)}` : ''
  return `Out en base${play.out_notation ? ` (${play.out_notation})` : ''}${during}`
}

const basesTitle = (play) => {
  if (play.bases === 4) return 'Anotó carrera'
  const base = BASES.find(item => item.value === play.bases)?.title || ''
  return play.out_on_base ? `${base}; después lo pusieron out` : base
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
  || `Bateador ${batting.value[battingSide.value].indexOf(line) + 1}`

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

// Dejados en base: corredores que llegaron a 1ra, 2da o 3ra y no anotaron ni fueron puestos out.
// Solo cuentan las entradas que ya terminaron (la que está en juego aún puede cambiar)
const inningLob = (side, inning) => batting.value[side]
  .flatMap(line => line.plays)
  .filter(play => play.result && play.inning === inning && play.bases >= 1 && play.bases <= 3 && !play.out_on_base)
  .length

// Corredor que se quedó en base al terminar su entrada
const isStranded = play => play.bases >= 1 && play.bases <= 3 && !play.out_on_base
  && inningState(SIDES.find(side => batting.value[side].some(line => line.plays.includes(play))), play.inning) === 'played'

// LOB individual: al bateador que hace el tercer out se le cuentan los corredores que quedaron en base
// en esa entrada (sin contarse a sí mismo si se embasó, como en una bola ocupada)
const lobByPlay = computed(() => {
  const result = new Map()
  for (const side of SIDES) {
    for (const turn of turnSequence(side).turns.filter(item => item.endsInning)) {
      const stranded = batting.value[side].flatMap(line => line.plays)
        .filter(play => play !== turn.play && play.result && play.inning === turn.inning && play.bases >= 1 && play.bases <= 3 && !play.out_on_base)
      if (stranded.length) result.set(turn.play, stranded.length)
    }
  }
  return result
})

const lineLob = line => line.plays.reduce((sum, play) => sum + (lobByPlay.value.get(play) || 0), 0)

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
  if (slots.length === 0) return { turns, next: null }

  const used = new Set()
  const countedRunnerOuts = new Set()
  const pending = () => batting.value[side].flatMap(line => line.plays).filter(play => play.result && !used.has(play))
  let slot = 0
  let inning = 1
  let outs = 0
  // Corredores de la entrada puestos out sin indicar en qué turno (se contaron en su propio turno)
  let unlinkedOuts = []

  for (let guard = 0; guard < 1000; guard++) {
    const line = batterFor(slots[slot], inning)
    const play = line.plays.find(item => item.result && item.inning === inning && !used.has(item))

    if (play) {
      used.add(play)
      turns.push({ line, inning, slot, play, outsBefore: outs })
      // El corredor puesto out en las bases también cuenta para cerrar la entrada
      const runnerOuts = runnerOutsIn(side, line, inning, play, used, countedRunnerOuts)
      if (play.out_on_base && !play.out_during) unlinkedOuts.push(play)

      // En DP y FC uno de los outs de la jugada es de un corredor. Si ese corredor se marcó out en este
      // turno ya está incluido; si se marcó out sin indicar el turno, se toma como el de esta jugada
      // y deja de contarse aparte
      let inPlay = RUNNER_OUTS_IN_PLAY[play.result] || 0
      const included = Math.min(runnerOuts, inPlay)
      inPlay -= included
      while (inPlay > 0 && unlinkedOuts.length) {
        unlinkedOuts.pop()
        outs--
        inPlay--
      }
      outs += (OUTS[play.result] || 0) + runnerOuts - included

      slot = (slot + 1) % slots.length
      if (outs >= 3) {
        turns[turns.length - 1].endsInning = true
        inning++
        outs = 0
        unlinkedOuts = []
      }
      continue
    }

    // La entrada se terminó a mano, o ya hay turnos capturados en entradas posteriores
    const later = pending().filter(item => item.inning > inning)
    if (closedInnings.value[side].includes(inning) || later.length) {
      inning = later.length && !closedInnings.value[side].includes(inning) ? Math.min(...later.map(item => item.inning)) : inning + 1
      outs = 0
      unlinkedOuts = []
      // Si quien toca no tiene turno en esa entrada, sigue quien tenga el primero capturado
      if (!batterFor(slots[slot], inning).plays.some(item => item.result && item.inning === inning && !used.has(item))) {
        const found = slots.findIndex(candidate => batterFor(candidate, inning).plays.some(item => item.result && item.inning === inning && !used.has(item)))
        if (found !== -1) slot = found
      }
      continue
    }

    return { turns, next: { line, inning, slot, play: null, outsBefore: outs } }
  }

  return { turns, next: null }
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
    .filter(turn => turn.inning === inning && turn.play.bases >= 1 && turn.play.bases <= 3)
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
  if (!runnerPlay.out_notation && panelPlay.value.result === 'DP' && panelPlay.value.notation) {
    runnerPlay.out_notation = panelPlay.value.notation.split('-').slice(0, 2).join('-')
  }
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
const newPlay = () => ({ inning: null, result: null, notation: '', rbi: 0, bases: 0, scored: false, out_on_base: false, out_notation: '', out_during: null })

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

// Al elegir la jugada del turno por capturar, el turno queda registrado en la casilla del bateador
const chooseResult = (result) => {
  const play = panelPlay.value
  // Al cambiar la jugada se limpia la anotación y el bateador queda en la base a la que llegó con ella
  if (play.result !== result) {
    play.notation = ''
    setBases(play, START_BASE[result] || 0)
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
}

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
const handleSubmit = () => {
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
    payload.batting = SIDES.flatMap(side => batting.value[side]
      .filter(line => line.player_id || line.placeholder)
      .map((line, _, sent) => ({
        team_id: teamIdOf(side),
        player_id: line.player_id,
        placeholder: line.player_id ? null : line.placeholder,
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
          out_during_order: play.out_on_base && sent.includes(play.out_during) ? sent.indexOf(play.out_during) + 1 : null
        })),
        ...Object.fromEntries(STATS.map(key => [key, toInt(line[key])])),
        ...(mode.value === 'scorecard' ? { lob: lineLob(line) } : {})
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

.sc-diamond__field {
  fill: none;
  stroke: #cbd5e1;
  stroke-width: 1.5;
}

.sc-diamond__path {
  fill: none;
  stroke: #1565c0;
  stroke-width: 2.5;
  stroke-linejoin: round;
}

.sc-diamond--scored .sc-diamond__field {
  fill: #2e7d32;
  stroke: #2e7d32;
}

.sc-diamond--scored .sc-diamond__path {
  stroke: #2e7d32;
}

.sc-diamond--out .sc-diamond__path {
  stroke: #c62828;
}

.sc-stranded {
  color: #64748b;
  font-size: 0.625rem;
  letter-spacing: 0.04em;
}

.sc-lob {
  color: #b45309;
  font-size: 0.625rem;
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
  min-height: 32px;
  padding-top: 2px;
  padding-bottom: 2px;
  font-size: 0.8125rem;
}
</style>
