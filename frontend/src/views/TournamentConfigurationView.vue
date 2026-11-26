<!-- src/views/TournamentConfigurationView.vue -->
<!-- Configuración de un torneo: equipos participantes y rol de juegos -->
<template>
  <!-- Tema claro fijo: con el tema del sistema en oscuro los campos quedaban blancos sobre blanco -->
  <v-app class="bg-admin-grey" theme="ejp">

    <!-- BARRA SUPERIOR -->
    <v-app-bar flat color="brand-blue" class="px-4" height="64">
      <div class="font-weight-black text-white text-h6 d-flex align-center">
        <v-icon icon="mdi-shield-crown" color="white" class="mr-2" size="26" />
        Panel de Administración
      </div>

      <v-spacer></v-spacer>

      <v-btn
        :to="{ name: 'AdminDashboard', query: { tab: 'torneos' } }"
        variant="text"
        size="small"
        class="text-capitalize font-weight-bold text-white"
        prepend-icon="mdi-arrow-left"
      >
        Volver a Torneos
      </v-btn>
    </v-app-bar>

    <v-main>
      <!-- Carga inicial del torneo, sus equipos y su calendario: cubre el contenido sin transición -->
      <div v-if="initialLoading" class="page-loader">
        <v-progress-circular indeterminate color="primary" size="48" width="4" />
        <div class="mt-3 font-weight-bold text-grey-darken-3">Cargando torneo...</div>
      </div>

      <v-container class="pa-6" fluid>

        <!-- Encabezado: Torneos / Nombre -->
        <div class="d-flex align-center mb-2">
          <v-icon icon="mdi-trophy" size="20" class="text-grey-darken-1 mr-2" />
          <router-link :to="{ name: 'AdminDashboard', query: { tab: 'torneos' } }" class="text-subtitle-1 text-primary text-decoration-none">
            Torneos
          </router-link>
          <span class="text-subtitle-1 text-grey mx-2">/</span>
          <h1 class="text-subtitle-1 font-weight-bold text-grey-darken-4">
            {{ tournament?.name || 'Cargando...' }}
          </h1>
        </div>

        <v-tabs v-model="tab" color="primary" density="compact" class="config-tabs mb-4">
          <v-tab value="equipos" class="text-none" prepend-icon="mdi-shield-home">
            Equipos
            <v-chip size="x-small" class="ml-2" variant="tonal">{{ teamsStore.teams.length }}</v-chip>
          </v-tab>
          <v-tab value="jugadores" class="text-none" prepend-icon="mdi-account-group">
            Jugadores
          </v-tab>
          <v-tab value="calendario" class="text-none" prepend-icon="mdi-calendar-month">
            Calendario
          </v-tab>
        </v-tabs>

        <v-window v-model="tab">

          <!-- PESTAÑA: EQUIPOS -->
          <v-window-item value="equipos">
            <v-alert
              v-if="hasCalendar"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-3 text-body-2"
            >
              El torneo ya tiene calendario. Para agregar o retirar equipos, elimina primero el calendario.
            </v-alert>

            <div class="gh-box">
              <div class="gh-box__header">
                <v-icon icon="mdi-shield-home" size="18" class="mr-2 text-grey-darken-1" />
                <span class="font-weight-bold text-grey-darken-4">{{ teamsStore.teams.length }}</span>
                <span class="ml-1 text-grey-darken-2">{{ teamsStore.teams.length === 1 ? 'equipo participante' : 'equipos participantes' }}</span>
                <v-spacer />
                <v-btn
                  color="success"
                  size="small"
                  variant="flat"
                  class="text-none font-weight-bold"
                  prepend-icon="mdi-plus"
                  :disabled="hasCalendar"
                  @click="openAddDialog"
                >
                  Agregar equipos
                </v-btn>
                <v-menu location="bottom end">
                  <template v-slot:activator="{ props: menuProps }">
                    <v-btn
                      v-bind="menuProps"
                      color="primary"
                      size="small"
                      variant="outlined"
                      class="text-none font-weight-bold ml-2"
                      prepend-icon="mdi-dots-vertical"
                      :loading="isExportingTeams"
                      :disabled="teamsStore.teams.length === 0"
                    >
                      Acciones
                    </v-btn>
                  </template>
                  <v-list density="compact" min-width="220" class="py-1 rounded-lg">
                    <v-list-item prepend-icon="mdi-file-pdf-box" title="Roster" subtitle="Jugadores de cada equipo" @click="exportTeamsPdf('roster')" />
                    <v-list-item prepend-icon="mdi-chart-box-outline" title="Estadísticas" subtitle="Roster con su bateo en el torneo" @click="exportTeamsPdf('stats')" />
                  </v-list>
                </v-menu>
              </div>

              <div v-if="teamsStore.loading && teamsStore.teams.length === 0" class="pa-6 d-flex justify-center">
                <v-progress-circular indeterminate color="primary" size="28" />
              </div>

              <div v-else-if="teamsStore.teams.length === 0" class="pa-8 text-center text-grey-darken-1 text-body-2">
                Aún no hay equipos en este torneo. Usa <strong>Agregar equipos</strong> para inscribirlos.
              </div>

              <template v-else>
                <div v-for="team in teamsStore.teams" :key="team.id" class="gh-row">
                  <v-avatar color="brand-surface" size="24" class="mr-3 text-white team-abbr font-weight-bold">
                    {{ team.abbreviation }}
                  </v-avatar>
                  <span class="gh-row__name font-weight-bold text-black">{{ team.name }}</span>
                  <span class="gh-row__meta text-grey-darken-1">{{ team.short_name }}</span>
                  <v-chip size="x-small" variant="tonal" :color="team.roster_count ? 'primary' : undefined" class="mr-2" title="Jugadores en el roster">
                    <v-icon icon="mdi-account-group" size="12" class="mr-1" />{{ team.roster_count || 0 }}
                  </v-chip>
                  <span class="gh-row__date text-grey-darken-1">Inscrito {{ formatDate(team.pivot?.created_at) }}</span>
                  <v-menu location="bottom end">
                    <template v-slot:activator="{ props: menuProps }">
                      <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="x-small" variant="text" color="grey-darken-1" title="Acciones" />
                    </template>
                    <v-list density="compact" min-width="190" class="rounded-lg">
                      <v-list-item prepend-icon="mdi-cog-outline" title="Configuración" subtitle="Roster de la temporada" @click="openRoster(team)" />
                      <v-list-item prepend-icon="mdi-printer-outline" title="Imprimir Roster" subtitle="PDF con los jugadores del equipo" @click="exportTeamsPdf('roster', team)" />
                      <v-divider class="my-1" />
                      <v-list-item
                        prepend-icon="mdi-close"
                        title="Retirar del torneo"
                        base-color="error"
                        :disabled="hasCalendar"
                        @click="askRemoveTeam(team)"
                      />
                    </v-list>
                  </v-menu>
                </div>
              </template>
            </div>
          </v-window-item>

          <!-- PESTAÑA: JUGADORES -->
          <v-window-item value="jugadores">
            <div class="gh-box">
              <div class="gh-box__header ga-2">
                <v-icon icon="mdi-account-group" size="18" class="text-grey-darken-1" />
                <span class="font-weight-bold text-grey-darken-4">{{ filteredPlayers.length }}</span>
                <span class="text-grey-darken-2 text-no-wrap">{{ filteredPlayers.length === 1 ? 'jugador en la temporada' : 'jugadores en la temporada' }}</span>
                <v-spacer />
                <v-select
                  v-model="playerTeamIds"
                  :items="teamsStore.teams"
                  item-title="name"
                  item-value="id"
                  prepend-inner-icon="mdi-shield-home"
                  placeholder="Todos los equipos"
                  variant="outlined"
                  density="compact"
                  multiple
                  hide-details
                  clearable
                  style="max-width: 260px"
                >
                  <template v-slot:selection="{ item, index }">
                    <span v-if="index === 0" class="text-truncate">{{ item.name }}</span>
                    <span v-else-if="index === 1" class="text-grey-darken-1 text-caption ml-1 text-no-wrap">(+{{ playerTeamIds.length - 1 }})</span>
                  </template>
                </v-select>
                <v-text-field
                  v-model="playerSearch"
                  prepend-inner-icon="mdi-magnify"
                  placeholder="Buscar jugador o equipo..."
                  variant="outlined"
                  density="compact"
                  hide-details
                  clearable
                  style="max-width: 280px"
                />
                <v-menu location="bottom end">
                  <template v-slot:activator="{ props: menuProps }">
                    <v-btn
                      v-bind="menuProps"
                      color="primary"
                      size="small"
                      variant="outlined"
                      class="text-none font-weight-bold rounded-pill"
                      append-icon="mdi-chevron-down"
                      :loading="isExportingPlayers"
                      :disabled="filteredPlayers.length === 0"
                    >
                      Acciones
                    </v-btn>
                  </template>
                  <v-list density="compact" class="py-1">
                    <v-list-item prepend-icon="mdi-file-pdf-box" title="PDF" @click="exportPlayers('pdf')" />
                    <v-list-item prepend-icon="mdi-microsoft-excel" title="XLS" @click="exportPlayers('xlsx')" />
                  </v-list>
                </v-menu>
              </div>

              <v-data-table
                :headers="playerHeaders"
                :items="filteredPlayers"
                :search="playerSearch"
                :custom-filter="matchesSearch"
                :loading="loadingPlayers"
                :items-per-page="25"
                item-value="id"
                class="admin-data-table bg-white"
                density="compact"
                no-data-text="Aún no hay jugadores en los rosters. Agrégalos desde Equipos → Configuración."
              >
                <template v-slot:item.team_name="{ item }">
                  <div class="d-flex align-center">
                    <v-avatar color="brand-surface" size="22" class="mr-2 text-white team-abbr font-weight-bold">
                      {{ item.team_abbreviation }}
                    </v-avatar>
                    {{ item.team_name }}
                  </div>
                </template>
                <template v-slot:item.full_name="{ item }">
                  <span class="font-weight-bold text-black">{{ item.full_name }}</span>
                </template>
              </v-data-table>
            </div>
          </v-window-item>

          <!-- PESTAÑA: CALENDARIO -->
          <v-window-item value="calendario">
            <div class="gh-box mb-4">
              <div class="gh-box__header">
                <v-icon icon="mdi-cog-outline" size="18" class="mr-2 text-grey-darken-1" />
                <span class="font-weight-bold text-grey-darken-4">Parámetros del rol de juegos</span>
              </div>
              <div class="pa-4">
                <!-- Calendario guardado: los parámetros ya no se modifican -->
                <v-row v-if="hasCalendar" dense align="center">
                  <v-col v-for="param in savedParams" :key="param.label" cols="6" sm="3" md="2">
                    <div class="text-caption text-grey-darken-1">{{ param.label }}</div>
                    <div class="text-body-2 font-weight-bold text-grey-darken-4">{{ param.value }}</div>
                  </v-col>
                  <v-col cols="12" md="4" class="d-flex justify-md-end">
                    <v-menu location="bottom end">
                      <template v-slot:activator="{ props: menuProps }">
                        <v-btn
                          v-bind="menuProps"
                          color="primary"
                          size="small"
                          variant="outlined"
                          class="text-none font-weight-bold rounded-pill"
                          append-icon="mdi-chevron-down"
                          :loading="isExporting"
                        >
                          Opciones
                        </v-btn>
                      </template>
                      <v-list density="compact" class="py-1">
                        <v-list-item prepend-icon="mdi-file-pdf-box" title="PDF Partidos" @click="exportCalendar('pdf', 'games')" />
                        <v-list-item prepend-icon="mdi-file-pdf-box" title="PDF Series" @click="exportCalendar('pdf', 'series')" />
                        <v-divider class="my-1" />
                        <v-list-item prepend-icon="mdi-microsoft-excel" title="XLS Partidos" @click="exportCalendar('xlsx', 'games')" />
                        <v-list-item prepend-icon="mdi-microsoft-excel" title="XLS Series" @click="exportCalendar('xlsx', 'series')" />
                        <v-divider class="my-1" />
                        <v-list-item prepend-icon="mdi-delete-outline" title="Eliminar" base-color="error" @click="confirmAction = 'clear'" />
                      </v-list>
                    </v-menu>
                  </v-col>
                </v-row>

                <v-row v-else dense align="center">
                  <v-col cols="12" sm="6" md="3">
                    <v-text-field v-model="options.start_date" type="date" label="Fecha de inicio" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="6" sm="3" md="2">
                    <v-select v-model="options.rounds" :items="[1, 2, 3, 4]" label="Vueltas" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="6" sm="3" md="2">
                    <v-select v-model="options.games_per_series" :items="[1, 2, 3]" label="Juegos por serie" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="6" sm="3" md="2">
                    <v-select v-model="options.rest_days" :items="[0, 1, 2, 3, 4, 5, 6, 7]" label="Descanso entre series (días)" variant="outlined" density="compact" hide-details />
                  </v-col>
                  <v-col cols="12" md="3" class="d-flex ga-2 justify-md-end">
                    <v-btn
                      v-if="previewData"
                      color="grey-darken-1"
                      size="small"
                      variant="outlined"
                      class="text-none font-weight-bold rounded-pill"
                      :disabled="gamesStore.loading"
                      @click="discardPreview"
                    >
                      Descartar
                    </v-btn>
                    <v-btn
                      v-if="previewData"
                      color="primary"
                      size="small"
                      variant="outlined"
                      class="text-none font-weight-bold rounded-pill"
                      prepend-icon="mdi-content-save-edit-outline"
                      :loading="isSavingDraft"
                      :disabled="gamesStore.loading && !isSavingDraft"
                      @click="saveDraft"
                    >
                      Guardar borrador
                    </v-btn>
                    <v-btn
                      v-if="previewData"
                      color="success"
                      size="small"
                      variant="flat"
                      class="text-none font-weight-bold rounded-pill px-4"
                      prepend-icon="mdi-content-save"
                      :loading="gamesStore.loading && !isSavingDraft"
                      :disabled="isSavingDraft"
                      @click="saveCalendar"
                    >
                      Guardar calendario
                    </v-btn>
                    <v-btn
                      v-else
                      color="primary"
                      size="small"
                      variant="flat"
                      class="text-none font-weight-bold rounded-pill px-4"
                      prepend-icon="mdi-calendar-sync"
                      :loading="gamesStore.loading"
                      :disabled="teamsStore.teams.length < 2"
                      @click="previewCalendar"
                    >
                      Generar calendario
                    </v-btn>
                  </v-col>
                </v-row>
                <div class="text-caption text-grey-darken-1 mt-3">
                  Todos contra todos: en cada jornada cada equipo juega una serie; con número impar de equipos uno descansa.
                  Local y visitante se alternan entre jornadas y se invierten en cada vuelta.
                  <span v-if="teamsStore.teams.length >= 2">
                    Con {{ teamsStore.teams.length }} equipos: {{ preview.matchdays }} jornadas, {{ preview.series }} series y {{ preview.games }} partidos.
                  </span>
                  <span v-else class="text-error">Inscribe al menos 2 equipos para generar el calendario.</span>
                </div>
              </div>
            </div>

            <v-alert
              v-if="previewData"
              type="warning"
              variant="tonal"
              density="compact"
              class="mb-3 text-body-2"
            >
              Vista previa con {{ options.games_per_series }} {{ options.games_per_series === 1 ? 'juego' : 'juegos' }} por serie.
              Puedes cambiar los equipos o la fecha de cada partido desde su menú.
              El calendario aún no se guarda: presiona <strong>Guardar borrador</strong> para continuar después o <strong>Guardar calendario</strong> para confirmarlo.
              <div v-if="draftSavedAt" class="mt-1 text-caption">
                <template v-if="draftDirty">Hay cambios sin guardar en el borrador (guardado por última vez el {{ formatDateTime(draftSavedAt) }}).</template>
                <template v-else>Borrador guardado el {{ formatDateTime(draftSavedAt) }}.</template>
              </div>
            </v-alert>

            <TournamentSchedule
              :games="previewData ? previewData.games : gamesStore.games"
              :byes="previewData ? previewData.byes : gamesStore.byes"
              show-actions
              :show-boxscore="!previewData"
              @edit="editGame"
              @boxscore="openBoxscore"
              :loading="gamesStore.loading"
            />
          </v-window-item>
        </v-window>
      </v-container>
    </v-main>

    <!-- Equipos del torneo: a la izquierda los seleccionados (inician con los inscritos), a la derecha los disponibles -->
    <v-dialog v-model="showAddDialog" max-width="960px">
      <v-card class="rounded-xl pa-2" theme="ejp">
        <v-card-title class="text-h6 font-weight-black text-grey-darken-4 pt-4 px-4">
          Equipos del torneo
        </v-card-title>
        <v-card-text>
          <v-row dense>
            <!-- Seleccionados -->
            <v-col cols="12" md="6">
              <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white">
                <div class="d-flex align-center px-3 py-2">
                  <span class="text-subtitle-2 font-weight-bold text-grey-darken-4">Seleccionados</span>
                  <v-chip size="x-small" class="ml-2" color="success" variant="tonal">{{ selectedTeams.length }}</v-chip>
                  <v-spacer />
                  <v-btn
                    size="small"
                    variant="text"
                    color="grey-darken-1"
                    class="text-capitalize"
                    prepend-icon="mdi-close"
                    :disabled="selectedTeams.length === 0"
                    @click="selectedTeams = []"
                  >
                    Quitar todos
                  </v-btn>
                </div>
                <v-divider />
                <div v-if="selectedTeams.length === 0" class="pa-8 d-flex align-center justify-center text-center text-grey-darken-1 text-body-2 team-picker-list">
                  Elige equipos de la lista de disponibles.
                </div>
                <v-list v-else density="compact" class="py-0 team-picker-list">
                  <v-list-item v-for="team in selectedTeams" :key="team.id" @click="unselectTeam(team)">
                    <template #prepend>
                      <v-avatar color="brand-surface" size="24" class="mr-2 text-white team-abbr font-weight-bold">
                        {{ team.abbreviation || 'EQ' }}
                      </v-avatar>
                    </template>
                    <v-list-item-title class="font-weight-bold text-black text-body-2">{{ team.name }}</v-list-item-title>
                    <v-list-item-subtitle class="text-caption">
                      {{ team.short_name }}
                      <template v-if="team.roster_count"> · {{ team.roster_count }} {{ team.roster_count === 1 ? 'jugador' : 'jugadores' }}</template>
                    </v-list-item-subtitle>
                    <template #append>
                      <v-chip v-if="!enrolledIds.has(team.id)" size="x-small" label color="success" variant="tonal" class="mr-2">Nuevo</v-chip>
                      <v-icon icon="mdi-close" size="small" color="grey-darken-1" />
                    </template>
                  </v-list-item>
                </v-list>
              </v-card>
            </v-col>

            <!-- Disponibles: del propietario del torneo y aún no inscritos -->
            <v-col cols="12" md="6">
              <v-card variant="outlined" color="grey-lighten-2" class="rounded-lg bg-white">
                <div class="d-flex align-center px-3 py-2">
                  <span class="text-subtitle-2 font-weight-bold text-grey-darken-4">Disponibles</span>
                  <v-chip size="x-small" class="ml-2" variant="tonal">{{ remainingTeams.length }}</v-chip>
                  <v-spacer />
                  <v-btn
                    size="small"
                    variant="text"
                    color="success"
                    class="text-capitalize"
                    prepend-icon="mdi-chevron-double-left"
                    :disabled="remainingTeams.length === 0"
                    @click="selectAllTeams"
                  >
                    Agregar todos
                  </v-btn>
                </div>
                <v-divider />
                <div class="px-3 pt-3">
                  <v-text-field
                    v-model="teamSearch"
                    prepend-inner-icon="mdi-magnify"
                    placeholder="Buscar equipo..."
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                  />
                </div>
                <div v-if="loadingAvailableTeams" class="pa-8 mt-2 d-flex align-center justify-center team-picker-list">
                  <v-progress-circular indeterminate color="primary" />
                </div>
                <div v-else-if="remainingTeams.length === 0" class="pa-8 mt-2 d-flex align-center justify-center text-center text-grey-darken-1 text-body-2 team-picker-list">
                  {{ teamSearch ? 'Ningún equipo coincide con la búsqueda.' : 'No hay más equipos disponibles.' }}
                </div>
                <v-list v-else density="compact" class="py-0 mt-2 team-picker-list">
                  <v-list-item v-for="team in remainingTeams" :key="team.id" @click="selectTeam(team)">
                    <template #prepend>
                      <v-icon icon="mdi-chevron-left" size="small" color="success" class="mr-1" />
                      <v-avatar color="brand-surface" size="24" class="mr-2 text-white team-abbr font-weight-bold">
                        {{ team.abbreviation || 'EQ' }}
                      </v-avatar>
                    </template>
                    <v-list-item-title class="font-weight-bold text-black text-body-2">{{ team.name }}</v-list-item-title>
                    <v-list-item-subtitle class="text-caption">{{ team.short_name }}</v-list-item-subtitle>
                    <template #append>
                      <v-chip v-if="enrolledIds.has(team.id)" size="x-small" label color="error" variant="tonal">Se retirará</v-chip>
                    </template>
                  </v-list-item>
                </v-list>
              </v-card>
            </v-col>
          </v-row>

          <!-- Retirar un equipo borra también su roster en el torneo -->
          <v-alert v-if="removedWithRoster.length" type="warning" variant="tonal" density="compact" class="text-body-2 mt-3">
            Al guardar se retirará el roster de {{ removedWithRoster.map(team => `${team.name} (${team.roster_count})`).join(', ') }}.
          </v-alert>
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 justify-end ga-2">
          <span class="text-body-2 text-grey-darken-1 mr-auto">
            {{ selectedTeams.length }} {{ selectedTeams.length === 1 ? 'equipo seleccionado' : 'equipos seleccionados' }}
            <template v-if="addedCount || removedTeams.length">
              ({{ [addedCount && `${addedCount} por agregar`, removedTeams.length && `${removedTeams.length} por retirar`].filter(Boolean).join(', ') }})
            </template>
          </span>
          <v-btn variant="outlined" color="grey-darken-1" class="text-capitalize rounded-pill px-5" @click="showAddDialog = false">
            Cancelar
          </v-btn>
          <v-btn
            color="success"
            variant="flat"
            class="text-capitalize rounded-pill px-5 font-weight-bold"
            :disabled="loadingAvailableTeams || (!addedCount && !removedTeams.length)"
            :loading="teamsStore.loading"
            @click="saveTeams"
          >
            Guardar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Roster del equipo -->
    <TeamRosterModal v-model="showRosterModal" :tournament-id="id" :team="rosterTeam" />

    <!-- Editar partido -->
    <GameFormModal
      v-model="showGameModal"
      :game="gameToEdit"
      :teams="teamsStore.teams"
      :loading="isSavingGame"
      @save="saveGame"
    />

    <!-- Confirmaciones -->
    <v-dialog :model-value="!!confirmAction" max-width="420px" persistent>
      <v-card class="rounded-xl pa-2" theme="ejp">
        <v-card-title class="text-h6 font-weight-black text-grey-darken-4 pt-4 px-4">
          {{ confirmTexts[confirmAction]?.title }}
        </v-card-title>
        <v-card-text class="text-body-2 text-grey-darken-2">
          {{ confirmTexts[confirmAction]?.message }}
        </v-card-text>
        <v-card-actions class="pa-4 pt-0 justify-end ga-2">
          <v-btn variant="outlined" color="grey-darken-1" class="text-capitalize rounded-pill px-5" :disabled="isConfirming" @click="confirmAction = null">
            Cancelar
          </v-btn>
          <v-btn color="error" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" :loading="isConfirming" @click="runConfirmAction">
            {{ confirmTexts[confirmAction]?.button }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-app>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTournamentsStore } from '@/stores/tournaments'
