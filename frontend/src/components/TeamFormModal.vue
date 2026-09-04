<!-- src/components/TeamFormModal.vue -->
<template>
  <v-dialog v-model="dialog" max-width="600px" persistent>
    <v-card class="rounded-xl pa-2" theme="light">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <span class="text-h6 font-weight-black text-grey-darken-4">
          {{ isEditing ? 'Editar Equipo' : 'Nuevo Equipo' }}
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
                  label="Nombre del Equipo"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  maxlength="150"
                  counter
                  :rules="[v => !!v || 'El nombre es requerido']"
                />
              </v-col>

              <!-- Short Name -->
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="formData.short_name"
                  label="Nombre Corto (Ej: Atléticos)"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  maxlength="100"
                  :rules="[v => !!v || 'El nombre corto es requerido']"
                />
              </v-col>

              <!-- Abbreviation -->
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="formData.abbreviation"
                  label="Abreviación (Ej: OAK)"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  maxlength="10"
                  :rules="[v => !!v || 'Abreviación requerida']"
                />
              </v-col>

              <!-- Is Active -->
              <v-col cols="12">
                <v-switch
                  v-model="formData.is_active"
                  label="Equipo Activo"
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
          {{ isEditing ? 'Guardar Cambios' : 'Crear Equipo' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  teamData: Object,
  loading: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])

const formRef = ref(null)
const isFormValid = ref(false)

const defaultForm = {
  id: null,
  name: '',
  short_name: '',
  abbreviation: '',
  is_active: true
}

const formData = ref({ ...defaultForm })

const isEditing = computed(() => !!formData.value.id)

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

watch(
  () => props.teamData,
  (newTeam) => {
    if (newTeam && Object.keys(newTeam).length > 0) {
      formData.value = { 
        id: newTeam.id ?? null,
        name: newTeam.name ?? '',
        short_name: newTeam.short_name ?? '',
        abbreviation: newTeam.abbreviation ?? '',
        is_active: newTeam.is_active ?? true
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