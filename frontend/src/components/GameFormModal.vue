<!-- src/components/GameFormModal.vue -->
<!-- Edita un partido del calendario: equipos que se enfrentan, fecha y hora -->
<template>
  <v-dialog v-model="dialog" max-width="600px" persistent>
    <v-card class="rounded-xl pa-2" theme="light">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <div>
          <div class="text-h6 font-weight-black text-grey-darken-4">Editar partido</div>
          <div v-if="game" class="text-subtitle-1 font-weight-bold text-grey-darken-4">
            {{ game.away_team.name }} <span class="text-grey font-weight-regular">@</span> {{ game.home_team.name }}
          </div>
          <div v-if="game" class="text-body-2 text-grey-darken-1">
            Partido #{{ game.game_number }} · Jornada {{ game.matchday }} · Serie {{ game.series }}, juego {{ game.series_game }}
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeModal" />
      </v-card-title>

      <v-divider class="my-2" />

      <!-- Form -->
      <v-card-text class="pt-2">
        <v-form ref="formRef" @submit.prevent="handleSubmit">
          <v-row density="comfortable">
            <v-col cols="12" sm="6">
              <v-select
                v-model="formData.away_team_id"
                :items="teams"
                item-title="name"
                item-value="id"
                label="Visitante"
                variant="outlined"
                density="comfortable"
                :rules="[v => !!v || 'El visitante es requerido', differentTeams]"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="formData.home_team_id"
                :items="teams"
                item-title="name"
                item-value="id"
                label="Local"
                variant="outlined"
                density="comfortable"
                :rules="[v => !!v || 'El local es requerido', differentTeams]"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="formData.date"
                type="date"
                label="Fecha"
                variant="outlined"
                density="comfortable"
                :rules="[v => !!v || 'La fecha es requerida']"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="formData.time"
                type="time"
                label="Hora"
                variant="outlined"
                density="comfortable"
                :rules="[v => !!v || 'La hora es requerida']"
              />
            </v-col>
          </v-row>
        </v-form>
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
          Cancelar
        </v-btn>
        <v-btn
          color="black"
          variant="flat"
          class="text-capitalize rounded-pill px-5 font-weight-bold"
          :loading="loading"
          @click="handleSubmit"
        >
          Guardar Cambios
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  game: Object,
  // Equipos inscritos en el torneo
  teams: { type: Array, default: () => [] },
  loading: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])

const formRef = ref(null)
const formData = ref({})

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

// scheduled_at llega como 'YYYY-MM-DD HH:mm:ss'. Se carga al abrir para no conservar
// cambios sin guardar si se cancela y se vuelve a abrir el mismo partido
watch(
  () => [props.modelValue, props.game],
  ([open, game]) => {
    if (!open || !game) return
    formRef.value?.resetValidation()
    formData.value = {
      away_team_id: game.away_team_id,
      home_team_id: game.home_team_id,
      date: game.scheduled_at.substring(0, 10),
      time: game.scheduled_at.substring(11, 16)
    }
  },
  { immediate: true }
)

const differentTeams = () => formData.value.away_team_id !== formData.value.home_team_id || 'Visitante y local deben ser distintos'

// Al cambiar un equipo se vuelve a validar el otro
watch(() => [formData.value.away_team_id, formData.value.home_team_id], () => formRef.value?.validate())

const closeModal = () => {
  dialog.value = false
}

const handleSubmit = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  emit('save', {
    away_team_id: formData.value.away_team_id,
    home_team_id: formData.value.home_team_id,
    scheduled_at: `${formData.value.date} ${formData.value.time}`
  })
}
</script>