import { useTournamentTeamsStore } from '@/stores/tournamentTeams'
import { useGamesStore } from '@/stores/games'
import { useNotificationsStore } from '@/stores/notifications'
import { getErrorMessage } from '@/utils/errors'
import { formatDate, parseDate, toIsoDate } from '@/utils/dates'
import TournamentSchedule from '@/components/TournamentSchedule.vue'
import GameFormModal from '@/components/GameFormModal.vue'
import TeamRosterModal from '@/components/TeamRosterModal.vue'

const props = defineProps({
  id: { type: [String, Number], required: true }
})

const tournamentsStore = useTournamentsStore()
const teamsStore = useTournamentTeamsStore()
const gamesStore = useGamesStore()
const router = useRouter()
const route = useRoute()
const notify = useNotificationsStore()

const tournament = ref(null)
// Pestaña inicial: la de ?tab= (al regresar del box score se abre el calendario)
const TABS = ['equipos', 'jugadores', 'calendario']
const tab = ref(TABS.includes(route.query.tab) ? route.query.tab : 'equipos')
const initialLoading = ref(true)
const hasCalendar = computed(() => gamesStore.games.length > 0)

// ---------- EQUIPOS ----------
const showAddDialog = ref(false)
const availableTeams = ref([])
const loadingAvailableTeams = ref(false)
const selectedTeams = ref([])
const teamSearch = ref('')

