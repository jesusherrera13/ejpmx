<!-- src/components/TournamentFormModal.vue -->
<template>
  <v-dialog v-model="dialog" max-width="600px" persistent>
    <v-card class="rounded-xl pa-2" theme="light">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <span class="text-h6 font-weight-black text-grey-darken-4">
          {{ isEditing ? 'Editar Torneo' : 'Nuevo Torneo' }}
        </span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeModal" />
      </v-card-title>

      <v-divider class="my-2" />

      <!-- Form -->
      <v-card-text class="pt-2">
        <v-form ref="formRef" v-model="isFormValid" @submit.prevent="handleSubmit">
          <v-container class="pa-0">
            <v-row density="comfortable">

              <!-- Name -->
              <v-col cols="12">
                <v-text-field
                  v-model="formData.name"
                  label="Nombre de Torneo"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  maxlength="150"
                  counter
                  :rules="[v => !!v || 'El nombre es requerido']"
                />
              </v-col>

              <!-- Games per series -->
              <v-col cols="12">
                <v-select
                  v-model="formData.games_per_series"
                  :items="[1, 2, 3]"
                  label="Juegos por serie"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  hint="Se aplica al generar el calendario del torneo"
                  persistent-hint
                />
              </v-col>

              <!-- Is Active -->
              <v-col cols="12">
                <v-switch
                  v-model="formData.is_active"
                  label="Torneo Activo"
                  color="success"
                  hide-details
                  density="compact"
                />
              </v-col>

            </v-row>
          </v-container>
        </v-form>
      </v-card-text>

      <!-- Actions -->
      <v-card-actions class="pa-4 pt-0 justify-end ga-2">
        <v-btn
          variant="outlined"
          color="grey-darken-1"
          class="text-capitalize rounded-pill px-5"
          @click="closeModal"
          :disabled="loading"
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
          {{ isEditing ? 'Guardar Cambios' : 'Crear Torneo' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  tournamentData: Object,
  loading: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])

const formRef = ref(null)
const isFormValid = ref(false)

const defaultForm = {
  id: null,
  name: '',
  games_per_series: 3,
  is_active: true
}

const formData = ref({ ...defaultForm })

const isEditing = computed(() => !!formData.value.id)

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

watch(
  () => props.tournamentData,
  (newTournament) => {
    if (newTournament && Object.keys(newTournament).length > 0) {
      formData.value = {
        id: newTournament.id ?? null,
        name: newTournament.name ?? '',
        games_per_series: newTournament.games_per_series ?? 3,
        is_active: newTournament.is_active ?? true
      }
    } else {
      formData.value = { ...defaultForm }
    }
  },
  { immediate: true, deep: true }
)

const closeModal = () => {
  dialog.value = false
  formData.value = { ...defaultForm }
  if (formRef.value) formRef.value.resetValidation()
}

const handleSubmit = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  const payload = { ...formData.value }
  emit('save', { data: payload, isEditing: isEditing.value, closeModal })
}
</script>
