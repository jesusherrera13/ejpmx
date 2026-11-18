<template>
  <v-dialog v-model="dialog" max-width="960px" persistent scrollable>
    <v-card class="rounded-xl blog-modal" theme="ejp">
      <!-- Encabezado fijo -->
      <v-card-title class="d-flex align-center justify-space-between pt-4 pb-3 px-6">
        <div class="d-flex align-center">
          <v-avatar color="primary" variant="tonal" size="36" class="mr-3">
            <v-icon :icon="isVideo ? 'mdi-video-outline' : 'mdi-newspaper-variant-outline'" size="20" />
          </v-avatar>
          <span class="text-h6 font-weight-black text-grey-darken-4">
            {{ isEditing ? 'Editar publicación' : 'Nueva publicación' }}
          </span>
        </div>
        <v-btn icon="mdi-close" variant="text" density="comfortable" :disabled="loading" @click="closeModal" />
      </v-card-title>

      <v-divider />

      <!-- Cuerpo con scroll; encabezado y botones quedan siempre visibles -->
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

        <!-- Primero los tipos de publicación: de ellos dependen el campo de tipo, la portada y el video -->
        <div v-if="!typesReady" class="d-flex flex-column align-center justify-center py-16 text-grey-darken-1">
          <template v-if="contentTypesStore.error">
            <v-icon icon="mdi-alert-circle-outline" color="error" size="36" class="mb-2" />
            <div class="text-body-2 mb-3">{{ contentTypesStore.error }}</div>
            <v-btn variant="outlined" color="primary" class="text-none rounded-pill" @click="loadContentTypes">Reintentar</v-btn>
          </template>
          <template v-else>
            <v-progress-circular indeterminate color="primary" size="40" class="mb-3" />
            <div class="text-body-2">Cargando tipos de publicación…</div>
          </template>
        </div>

        <v-form v-else ref="formRef" @submit.prevent="handleSubmit">
          <v-text-field
            v-model="formData.title"
            label="Título de la publicación"
            placeholder="Cinco interrogantes rumbo a la fecha límite de cambios"
            variant="outlined"
            density="comfortable"
            class="mb-2"
            counter="200"
            maxlength="200"
            :rules="TITLE_RULES"
          />

          <v-row dense class="mb-1">
            <v-col cols="12" sm="7">
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
            <v-col cols="12" sm="5" class="d-flex align-start">
              <v-sheet border rounded="lg" class="d-flex align-center justify-space-between w-100 px-4 publish-box">
                <div>
                  <div class="text-body-2 font-weight-bold">Publicar</div>
                  <div class="text-caption text-grey-darken-1">
                    {{ formData.is_published ? 'Visible en el portal al guardar' : 'Se guarda como borrador' }}
                  </div>
                </div>
                <v-switch v-model="formData.is_published" color="primary" hide-details density="compact" inset />
              </v-sheet>
            </v-col>
          </v-row>

          <!-- Artículo: imagen de portada -->
          <div v-if="isArticle" class="mb-4">
            <v-file-input
              v-model="imageFile"
              accept="image/png, image/jpeg, image/webp"
              label="Imagen de portada (opcional)"
              variant="outlined"
              density="comfortable"
              prepend-icon=""
              prepend-inner-icon="mdi-image-outline"
              show-size
              hint="JPG, PNG o WebP de hasta 10 MB. Se optimiza automáticamente al guardar."
              persistent-hint
              :rules="IMAGE_RULES"
              @update:model-value="handleImageChange"
              @click:clear="clearImage"
            />
            <v-img
              v-if="imagePreview"
              :src="imagePreview"
              height="180"
              cover
              class="rounded-lg border mt-3 bg-grey-lighten-4"
            />
          </div>

          <!-- Video: URL y vista previa -->
          <div v-if="isVideo" class="mb-4">
            <v-text-field
              v-model.lazy="formData.video_url"
              label="URL del video (YouTube, Vimeo, etc.)"
              placeholder="https://www.youtube.com/watch?v=..."
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="mdi-video-outline"
              :rules="VIDEO_RULES"
            />
            <iframe
              v-if="embedUrl"
              :src="embedUrl"
              width="100%"
              height="315"
              frameborder="0"
              class="rounded-lg border"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            />
          </div>

          <div class="text-subtitle-2 font-weight-bold text-grey-darken-3 mb-2">Contenido</div>
          <RichTextEditor
            ref="editorRef"
            v-model="formData.content"
            :error="contentError"
            placeholder="Escribe el contenido de la publicación…"
            @update:model-value="onContentChange"
          />
          <div v-if="contentError" class="text-caption text-error mt-1">El contenido es requerido.</div>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="px-6 py-4 justify-end ga-2">
        <v-btn variant="outlined" color="grey-darken-1" class="text-none rounded-pill px-5" :disabled="loading" @click="closeModal">Cancelar</v-btn>
        <v-btn color="primary" variant="flat" class="text-none rounded-pill px-5 font-weight-bold" :loading="loading" :disabled="!typesReady" @click="handleSubmit">
          {{ isEditing ? 'Guardar cambios' : (formData.is_published ? 'Publicar' : 'Guardar borrador') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useContentTypesStore } from '@/stores/content_types'
import RichTextEditor from '@/components/RichTextEditor.vue'

const contentTypesStore = useContentTypesStore()

const props = defineProps({
  modelValue: Boolean,
  blogData: Object,
  loading: Boolean,
})

const emit = defineEmits(['update:modelValue', 'save'])

// Reglas fuera del render: si se crean en el template, cada render las cambia y revalida los campos
const TITLE_RULES = [v => !!v?.trim() || 'El título es requerido']
const TYPE_RULES = [v => !!v || 'Selecciona un tipo']
const VIDEO_RULES = [v => !!v?.trim() || 'La URL del video es requerida']

// Portada: mismo límite y tipos que valida el backend (BlogController)
const MAX_IMAGE_MB = 10
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const firstFile = (value) => (Array.isArray(value) ? value[0] : value) || null
const imageError = (file) => {
  if (!file) return null
  if (!IMAGE_TYPES.includes(file.type)) return 'Solo se permiten imágenes JPG, PNG o WebP'
  if (file.size > MAX_IMAGE_MB * 1024 * 1024) {
    return `La imagen pesa ${(file.size / 1024 / 1024).toFixed(1)} MB; el máximo es ${MAX_IMAGE_MB} MB`
  }
  return null
}
const IMAGE_RULES = [v => imageError(firstFile(v)) || true]

const formRef = ref(null)
const editorRef = ref(null)
const contentError = ref(false)

const imageFile = ref(null)
const imagePreview = ref(null)

const serverError = ref(null)

const defaultForm = {
  id: null,
  title: '',
  content: '',
  image: null, // Objeto File de la portada
  video_url: '',
  content_type_id: null,
  is_published: false
}

const formData = ref({ ...defaultForm })

const selectedContentType = computed(() => {
  return contentTypesStore.contentTypes.find(t => Number(t.id) === Number(formData.value.content_type_id))
})

const isArticle = computed(() => selectedContentType.value?.slug === 'article')
const isVideo = computed(() => selectedContentType.value?.slug === 'video')

// URL de YouTube a formato embed para la vista previa
const embedUrl = computed(() => {
  const url = formData.value.video_url
  if (!url) return null
  const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})/)
  if (ytMatch && ytMatch[1]) {
    return `https://www.youtube.com/embed/${ytMatch[1]}`
  }
  return url
})