// Búsqueda por nombre, nombre corto o abreviación, sin distinguir mayúsculas ni acentos
const normalize = text => String(text ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()

// Equipos guardados en el torneo; la selección inicia con ellos
const enrolledIds = computed(() => new Set(teamsStore.teams.map(team => team.id)))
const selectedIds = computed(() => new Set(selectedTeams.value.map(team => team.id)))

// Disponibles: los inscritos que se quitaron de la selección y los que aún no participan,
// filtrados por la búsqueda
const remainingTeams = computed(() => {
  const query = normalize(teamSearch.value).trim()
  return [...teamsStore.teams, ...availableTeams.value].filter(team =>
    !selectedIds.value.has(team.id)
    && (!query || [team.name, team.short_name, team.abbreviation].some(value => normalize(value).includes(query)))
  )
})

const addedCount = computed(() => selectedTeams.value.filter(team => !enrolledIds.value.has(team.id)).length)
const removedTeams = computed(() => teamsStore.teams.filter(team => !selectedIds.value.has(team.id)))
const removedWithRoster = computed(() => removedTeams.value.filter(team => team.roster_count > 0))

const selectTeam = team => {
  selectedTeams.value = [...selectedTeams.value, team]
}

const unselectTeam = team => {
  selectedTeams.value = selectedTeams.value.filter(item => item.id !== team.id)
}

const selectAllTeams = () => {
  selectedTeams.value = [...selectedTeams.value, ...remainingTeams.value]
}

// El servidor solo regresa equipos activos del propietario del torneo que aún no están inscritos
const openAddDialog = async () => {
  selectedTeams.value = [...teamsStore.teams]
  availableTeams.value = []
  teamSearch.value = ''
  showAddDialog.value = true
  loadingAvailableTeams.value = true
  try {
    availableTeams.value = await teamsStore.fetchAvailableTeams(props.id)
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al cargar los equipos disponibles'))
  } finally {
    loadingAvailableTeams.value = false
  }
}

const saveTeams = async () => {
  try {
    await teamsStore.syncTeams(props.id, selectedTeams.value.map(team => team.id))
    notify.success('Equipos del torneo actualizados')
    showAddDialog.value = false
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al guardar los equipos'))
  }
}

const teamToRemove = ref(null)
const askRemoveTeam = (team) => {
  teamToRemove.value = team
  confirmAction.value = 'remove'
}

// Roster del equipo en este torneo
const rosterTeam = ref(null)
const showRosterModal = ref(false)
const openRoster = (team) => {
  rosterTeam.value = team
  showRosterModal.value = true
}

// ---------- CALENDARIO ----------
const options = ref({
  start_date: toIsoDate(new Date()),
  rounds: 2,
  games_per_series: 3,
  rest_days: 1
})

// Calendario calculado pero aún sin guardar (puede venir de un borrador y tener cambios manuales)
const previewData = ref(null)
// Hay partidos editados a mano en la vista previa: recalcularla los pierde
const previewEdited = ref(false)
// Fecha del borrador guardado en el servidor (null si no hay) y si la vista previa cambió desde entonces
const draftSavedAt = ref(null)
const draftDirty = ref(false)
const isSavingDraft = ref(false)

// '2026-11-19 14:30:00' -> 'jue, 19 nov a las 14:30'
const formatDateTime = value => `${formatDate(value)} a las ${String(value).substring(11, 16)}`

// Al cambiar los juegos por serie se calcula la vista previa; con otros parámetros
// se recalcula solo si ya había una. Se activa hasta cargar los datos del torneo.
const watchOptions = () => watch(
  () => ({ ...options.value }),
  (current, previous) => {
    if (teamsStore.teams.length < 2) return
    if (current.games_per_series !== previous.games_per_series || previewData.value) previewCalendar()
  }
)

// Toma los parámetros con los que se generó el calendario actual
const syncOptionsFromGames = () => {
  const games = gamesStore.games
  if (games.length === 0) return
  const gamesPerSeries = Math.max(...games.map(game => game.series_game))
  const secondMatchday = games.find(game => game.matchday === 2)
  const daysBetween = secondMatchday ? Math.round((parseDate(secondMatchday.scheduled_at) - parseDate(games[0].scheduled_at)) / 86400000) : gamesPerSeries
  options.value = {
    start_date: games[0].scheduled_at.substring(0, 10),
    rounds: Math.max(...games.map(game => game.round)),
    // El combo arranca con el valor del registro del torneo
    games_per_series: tournament.value?.games_per_series ?? gamesPerSeries,
    rest_days: Math.max(0, daysBetween - gamesPerSeries)
  }
}

// Totales esperados antes de generar
const preview = computed(() => {
  const teams = teamsStore.teams.length
  const perRound = teams % 2 === 0 ? teams - 1 : teams
  const series = (teams * (teams - 1) / 2) * options.value.rounds
  return {
    matchdays: perRound * options.value.rounds,
    series,
    games: series * options.value.games_per_series
  }
})

const previewCalendar = async () => {
  try {
    const hadEdits = previewEdited.value
    previewData.value = await gamesStore.preview(props.id, options.value)
    previewEdited.value = false
    draftDirty.value = true
    if (hadEdits) notify.warning('Se recalculó la vista previa con los nuevos parámetros; se perdieron los cambios manuales a los partidos.')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al generar el calendario'))
  }
}

// Guarda el calendario tal como está en la vista previa (con sus cambios) y deja en el torneo
// los juegos por serie elegidos; el borrador se descarta
const saveCalendar = async () => {
  try {
    await gamesStore.generate(props.id, options.value, previewData.value.games)
    tournament.value = { ...tournament.value, games_per_series: options.value.games_per_series }
    previewData.value = null
    previewEdited.value = false
    draftSavedAt.value = null
    draftDirty.value = false
    notify.success('Calendario guardado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al guardar el calendario'))
  }
}

