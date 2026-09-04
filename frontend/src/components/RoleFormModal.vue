<template>
  <v-dialog v-model="dialog" max-width="550px" persistent>
    <v-card class="rounded-xl pa-2" theme="light">
      <!-- Encabezado del Modal -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <span class="text-h6 font-weight-black text-grey-darken-4">
          {{ isEditing ? 'Editar Rol' : 'Nuevo Rol' }}
        </span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeModal" />
      </v-card-title>

      <v-divider class="my-2" />

      <!-- Formulario -->
      <v-card-text class="pt-2">
        <v-form ref="formRef" v-model="isFormValid" @submit.prevent="handleSubmit">
          <v-container class="pa-0">
            <v-row density="comfortable">
              
              <!-- Nombre del Rol -->
              <v-col cols="12">
                <v-text-field
                  v-model="formData.name"
                  label="Nombre del Rol"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  maxlength="200"
                  counter
                  :rules="[v => !!v || 'El nombre es requerido']"
                />
              </v-col>

              <!-- Estado (is_active) -->
              <v-col cols="12">
                <v-switch
                  v-model="formData.is_active"
                  label="Rol Activo"
                  color="success"
                  hide-details
                  density="compact"
                />
              </v-col>

            </v-row>
          </v-container>
        </v-form>
      </v-card-text>

      <!-- Acciones del Modal -->
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
          {{ isEditing ? 'Guardar Cambios' : 'Crear Rol' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  rolData: Object,
  userData: Object, // Acepta userData por compatibilidad si se le pasa desde el Dashboard
  loading: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])

const formRef = ref(null)
const isFormValid = ref(false)

const defaultForm = {
  id: null,
  name: '',
  is_active: true
}

const formData = ref({ ...defaultForm })

const isEditing = computed(() => !!formData.value.id)

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

// Evalúa tanto rolData como userData para cargar la información recibida
const activeRol = computed(() => props.rolData || props.userData)

watch(
  activeRol,
  (newRol) => {
    if (newRol && Object.keys(newRol).length > 0) {
      formData.value = { 
        id: newRol.id ?? null,
        name: newRol.name ?? '',
        is_active: newRol.is_active ?? true
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