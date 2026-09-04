<template>
  <v-dialog v-model="dialog" max-width="600px" persistent>
    <v-card class="rounded-xl pa-2" theme="light">
      
      <!-- Título Modal -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <span class="text-h6 font-weight-black text-grey-darken-4">
          {{ isEditing ? 'Editar' : 'Nuevo' }} {{ moduleName }}
        </span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeModal" />
      </v-card-title>

      <v-divider class="my-2" />

      <!-- Formulario -->
      <v-card-text class="pt-2">
        <v-form ref="formRef" v-model="isFormValid" @submit.prevent="handleSubmit">
          <v-container class="pa-0">
            
            <!-- 1. CAMPOS PARA USUARIOS -->
            <v-row v-if="activeTab === 'usuarios'" density="comfortable">
              <v-col cols="12">
                <v-text-field
                  v-model="formData.username"
                  label="Nombre de Usuario (Username)"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'Username es requerido']"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="formData.first_name" label="Nombre" variant="outlined" density="comfortable" class="rounded-lg" :rules="[v => !!v || 'Requerido']" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field v-model="formData.last_name" label="Apellidos" variant="outlined" density="comfortable" class="rounded-lg" :rules="[v => !!v || 'Requerido']" />
              </v-col>
              <v-col cols="12">
                <v-text-field v-model="formData.email" label="Correo Electrónico" type="email" variant="outlined" density="comfortable" class="rounded-lg" :rules="[v => !!v || 'Requerido']" />
              </v-col>
              <v-col cols="12">
                <v-text-field v-model="formData.password" :label="isEditing ? 'Nueva Contraseña (Opcional)' : 'Contraseña'" type="password" variant="outlined" density="comfortable" class="rounded-lg" :rules="isEditing ? [] : [v => !!v || 'Requerida']" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-switch v-model="formData.is_active" label="Usuario Activo" color="success" hide-details density="compact" />
              </v-col>
            </v-row>

            <!-- 2. CAMPOS PARA PERFILES (Profile: name, is_active) -->
            <v-row v-else-if="activeTab === 'perfiles'" density="comfortable">
              <v-col cols="12">
                <v-text-field
                  v-model="formData.name"
                  label="Nombre del Perfil"
                  maxlength="50"
                  counter
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'El nombre es requerido']"
                />
              </v-col>
              <v-col cols="12">
                <v-switch v-model="formData.is_active" label="Perfil Activo" color="success" hide-details density="compact" />
              </v-col>
            </v-row>

            <!-- 3. CAMPOS PARA EQUIPOS (Team: full_name, short_name, is_active) -->
            <v-row v-else-if="activeTab === 'equipos'" density="comfortable">
              <v-col cols="12">
                <v-text-field
                  v-model="formData.full_name"
                  label="Nombre Completo del Equipo"
                  maxlength="100"
                  counter
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'El nombre es requerido']"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="formData.short_name"
                  label="Nombre Corto / Abreviatura"
                  maxlength="50"
                  counter
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'La abreviatura es requerida']"
                />
              </v-col>
              <v-col cols="12">
                <v-switch v-model="formData.is_active" label="Equipo Activo" color="success" hide-details density="compact" />
              </v-col>
            </v-row>

            <!-- 4. CAMPOS PARA JUGADORES (Player: name, last_name, second_last_name, birthday, curp, is_active) -->
            <v-row v-else-if="activeTab === 'jugadores'" density="comfortable">
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="formData.name"
                  label="Nombre(s)"
                  maxlength="50"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'El nombre es requerido']"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="formData.last_name"
                  label="Primer Apellido"
                  maxlength="50"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'El apellido es requerido']"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="formData.second_last_name"
                  label="Segundo Apellido"
                  maxlength="50"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="formData.curp"
                  label="CURP"
                  maxlength="18"
                  counter
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[
                    v => !!v || 'La CURP es requerida',
                    v => (v && v.length === 18) || 'La CURP debe tener 18 caracteres'
                  ]"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="formData.birthday"
                  label="Fecha de Nacimiento"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'La fecha es requerida']"
                />
              </v-col>
              <v-col cols="12">
                <v-switch v-model="formData.is_active" label="Jugador Activo" color="success" hide-details density="compact" />
              </v-col>
            </v-row>

          </v-container>
        </v-form>
      </v-card-text>

      <!-- Botones de Acción -->
      <v-card-actions class="pa-4 pt-0 justify-end ga-2">
        <v-btn variant="outlined" color="grey-darken-1" class="text-capitalize rounded-pill px-5" @click="closeModal" :disabled="loading">
          Cancelar
        </v-btn>
        <v-btn color="black" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" :loading="loading" @click="handleSubmit">
          {{ isEditing ? 'Guardar Cambios' : 'Crear Registro' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  activeTab: String,
  itemData: Object,
  loading: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])

const formRef = ref(null)
const isFormValid = ref(false)
const formData = ref({})

const isEditing = computed(() => !!(props.itemData && props.itemData.id))

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const moduleName = computed(() => {
  const names = {
    usuarios: 'Usuario',
    perfiles: 'Perfil',
    equipos: 'Equipo',
    jugadores: 'Jugador',
    torneos: 'Torneo',
    blog: 'Noticia'
  }
  return names[props.activeTab] || 'Registro'
})

// Inicializador de estado por defecto según módulo
const getInitialState = (tab) => {
  switch (tab) {
    case 'perfiles':
      return { id: null, name: '', is_active: true }
    case 'equipos':
      return { id: null, full_name: '', short_name: '', is_active: true }
    case 'jugadores':
      return { id: null, name: '', last_name: '', second_last_name: '', birthday: '', curp: '', is_active: true }
    case 'usuarios':
    default:
      return { id: null, username: '', first_name: '', last_name: '', email: '', password: '', is_active: true }
  }
}

watch([() => props.itemData, () => props.activeTab], ([newItem, tab]) => {
  if (newItem) {
    formData.value = { ...newItem }
  } else {
    formData.value = getInitialState(tab)
  }
}, { immediate: true })

const closeModal = () => {
  dialog.value = false
  formData.value = getInitialState(props.activeTab)
  if (formRef.value) formRef.value.resetValidation()
}

const handleSubmit = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  emit('save', {
    data: { ...formData.value },
    isEditing: isEditing.value,
    closeModal
  })
}
</script>