// Guarda la vista previa como borrador para continuar después
const saveDraft = async () => {
  isSavingDraft.value = true
  try {
    const draft = await gamesStore.saveDraft(props.id, options.value, previewData.value.games)
    draftSavedAt.value = draft?.saved_at ?? null
    draftDirty.value = false
    notify.success('Borrador guardado correctamente')
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al guardar el borrador'))
  } finally {
    isSavingDraft.value = false
  }
}

// Sin borrador guardado solo se cierra la vista previa; con borrador se pide confirmación para borrarlo
const discardPreview = () => {
  if (draftSavedAt.value) {
    confirmAction.value = 'discardDraft'
    return
  }
  previewData.value = null
  previewEdited.value = false
}

// Recupera el borrador guardado (si hay y el torneo aún no tiene calendario)
const loadDraft = async () => {
  try {
    const draft = await gamesStore.fetchDraft(props.id)
    if (!draft) return
    options.value = { ...draft.options }
    previewData.value = { games: draft.games, byes: draft.byes }
    previewEdited.value = true
    draftSavedAt.value = draft.saved_at
    draftDirty.value = false
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al cargar el borrador del calendario'))
  }
}

// Equipo que descansa en cada jornada: el inscrito que no juega ningún partido de ella
const computeByes = (games) => {
  const byes = {}
  const playing = {}
  for (const game of games) {
    playing[game.matchday] ??= new Set()
    playing[game.matchday].add(game.home_team_id).add(game.away_team_id)
  }
  for (const [matchday, teamIds] of Object.entries(playing)) {
    const resting = teamsStore.teams.find(team => !teamIds.has(team.id))
    if (resting) byes[matchday] = resting
  }
  return byes
}

