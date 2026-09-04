<!-- src/components/PlayerFormModal.vue -->
<template>
  <v-dialog v-model="dialog" max-width="650px" persistent>
    <v-card class="rounded-xl pa-2" theme="light">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <span class="text-h6 font-weight-black text-grey-darken-4">
          {{ isEditing ? 'Editar Jugador' : 'Nuevo Jugador' }}
        </span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeModal" />
      </v-card-title>

      <v-divider class="my-2" />

      <!-- Form -->
      <v-card-text class="pt-2">
        <v-form ref="formRef" v-model="isFormValid" @submit.prevent="handleSubmit">
          <v-container class="pa-0">
            <v-row density="comfortable">
              
              <!-- First Name -->
              <v-col cols="12" sm="12">
                <v-text-field
                  v-model="formData.first_name"
                  label="Nombre(s)"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  maxlength="100"
                  :rules="[v => !!v || 'El nombre es requerido']"
                />
              </v-col>

              <!-- Last Name -->
              <v-col cols="12" sm="12">
                <v-text-field
                  v-model="formData.last_name"
                  label="Primer Apellido"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  maxlength="100"
                  :rules="[v => !!v || 'El primer apellido es requerido']"
                />
              </v-col>

              <!-- Second Last Name -->
              <v-col cols="12" sm="12">
                <v-text-field
                  v-model="formData.second_last_name"
                  label="Segundo Apellido (Opcional)"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  maxlength="100"
                />
              </v-col>

              <!-- Birthdate -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="formData.birthdate"
                  label="Fecha de Nacimiento"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                />
              </v-col>

              <!-- CURP -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="formData.curp"
                  label="CURP"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg text-uppercase"
                  maxlength="18"
                  counter
                />
              </v-col>

              <!-- Is Active -->
              <v-col cols="12">
                <v-switch
                  v-model="formData.is_active"
                  label="Jugador Activo"
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
          {{ isEditing ? 'Guardar Cambios' : 'Crear Jugador' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  playerData: Object,
  loading: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])

const formRef = ref(null)
const isFormValid = ref(false)

const defaultForm = {
  id: null,
  first_name: '',
  last_name: '',
  second_last_name: '',
  birthdate: '',
  curp: '',
  is_active: true
}

const formData = ref({ ...defaultForm })

const isEditing = computed(() => !!formData.value.id)

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

watch(
  () => props.playerData,
  (newPlayer) => {
    if (newPlayer && Object.keys(newPlayer).length > 0) {
      formData.value = { 
        id: newPlayer.id ?? null,
        first_name: newPlayer.first_name ?? '',
        last_name: newPlayer.last_name ?? '',
        second_last_name: newPlayer.second_last_name ?? '',
        birthdate: newPlayer.birthdate ?? '',
        // Forzamos la CURP a mayúsculas para cumplir con el estándar
        curp: newPlayer.curp ? newPlayer.curp.toUpperCase() : '',
        is_active: newPlayer.is_active ?? true
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
  // Nos aseguramos que la curp se envíe siempre en mayúsculas
  if (formData.value.curp) {
    formData.value.curp = formData.value.curp.toUpperCase();
  }

  const { valid } = await formRef.value.validate()
  if (!valid) return

  // Creamos una copia del payload
  const payload = { ...formData.value }

  // LIMPIEZA DE CAMPOS OPCIONALES
  // Si la fecha de nacimiento viene vacía, la mandamos como null
  if (payload.birthdate === "") {
    payload.birthdate = null;
  }
  
  // Como buena práctica, hacemos lo mismo para curp y second_last_name 
  // si también son opcionales y los dejas vacíos
  if (payload.curp === "") {
    payload.curp = null;
  }
  if (payload.second_last_name === "") {
    payload.second_last_name = null;
  }

  emit('save', { data: payload, isEditing: isEditing.value, closeModal })
}
</script>