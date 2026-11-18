<template>
  <v-dialog v-model="dialog" max-width="960px" persistent scrollable>
    <v-card class="rounded-xl ad-modal" theme="ejp">
      <v-card-title class="d-flex align-center justify-space-between pt-4 pb-3 px-6">
        <div class="d-flex align-center">
          <v-avatar color="primary" variant="tonal" size="36" class="mr-3">
            <v-icon icon="mdi-bullhorn-outline" size="20" />
          </v-avatar>
          <span class="text-h6 font-weight-black text-grey-darken-4">
            {{ isEditing ? 'Editar publicidad' : 'Nueva publicidad' }}
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

        <!-- Primero los catálogos: tipos de publicación y patrocinadores -->
        <div v-if="!catalogsReady" class="d-flex flex-column align-center justify-center py-16 text-grey-darken-1">
          <v-progress-circular indeterminate color="primary" size="40" class="mb-3" />
          <div class="text-body-2">Cargando catálogos…</div>
        </div>

        <v-form v-else ref="formRef" @submit.prevent="handleSubmit">
          <v-text-field
            v-model="formData.title"
            label="Título de la publicidad"
            variant="outlined"
            density="comfortable"
            class="mb-1"
            counter="255"
            maxlength="255"
            :rules="TITLE_RULES"
          />

          <v-row dense>
            <v-col cols="12" sm="6">
              <v-autocomplete
                v-model="formData.sponsor_id"
                :items="sponsorItems"
                item-title="name"
                item-value="id"
                label="Patrocinador"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-handshake-outline"
                no-data-text="No hay patrocinadores activos. Regístralos en Patrocinadores."
                :rules="SPONSOR_RULES"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="formData.content_type_id"
                :items="contentTypesStore.contentTypes"
                item-title="name"
                item-value="id"
                label="Tipo de publicación"
                variant="outlined"
                density="comfortable"
                :rules="TYPE_RULES"
              />
            </v-col>
          </v-row>

          <!-- Vigencia -->
          <v-sheet border rounded="lg" class="pa-4 mb-4">
            <div class="d-flex align-center justify-space-between mb-2">
              <div>
                <div class="text-body-2 font-weight-bold">Vigencia</div>
                <div class="text-caption text-grey-darken-1">
                  {{ formData.is_permanent ? 'Se muestra siempre, sin fecha de inicio ni de fin' : 'Se muestra del inicio al fin (fechas incluidas); sin fin, sigue vigente' }}
                </div>
              </div>
              <v-switch v-model="formData.is_permanent" label="Permanente" color="primary" hide-details density="compact" inset />
            </div>
            <v-row v-if="!formData.is_permanent" dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="formData.starts_at"
                  type="date"
                  label="Fecha de inicio"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-start"
                  hide-details="auto"
                  :rules="START_RULES"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="formData.ends_at"
                  type="date"
                  label="Fecha de fin (opcional)"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-end"
                  :min="formData.starts_at || undefined"
                  hide-details="auto"
                  :rules="endRules"
                />
              </v-col>
            </v-row>
          </v-sheet>

          <!-- Imagen (artículo) o video -->
          <div v-if="isVideo" class="mb-2">
            <v-text-field
              v-model.lazy="formData.video_url"
              label="URL del video (YouTube, Vimeo, etc.)"
              placeholder="https://www.youtube.com/watch?v=..."
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="mdi-video-outline"
              :rules="VIDEO_RULES"
            />
            <div v-if="videoEmbed" class="video-wrapper rounded-lg overflow-hidden border mb-4">
              <iframe :src="videoEmbed" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" />
            </div>
          </div>
          <div v-else class="mb-4">
            <v-file-input
              v-model="imageFile"
              accept="image/png, image/jpeg, image/webp"
              label="Imagen de la publicidad"
              variant="outlined"
              density="comfortable"
              prepend-icon=""
              prepend-inner-icon="mdi-image-outline"
              show-size
              hint="Formato vertical 4:5 (p. ej. 1080×1350). JPG, PNG o WebP de hasta 10 MB."
              persistent-hint
              :rules="IMAGE_RULES"
              @update:model-value="handleImageChange"
              @click:clear="clearImage"
            />
            <!-- Vista previa tal como se guardará: vertical 4:5, recortada al centro -->
            <div v-if="imagePreview" class="d-flex flex-column align-center mt-3">
              <v-img :src="imagePreview" :aspect-ratio="0.8" cover width="220" class="rounded-lg border bg-grey-lighten-4 flex-grow-0" />
              <v-alert
                v-if="orientationWarning"
                type="warning"
                variant="tonal"
                density="compact"
                class="mt-3 text-caption rounded-lg w-100"
              >
                {{ orientationWarning }}
              </v-alert>
            </div>
          </div>

          <v-text-field
            v-model="formData.link_url"
            label="Enlace al hacer clic (opcional)"
            placeholder="https://www.patrocinador.com/promocion"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-link-variant"
            :rules="URL_RULES"
          />

          <div class="d-flex align-center justify-space-between border rounded-lg px-4 py-1 mb-4">
            <div>
              <div class="text-body-2 font-weight-bold">Activa</div>
              <div class="text-caption text-grey-darken-1">Desactívala para pausarla sin borrar sus fechas</div>
            </div>
            <v-switch v-model="formData.is_active" color="primary" hide-details density="compact" inset />
          </div>

          <div class="text-subtitle-2 font-weight-bold text-grey-darken-3 mb-2">Texto (opcional)</div>
          <RichTextEditor
            ref="editorRef"
            v-model="formData.content"
            placeholder="Descripción o mensaje de la publicidad…"
            min-height="160px"
            max-height="35vh"
          />
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="px-6 py-4 justify-end ga-2">
        <v-btn variant="outlined" color="grey-darken-1" class="text-none rounded-pill px-5" :disabled="loading" @click="closeModal">Cancelar</v-btn>
        <v-btn color="primary" variant="flat" class="text-none rounded-pill px-5 font-weight-bold" :loading="loading" :disabled="!catalogsReady" @click="handleSubmit">
          {{ isEditing ? 'Guardar cambios' : 'Crear publicidad' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import RichTextEditor from '@/components/RichTextEditor.vue'
// @ts-expect-error el store de tipos de publicación está en JavaScript (sin tipos)
import { useContentTypesStore } from '@/stores/content_types'
import { useSponsorsStore } from '@/stores/sponsors'
import type { Advertising } from '@/stores/advertising'
import { embedUrl } from '@/utils/video'

const props = defineProps<{
  modelValue: boolean
  adData?: Advertising | null
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  save: [payload: { data: Record<string, unknown>, isEditing: boolean, closeModal: () => void, onError: (msg: string) => void }]
}>()

const contentTypesStore = useContentTypesStore()
const sponsorsStore = useSponsorsStore()

const MAX_IMAGE_MB = 10
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const firstFile = (value: unknown): File | null => (Array.isArray(value) ? value[0] : value as File) || null
const imageError = (file: File | null) => {
  if (!file) return null
  if (!IMAGE_TYPES.includes(file.type)) return 'Solo se permiten imágenes JPG, PNG o WebP'
  if (file.size > MAX_IMAGE_MB * 1024 * 1024) return `La imagen pesa ${(file.size / 1024 / 1024).toFixed(1)} MB; el máximo es ${MAX_IMAGE_MB} MB`
  return null
}

const TITLE_RULES = [(v: string) => !!v?.trim() || 'El título es requerido']
const SPONSOR_RULES = [(v: number | null) => !!v || 'Selecciona un patrocinador']
const TYPE_RULES = [(v: number | null) => !!v || 'Selecciona un tipo']
const START_RULES = [(v: string) => !!v || 'Indica la fecha de inicio']
const VIDEO_RULES = [(v: string) => !!v?.trim() || 'La URL del video es requerida']
const URL_RULES = [(v: string) => !v || /^https?:\/\/\S+\.\S+/i.test(v) || 'Escribe la URL completa (https://...)']
const IMAGE_RULES = [(v: unknown) => imageError(firstFile(v)) || true]

const defaultForm = () => ({
  id: null as number | null,
  title: '',
  content: '',
  content_type_id: null as number | null,
  sponsor_id: null as number | null,
  video_url: '',
  link_url: '',
  starts_at: new Date().toISOString().slice(0, 10) as string | null,
  ends_at: null as string | null,
  is_permanent: false,
  is_active: true,
  image: null as File | null,
})

const formRef = ref()
const editorRef = ref()
const formData = ref(defaultForm())
const imageFile = ref<File | File[] | null>(null)
const imagePreview = ref<string | null>(null)
const serverError = ref<string | null>(null)
const catalogsReady = ref(contentTypesStore.contentTypes.length > 0 && sponsorsStore.sponsors.length > 0)

const isEditing = computed(() => !!formData.value.id)
const dialog = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const selectedType = computed(() => contentTypesStore.contentTypes.find(
  (t: { id: number }) => Number(t.id) === Number(formData.value.content_type_id)
) as { slug?: string } | undefined)
const isVideo = computed(() => selectedType.value?.slug === 'video')
const videoEmbed = computed(() => embedUrl(formData.value.video_url))

// Patrocinadores activos, más el de la publicidad que se edita aunque ya esté inactivo
const sponsorItems = computed(() => sponsorsStore.sponsors.filter(
  s => s.is_active || s.id === formData.value.sponsor_id
))

const endRules = computed(() => [
  (v: string) => !v || !formData.value.starts_at || v >= formData.value.starts_at || 'El fin no puede ser antes del inicio',
])

onMounted(async () => {
  if (!catalogsReady.value) {
    await Promise.all([
      contentTypesStore.contentTypes.length ? null : contentTypesStore.fetchContentTypes(),
      sponsorsStore.sponsors.length ? null : sponsorsStore.fetchSponsors(),
    ])
    catalogsReady.value = true
  }
})

const revokePreview = () => {
  if (imagePreview.value?.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value)
}

watch(() => props.adData, (ad) => {
  revokePreview()
  if (ad?.id) {
    formData.value = {
      ...defaultForm(),
      id: ad.id,
      title: ad.title,
      content: ad.content || '',
      content_type_id: Number(ad.content_type_id),
      sponsor_id: Number(ad.sponsor_id),
      video_url: ad.video_url || '',
      link_url: ad.link_url || '',
      starts_at: ad.starts_at ? ad.starts_at.slice(0, 10) : null,
      ends_at: ad.ends_at ? ad.ends_at.slice(0, 10) : null,
      is_permanent: ad.is_permanent,
      is_active: ad.is_active,
    }
    imagePreview.value = ad.image_url
  } else {
    formData.value = defaultForm()
    imagePreview.value = null
  }
  imageFile.value = null
  serverError.value = null
}, { immediate: true })

// Aviso cuando la foto no es vertical: el servidor la recorta al centro a 4:5
const orientationWarning = ref<string | null>(null)
const checkOrientation = (src: string) => {
  orientationWarning.value = null
  const img = new Image()
  img.onload = () => {
    const ratio = img.naturalWidth / img.naturalHeight
    if (ratio >= 1) {
      orientationWarning.value = 'La foto es horizontal o cuadrada: se recortará al centro para dejarla vertical. Para mejor resultado usa una foto vertical.'
    } else if (Math.abs(ratio - 0.8) > 0.12) {
      orientationWarning.value = 'La foto no es 4:5: se recortará un poco al centro, como se ve en la vista previa.'
    }
  }
  img.src = src
}

const handleImageChange = (value: unknown) => {
  const file = firstFile(value)
  revokePreview()
  orientationWarning.value = null
  if (file && !imageError(file)) {
    imagePreview.value = URL.createObjectURL(file)
    formData.value.image = file
    checkOrientation(imagePreview.value)
  } else {
    formData.value.image = null
    imagePreview.value = isEditing.value ? props.adData?.image_url || null : null
  }
}

const clearImage = () => {
  revokePreview()
  orientationWarning.value = null
  imageFile.value = null
  formData.value.image = null
  imagePreview.value = isEditing.value ? props.adData?.image_url || null : null
}

const closeModal = () => {
  dialog.value = false
}

const handleSubmit = async () => {
  if (!formRef.value) return
  const { valid } = await formRef.value.validate()
  if (editorRef.value) formData.value.content = editorRef.value.getHTML()
  if (!valid) return

  serverError.value = null
  const data: Record<string, unknown> = { ...formData.value }
  if (data.is_permanent) {
    data.starts_at = null
    data.ends_at = null
  }
  emit('save', {
    data,
    isEditing: isEditing.value,
    closeModal,
    onError: (msg: string) => { serverError.value = msg },
  })
}

onBeforeUnmount(revokePreview)
</script>

<style scoped>
.ad-modal {
  max-height: 92vh;
}
.video-wrapper {
  position: relative;
  padding-top: 56.25%;
  background: #000;
}
.video-wrapper iframe {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
</style>