// Parámetros con que se guardó el calendario, como etiquetas
const savedParams = computed(() => [
  { label: 'Fecha de inicio', value: formatDate(options.value.start_date) },
  { label: 'Vueltas', value: options.value.rounds },
  { label: 'Juegos por serie', value: options.value.games_per_series },
  { label: 'Descanso entre series', value: `${options.value.rest_days} ${options.value.rest_days === 1 ? 'día' : 'días'}` }
])

const isExporting = ref(false)

// format: 'pdf' o 'xlsx'; view: 'games' (partidos) o 'series'
const exportCalendar = async (format, view) => {
  isExporting.value = true
  try {
    const name = (tournament.value?.name || 'torneo').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '_').toLowerCase()
    const prefix = view === 'series' ? 'calendario_series' : 'calendario_partidos'
    await gamesStore.exportCalendar(props.id, format, view, `${prefix}_${name}.${format}`)
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al generar el archivo'))
  } finally {
    isExporting.value = false
  }
}

// ---------- EQUIPOS: PDFs ----------
const isExportingTeams = ref(false)

// type: 'roster' (jugadores de cada equipo) o 'stats' (el roster con su bateo en el torneo);
// con team, solo el roster de ese equipo (Imprimir Roster en el menú de su renglón)
const exportTeamsPdf = async (type, team = null) => {
  isExportingTeams.value = true
  try {
    const name = [tournament.value?.name || 'torneo', team?.name].filter(Boolean).join(' ')
      .normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '_').toLowerCase()
    const prefix = type === 'stats' ? 'estadisticas_roster' : 'roster'
    await teamsStore.exportTeamsPdf(props.id, type, `${prefix}_${name}.pdf`, team?.id)
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al generar el archivo'))
  } finally {
    isExportingTeams.value = false
  }
}

