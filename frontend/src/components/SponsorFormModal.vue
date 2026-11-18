<template>
  <v-dialog v-model="dialog" max-width="720px" persistent scrollable>
    <v-card class="rounded-xl" theme="ejp">
      <v-card-title class="d-flex align-center justify-space-between pt-4 pb-3 px-6">
        <div class="d-flex align-center">
          <v-avatar color="primary" variant="tonal" size="36" class="mr-3">
            <v-icon icon="mdi-handshake-outline" size="20" />
          </v-avatar>
          <span class="text-h6 font-weight-black text-grey-darken-4">
            {{ isEditing ? 'Editar patrocinador' : 'Nuevo patrocinador' }}
          </span>
        </div>
        <v-btn icon="mdi-close" variant="text" density="comfortable" :disabled="loading" @click="closeModal" />
      </v-card-title>

      <v-divider />

      <v-card-text class="px-6 py-5">
        <v-alert
          v-if="serverError"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4 rounded-lg text-caption font-weight-medium"
          closable
          @click:close="serverError = null"
        >
          {{ serverError }}
        </v-alert>

        <v-form ref="formRef" @submit.prevent="handleSubmit">
          <v-row dense>
            <!-- Logo -->
            <v-col cols="12" sm="4" class="d-flex flex-column align-center">
              <v-avatar size="112" rounded="lg" color="grey-lighten-4" class="border mb-2">
                <v-img v-if="logoPreview" :src="logoPreview" contain />
                <v-icon v-else icon="mdi-image-outline" size="40" color="grey" />
              </v-avatar>
              <v-btn size="small" variant="text" color="primary" class="text-none" prepend-icon="mdi-upload" @click="fileInput?.click()">
                {{ logoPreview ? 'Cambiar logo' : 'Subir logo' }}
              </v-btn>
              <v-btn v-if="logoPreview" size="small" variant="text" color="grey-darken-1" class="text-none" @click="removeLogo">
                Quitar
              </v-btn>
              <input ref="fileInput" type="file" accept="image/png, image/jpeg, image/webp" class="d-none" @change="onLogoSelected" />
              <div v-if="logoError" class="text-caption text-error text-center">{{ logoError }}</div>
              <div v-else class="text-caption text-grey-darken-1 text-center">JPG, PNG o WebP, hasta 10 MB</div>
            </v-col>

            <v-col cols="12" sm="8">
              <v-text-field
                v-model="formData.name"
                label="Nombre del patrocinador"
                variant="outlined"
                density="comfortable"
                maxlength="255"
                :rules="NAME_RULES"
              />
              <v-text-field
                v-model="formData.contact_name"
                label="Persona de contacto"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-account-outline"
              />
              <div class="d-flex align-center justify-space-between border rounded-lg px-4 py-1">
                <div>
                  <div class="text-body-2 font-weight-bold">Activo</div>
                  <div class="text-caption text-grey-darken-1">Disponible para asignarle publicidad</div>
                </div>
                <v-switch v-model="formData.is_active" color="primary" hide-details density="compact" inset />
              </div>
            </v-col>

            <v-col cols="12" sm="6">
              <v-text-field
                v-model="formData.email"
                label="Correo"
                type="email"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-email-outline"
                :rules="EMAIL_RULES"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="formData.phone"
                label="Teléfono"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-phone-outline"
                maxlength="30"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="formData.website"
                label="Sitio web"
                placeholder="https://www.ejemplo.com"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-web"
                :rules="URL_RULES"
              />
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="formData.notes"
                label="Notas"
                variant="outlined"
                density="comfortable"
                rows="3"
                auto-grow
                maxlength="2000"
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="px-6 py-4 justify-end ga-2">
        <v-btn variant="outlined" color="grey-darken-1" class="text-none rounded-pill px-5" :disabled="loading" @click="closeModal">Cancelar</v-btn>
        <v-btn color="primary" variant="flat" class="text-none rounded-pill px-5 font-weight-bold" :loading="loading" @click="handleSubmit">
          {{ isEditing ? 'Guardar cambios' : 'Crear patrocinador' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import type { Sponsor } from '@/stores/sponsors'

const props = defineProps<{
  modelValue: boolean
  sponsorData?: Sponsor | null
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  save: [payload: { data: Record<string, unknown>, isEditing: boolean, closeModal: () => void, onError: (msg: string) => void }]
}>()

const MAX_LOGO_MB = 10
const LOGO_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const NAME_RULES = [(v: string) => !!v?.trim() || 'El nombre es requerido']
const EMAIL_RULES = [(v: string) => !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Correo no válido']
const URL_RULES = [(v: string) => !v || /^https?:\/\/\S+\.\S+/i.test(v) || 'Escribe la URL completa (https://...)']

const defaultForm = () => ({
  id: null as number | null,
  name: '',
  contact_name: '',
  email: '',
  phone: '',
  website: '',
  notes: '',
  is_active: true,
  logo: null as File | null,
  remove_logo: false,
})

const formRef = ref()
const fileInput = ref<HTMLInputElement | null>(null)
const formData = ref(defaultForm())
const logoPreview = ref<string | null>(null)
const logoError = ref<string | null>(null)
const serverError = ref<string | null>(null)

const isEditing = computed(() => !!formData.value.id)
const dialog = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const revokePreview = () => {
  if (logoPreview.value?.startsWith('blob:')) URL.revokeObjectURL(logoPreview.value)
}

watch(() => props.sponsorData, (sponsor) => {
  revokePreview()
  if (sponsor?.id) {
    formData.value = {
      ...defaultForm(),
      id: sponsor.id,
      name: sponsor.name || '',
      contact_name: sponsor.contact_name || '',
      email: sponsor.email || '',
      phone: sponsor.phone || '',
      website: sponsor.website || '',
      notes: sponsor.notes || '',
      is_active: sponsor.is_active,
    }
    logoPreview.value = sponsor.logo_url
  } else {
    formData.value = defaultForm()
    logoPreview.value = null
  }
  logoError.value = null
  serverError.value = null
}, { immediate: true })

const onLogoSelected = (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  if (!LOGO_TYPES.includes(file.type)) {
    logoError.value = 'Solo JPG, PNG o WebP'
    return
  }
  if (file.size > MAX_LOGO_MB * 1024 * 1024) {
    logoError.value = `El logo pesa ${(file.size / 1024 / 1024).toFixed(1)} MB; el máximo es ${MAX_LOGO_MB} MB`
    return
  }
  logoError.value = null
  revokePreview()
  logoPreview.value = URL.createObjectURL(file)
  formData.value.logo = file
  formData.value.remove_logo = false
}

const removeLogo = () => {
  revokePreview()
  logoPreview.value = null
  formData.value.logo = null
  formData.value.remove_logo = true
}

const closeModal = () => {
  dialog.value = false
}

const handleSubmit = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  serverError.value = null
  emit('save', {
    data: { ...formData.value },
    isEditing: isEditing.value,
    closeModal,
    onError: (msg: string) => { serverError.value = msg },
  })
}

onBeforeUnmount(revokePreview)
</script>
