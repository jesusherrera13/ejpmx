<template>
  <v-dialog v-model="dialog" max-width="550px" persistent>
    <v-card class="rounded-xl pa-2" theme="light">
      <!-- Encabezado del Modal -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <span class="text-h6 font-weight-black text-grey-darken-4">
          {{ isEditing ? 'Editar Usuario' : 'Nuevo Usuario' }}
        </span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeModal" />
      </v-card-title>

      <v-divider class="my-2" />

      <!-- Formulario -->
      <v-card-text class="pt-2">
        <v-form ref="formRef" v-model="isFormValid" @submit.prevent="handleSubmit">
          <v-container class="pa-0">
            <v-row density="comfortable">

              <!-- Nombre -->
              <v-col cols="12">
                <v-text-field
                  v-model="formData.name"
                  label="Nombre"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'El nombre es requerido']"
                />
              </v-col>

              <!-- Email -->
              <v-col cols="12">
                <v-text-field
                  v-model="formData.email"
                  label="Correo Electrónico"
                  type="email"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[
                    v => !!v || 'El correo es requerido',
                    v => /.+@.+\..+/.test(v) || 'Correo no válido'
                  ]"
                />
              </v-col>

              <!-- NUEVO: Asignación de Rol -->
              <v-col cols="12">
                <v-select
                  v-model="formData.role_id"
                  :items="rolesStore.roles"
                  item-title="name"
                  item-value="id"
                  label="Asignar Rol"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :loading="rolesStore.loading"
                  :rules="[v => !!v || 'Debes seleccionar un rol para este usuario']"
                  clearable
                />
              </v-col>

              <!-- Contraseña (Solo obligatoria al Crear) -->
              <v-col cols="12">
                <v-text-field
                  v-model="formData.password"
                  :label="isEditing ? 'Nueva Contraseña (Opcional)' : 'Contraseña'"
                  type="password"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="isEditing ? [] : [v => !!v || 'La contraseña es requerida']"
                  :hint="isEditing ? 'Déjala en blanco para mantener la actual' : ''"
                  persistent-hint
                />
              </v-col>

              <!-- Permisos y Roles (Switch) -->
              <v-col cols="12" sm="6">
                <v-switch
                  v-model="formData.is_active"
                  label="Usuario Activo"
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
          {{ isEditing ? 'Guardar Cambios' : 'Crear Usuario' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
// Ajusta la ruta correcta a donde tienes tu store de Pinia
import { useRolesStore } from '@/stores/roles' 

const props = defineProps({
  modelValue: Boolean,
  userData: Object,
  loading: Boolean
})

const emit = defineEmits(['update:modelValue', 'save'])

// Inicializamos el store de roles
const rolesStore = useRolesStore()

const formRef = ref(null)
const isFormValid = ref(false)

// Agregado role_id al objeto por defecto
// 4: Invitado

const defaultForm = {
  id: null,
  username: '',
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  role_id: 4, 
  is_active: true,
  is_superuser: false
}

const formData = ref({ ...defaultForm })

const isEditing = computed(() => !!formData.value.id)

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

// Cargamos los roles al momento de que el modal (o el componente padre) se monta
onMounted(() => {
  rolesStore.fetchRoles()
})

watch(
  () => props.userData,
  (newUser) => {
    if (newUser && Object.keys(newUser).length > 0) {

      // Extraemos el role_id basado en los datos devueltos por el backend
      const assignedRolId = newUser.roles?.length ? newUser.roles[0].id : 4;

      formData.value = { 
        ...newUser, 
        role_id: assignedRolId,
        password: ''
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
  
  // Si estamos editando y no se escribió contraseña, la borramos del payload
  if (isEditing.value && !payload.password) {
    delete payload.password
  }

  emit('save', { data: payload, isEditing: isEditing.value, closeModal })
}
</script>