// ---------- JUGADORES ----------
// Jugadores en los rosters de los equipos inscritos; se recargan al abrir la pestaña
const players = ref([])
const loadingPlayers = ref(false)
const playerSearch = ref('')
const isExportingPlayers = ref(false)
// Equipos elegidos en el combo; vacío = todos. Filtra la tabla y las exportaciones
const playerTeamIds = ref([])

const filteredPlayers = computed(() => playerTeamIds.value.length
  ? players.value.filter(player => playerTeamIds.value.includes(player.team_id))
  : players.value)

const playerHeaders = [
  { title: 'Jugador', key: 'full_name' },
  { title: 'CURP', key: 'curp' },
  { title: 'Equipo', key: 'team_name' }
]

// Búsqueda indistinta a acentos y mayúsculas en cada columna
const matchesSearch = (value, query) => normalize(value).includes(normalize(query).trim())

const loadPlayers = async () => {
  loadingPlayers.value = true
  try {
    players.value = await teamsStore.fetchPlayers(props.id)
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al cargar los jugadores'))
  } finally {
    loadingPlayers.value = false
  }
}

watch(tab, (value) => {
  if (value === 'jugadores') loadPlayers()
})

// format: 'pdf' o 'xlsx'
const exportPlayers = async (format) => {
  isExportingPlayers.value = true
  try {
    const name = (tournament.value?.name || 'torneo').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '_').toLowerCase()
    await teamsStore.exportPlayers(props.id, format, `jugadores_${name}.${format}`, playerTeamIds.value)
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al generar el archivo'))
  } finally {
    isExportingPlayers.value = false
  }
}