const isEditing = computed(() => !!formData.value.id)

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const onContentChange = (html) => {
  if (contentError.value && html) contentError.value = false
}

onBeforeUnmount(() => {
  if (imagePreview.value && imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value)
  }
})

// El formulario (y el contenido del editor) se muestra hasta tener los tipos de publicación
const typesReady = ref(contentTypesStore.contentTypes.length > 0)

const loadContentTypes = async () => {
  await contentTypesStore.fetchContentTypes()
  typesReady.value = !contentTypesStore.error
}

onMounted(() => {
  if (!typesReady.value) loadContentTypes()
})

watch(
  () => props.blogData,
  (newBlog) => {
    if (newBlog && Object.keys(newBlog).length > 0) {
      formData.value = {
        ...defaultForm,
        ...newBlog,
        image: null,
        content_type_id: newBlog.content_type_id == null ? null : Number(newBlog.content_type_id),
      }
      imagePreview.value = newBlog.image_url || null
    } else {
      formData.value = { ...defaultForm }
      imagePreview.value = null
    }
    imageFile.value = null
    serverError.value = null
    contentError.value = false
  },
  { immediate: true }
)

// Vista previa al elegir una imagen
const handleImageChange = (file) => {
  // Vuetify a veces regresa un arreglo aunque no sea 'multiple'
  const selectedFile = firstFile(file)

  // Si no cumple, se deja en el campo para que se vea el error, pero no se envía ni se previsualiza
  if (selectedFile && imageError(selectedFile)) {
    formData.value.image = null
    if (imagePreview.value && imagePreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(imagePreview.value)
    }
    imagePreview.value = isEditing.value ? props.blogData?.image_url || null : null
    return
  }

  if (selectedFile) {
    if (imagePreview.value && imagePreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(imagePreview.value)
    }
    imagePreview.value = URL.createObjectURL(selectedFile)
    formData.value.image = selectedFile
  } else {
    clearImage()
  }
}

const clearImage = () => {
  imageFile.value = null
  formData.value.image = null
  imagePreview.value = isEditing.value ? props.blogData?.image_url || null : null
}

const closeModal = () => {
  dialog.value = false
  formData.value = { ...defaultForm }
  clearImage()
  contentError.value = false
  if (formRef.value) formRef.value.resetValidation()
}

const handleSubmit = async () => {
  if (!formRef.value) return
  const { valid } = await formRef.value.validate()
  // El editor emite con un retraso corto; se toma el HTML al momento
  if (editorRef.value) formData.value.content = editorRef.value.getHTML()
  contentError.value = editorRef.value ? editorRef.value.isEmpty() : !formData.value.content

  if (!valid || contentError.value) return

  serverError.value = null
  const payload = { ...formData.value }

  emit('save', {
    data: payload,
    isEditing: isEditing.value,
    closeModal,
    onError: (msg) => { serverError.value = msg }
  })
}
</script>

<style scoped>
.blog-modal {
  max-height: 92vh;
}
.publish-box {
  min-height: 48px;
  padding-top: 4px;
  padding-bottom: 4px;
}
</style>