// ---------- ACCIONES DE PARTIDOS ----------
// item: un partido (vista Partidos) o una serie con sus partidos (vista Series)
const gameToEdit = ref(null)
const showGameModal = ref(false)
const isSavingGame = ref(false)

const editGame = (item) => {
  // Una serie agrupa varios partidos: se editan uno por uno
  if (item.games) {
    notify.warning('Para editar un partido cambia a la vista Partidos')
    return
  }
  gameToEdit.value = item
  showGameModal.value = true
}

// En la vista previa el cambio solo se aplica en pantalla; se guarda con el borrador o el calendario
const updatePreviewGame = (data) => {
  const team = id => teamsStore.teams.find(item => item.id === id)
  const games = previewData.value.games.map(game => game.game_number !== gameToEdit.value.game_number ? game : {
    ...game,
    away_team_id: data.away_team_id,
    home_team_id: data.home_team_id,
    away_team: team(data.away_team_id),
    home_team: team(data.home_team_id),
    scheduled_at: `${data.scheduled_at}:00`
  })
  previewData.value = { games, byes: computeByes(games) }
  previewEdited.value = true
  draftDirty.value = true
  showGameModal.value = false
  notify.success('Partido actualizado en la vista previa')
}

const saveGame = async (data) => {
  if (previewData.value) {
    updatePreviewGame(data)
    return
  }
  isSavingGame.value = true
  try {
    await gamesStore.updateGame(props.id, gameToEdit.value.id, data)
    notify.success('Partido actualizado correctamente')
    showGameModal.value = false
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al actualizar el partido'))
  } finally {
    isSavingGame.value = false
  }
}

// El box score de un partido se captura en su propia página (ruta GameBoxscore)
const openBoxscore = (item) => {
  // Una serie agrupa varios partidos: el box score es de cada partido
  if (item.games) {
    notify.warning('Para capturar el box score cambia a la vista Partidos')
    return
  }
  router.push({ name: 'GameBoxscore', params: { id: props.id, gameId: item.id } })
}

// ---------- CONFIRMACIONES ----------
const confirmAction = ref(null)
const isConfirming = ref(false)

const confirmTexts = computed(() => ({
  remove: {
    title: 'Retirar equipo',
    message: `¿Seguro que deseas retirar a ${teamToRemove.value?.name || 'este equipo'} del torneo?`,
    button: 'Retirar'
  },
  clear: {
    title: 'Eliminar calendario',
    message: '¿Seguro que deseas eliminar el calendario completo del torneo? Esta acción no se puede deshacer.',
    button: 'Eliminar'
  },
  discardDraft: {
    title: 'Descartar borrador',
    message: '¿Seguro que deseas descartar la vista previa y eliminar el borrador guardado del calendario? Se perderán los cambios a los partidos.',
    button: 'Descartar'
  }
}))

const runConfirmAction = async () => {
  isConfirming.value = true
  try {
    if (confirmAction.value === 'remove') {
      await teamsStore.removeTeam(props.id, teamToRemove.value.id)
      notify.success('Equipo retirado del torneo')
    } else if (confirmAction.value === 'clear') {
      await gamesStore.clear(props.id)
      notify.success('Calendario eliminado correctamente')
    } else if (confirmAction.value === 'discardDraft') {
      await gamesStore.discardDraft(props.id)
      previewData.value = null
      previewEdited.value = false
      draftSavedAt.value = null
      draftDirty.value = false
      notify.success('Borrador descartado')
    }
    confirmAction.value = null
  } catch (error) {
    notify.error(getErrorMessage(error, 'Ocurrió un error al realizar la acción'))
  } finally {
    isConfirming.value = false
  }
}

onMounted(async () => {
  // Limpiar datos de otro torneo abierto antes
  teamsStore.teams = []
  gamesStore.games = []
  gamesStore.byes = {}

  try {
    tournament.value = await tournamentsStore.fetchTournament(props.id)
    options.value.games_per_series = tournament.value.games_per_series ?? 3
  } catch (error) {
    notify.error(getErrorMessage(error, 'No se encontró el torneo'))
    initialLoading.value = false
    return
  }

  try {
    await Promise.all([teamsStore.fetchTeams(props.id), gamesStore.fetchGames(props.id)])
  } finally {
    initialLoading.value = false
  }
  syncOptionsFromGames()
  // Sin calendario guardado se retoma el borrador, antes de vigilar los parámetros para no recalcularlo
  if (!hasCalendar.value) await loadDraft()
  watchOptions()
})
</script>

<style scoped>
/* Debajo de la barra superior (64px), sobre el contenido */
.page-loader {
  position: fixed;
  inset: 64px 0 0 0;
  z-index: 5;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background-color: rgba(248, 250, 252, 0.94);
}

.bg-admin-grey {
  background-color: rgb(var(--v-theme-background)) !important;
}

.border-bottom {
  border-bottom: 1px solid #e2e8f0 !important;
}

.config-tabs {
  border-bottom: 1px solid #e2e8f0;
}

/* Listas del modal de agregar equipos: altura fija con scroll propio */
.team-picker-list {
  height: 360px;
  overflow-y: auto;
}

/* Contenedor estilo lista de archivos de GitHub */
.gh-box {
  background: #ffffff;
  border: 1px solid #d0d7de;
  border-radius: 6px;
  overflow: hidden;
}

.gh-box__header {
  display: flex;
  align-items: center;
  min-height: 48px;
  padding: 8px 16px;
  background: #f6f8fa;
  border-bottom: 1px solid #d0d7de;
  font-size: 0.875rem;
}

.gh-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  padding: 4px 8px 4px 16px;
  font-size: 0.8125rem;
}

.gh-row + .gh-row {
  border-top: 1px solid #eaeef2;
}

.gh-row:hover {
  background: #f6f8fa;
}

.gh-row__name {
  flex: 0 0 35%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gh-row__meta {
  flex: 1 1 auto;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gh-row__date {
  white-space: nowrap;
}

.team-abbr {
  font-size: 0.55rem !important;
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

.admin-data-table :deep(.v-data-table-footer) {
  font-size: 0.8125rem;
}

@media (max-width: 600px) {
  .gh-row__meta,
  .gh-row__date {
    display: none;
  }
}
</style>
