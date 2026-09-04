<template>
  <v-dialog v-model="dialog" max-width="800px" persistent>
    <v-card class="rounded-xl pa-2" theme="light">
      <v-card-title class="d-flex align-center justify-space-between pt-4 px-4">
        <span class="text-h6 font-weight-black text-grey-darken-4">
          {{ isEditing ? 'Editar Publicación' : 'Nueva Publicación' }}
        </span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeModal" />
      </v-card-title>

      <v-divider class="my-2" />

      <v-card-text class="pt-2">
        <!-- NUEVO: Alerta de errores del servidor -->
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
        <v-form ref="formRef" v-model="isFormValid" @submit.prevent="handleSubmit">
          <v-container class="pa-0">
            <v-row density="comfortable">

              <v-col cols="12" md="8">
                <v-text-field
                  v-model="formData.title"
                  label="Título de la publicación"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'El título es requerido']"
                />
              </v-col>

              <v-col cols="12" md="4" class="d-flex align-center">
                <v-switch
                  v-model="formData.is_published"
                  label="Publicar inmediatamente"
                  color="success"
                  hide-details
                  density="compact"
                />
              </v-col>

              <v-col cols="12">
                <v-select
                  v-model="formData.content_type_id"
                  :items="contentTypesStore.contentTypes" 
                  item-title="name"
                  item-value="id"
                  label="Tipo de Publicación"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  :rules="[v => !!v || 'Selecciona un tipo']"
                />
              </v-col>

              <!-- NUEVO: Renderizado Condicional para Artículo (Imagen) -->
              <v-col cols="12" v-if="isArticle">
                <v-file-input
                  v-model="imageFile"
                  accept="image/png, image/jpeg, image/webp"
                  label="Imagen de portada (Opcional)"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  prepend-inner-icon="mdi-camera"
                  show-size
                  @update:model-value="handleImageChange"
                  @click:clear="clearImage"
                />
                <v-expand-transition>
                  <div v-if="imagePreview" class="mt-2 text-center">
                    <p class="text-caption text-grey-darken-1 mb-1 text-left">Vista previa:</p>
                    <v-img :src="imagePreview" max-height="250" class="rounded-lg border bg-grey-lighten-4" cover />
                  </div>
                </v-expand-transition>
              </v-col>

              <!-- NUEVO: Renderizado Condicional para Video (URL) -->
              <v-col cols="12" v-if="isVideo">
                <v-text-field
                  v-model="formData.video_url"
                  label="URL del Video (YouTube, Vimeo, etc.)"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg"
                  prepend-inner-icon="mdi-video"
                  :rules="[v => !!v || 'La URL del video es requerida']"
                />
                <!-- Vista previa de Video simple (Iframe) -->
                <v-expand-transition>
                  <div v-if="embedUrl" class="mt-2 text-center">
                    <p class="text-caption text-grey-darken-1 mb-1 text-left">Vista previa del video:</p>
                    <iframe 
                      :src="embedUrl" 
                      width="100%" 
                      height="315" 
                      frameborder="0" 
                      class="rounded-lg border"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                      allowfullscreen>
                    </iframe>
                  </div>
                </v-expand-transition>
              </v-col>

              <!-- Contenido (Editor Tiptap) -->
              <v-col cols="12">
                <p class="text-subtitle-2 mb-2 text-grey-darken-2">Contenido</p>
                <div :class="['editor-container', { 'border-error': contentError }]">
                  <div v-if="editor" class="editor-toolbar bg-grey-lighten-4 px-2 py-1 d-flex flex-wrap ga-1 border-b">
                    <!-- Botones de Tiptap (Mantenidos igual) -->
                    <v-btn icon="mdi-format-bold" size="small" variant="text" :color="editor.isActive('bold') ? 'primary' : 'grey-darken-2'" @click="editor.chain().focus().toggleBold().run()" />
                    <v-btn icon="mdi-format-italic" size="small" variant="text" :color="editor.isActive('italic') ? 'primary' : 'grey-darken-2'" @click="editor.chain().focus().toggleItalic().run()" />
                    <v-btn icon="mdi-format-strikethrough" size="small" variant="text" :color="editor.isActive('strike') ? 'primary' : 'grey-darken-2'" @click="editor.chain().focus().toggleStrike().run()" />
                    <v-divider vertical class="mx-1 my-2" />
                    <v-btn icon="mdi-format-header-1" size="small" variant="text" :color="editor.isActive('heading', { level: 1 }) ? 'primary' : 'grey-darken-2'" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" />
                    <v-btn icon="mdi-format-header-2" size="small" variant="text" :color="editor.isActive('heading', { level: 2 }) ? 'primary' : 'grey-darken-2'" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" />
                    <v-divider vertical class="mx-1 my-2" />
                    <v-btn icon="mdi-format-list-bulleted" size="small" variant="text" :color="editor.isActive('bulletList') ? 'primary' : 'grey-darken-2'" @click="editor.chain().focus().toggleBulletList().run()" />
                    <v-btn icon="mdi-format-list-numbered" size="small" variant="text" :color="editor.isActive('orderedList') ? 'primary' : 'grey-darken-2'" @click="editor.chain().focus().toggleOrderedList().run()" />
                    <v-divider vertical class="mx-1 my-2" />
                    <v-btn icon="mdi-format-clear" size="small" variant="text" color="grey-darken-2" @click="editor.chain().focus().clearNodes().unsetAllMarks().run()" />
                  </div>
                  <editor-content :editor="editor" class="editor-content-area pa-3" />
                </div>
                <div v-if="contentError" class="text-caption text-error mt-1">El contenido es requerido.</div>
              </v-col>

            </v-row>
          </v-container>
        </v-form>
      </v-card-text>

      <v-card-actions class="pa-4 pt-0 justify-end ga-2">
        <v-btn variant="outlined" color="grey-darken-1" class="text-capitalize rounded-pill px-5" @click="closeModal" :disabled="loading">Cancelar</v-btn>
        <v-btn color="black" variant="flat" class="text-capitalize rounded-pill px-5 font-weight-bold" :loading="loading" @click="handleSubmit">{{ isEditing ? 'Guardar Cambios' : 'Crear Publicación' }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { useContentTypesStore } from '@/stores/content_types'

const contentTypesStore = useContentTypesStore()

const props = defineProps({
  modelValue: Boolean,
  blogData: Object,
  loading: Boolean,
})

const emit = defineEmits(['update:modelValue', 'save'])

const formRef = ref(null)
const isFormValid = ref(false)
const contentError = ref(false)

// Nuevas variables para la gestión de archivos
const imageFile = ref(null)
const imagePreview = ref(null)

const serverError = ref(null)

const defaultForm = {
  id: null,
  title: '',
  content: '',
  image: null, // Ahora guardará el objeto File
  video_url: '', // NUEVO
  content_type_id: null, // NUEVO
  is_published: false
}

const formData = ref({ ...defaultForm })

const selectedContentType = computed(() => {
  return contentTypesStore.contentTypes.find(t => t.id === formData.value.content_type_id)
})

const isArticle = computed(() => selectedContentType.value?.slug === 'article')
const isVideo = computed(() => selectedContentType.value?.slug === 'video')

// Convertir URL normal a Embed para vista previa (Ejemplo con YouTube)
const embedUrl = computed(() => {
  const url = formData.value.video_url
  if (!url) return null
  
  // Transformación básica para YouTube
  const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})/)
  if (ytMatch && ytMatch[1]) {
    return `https://www.youtube.com/embed/${ytMatch[1]}`
  }
  return url // Fallback directo si es otro origen ya preparado
})

const isEditing = computed(() => !!formData.value.id)

const dialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const editor = useEditor({
  extensions: [StarterKit],
  // Inicializamos con el valor actual, no solo con el default
  content: formData.value.content, 
  onUpdate: ({ editor }) => {
    formData.value.content = editor.getHTML()
    const plainText = editor.getText().trim()
    if (plainText.length > 0) contentError.value = false
  }
})

onBeforeUnmount(() => {
  if (editor.value) editor.value.destroy()
  // Limpiar memoria de la URL temporal
  if (imagePreview.value && imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value)
  }
})

const loadModuleData = async (tabId) => {
  await contentTypesStore.fetchContentTypes()
}

onMounted(() => {
  loadModuleData()
})

watch(
  () => props.blogData,
  async (newBlog) => {
    if (newBlog && Object.keys(newBlog).length > 0) {
      formData.value = { ...newBlog, image: null }
      imagePreview.value = newBlog.image_url || null
      imageFile.value = null
      serverError.value = null
    } else {
      formData.value = { ...defaultForm }
      imagePreview.value = null
      imageFile.value = null
    }
    
    // Esperamos un tick del framework antes de intentar inyectar el contenido
    await nextTick()
    if (editor.value) {
      // Usamos el contenido recién asignado a formData
      editor.value.commands.setContent(formData.value.content || '')
    }
    contentError.value = false
  },
  { immediate: true, deep: true }
)

// Generar vista previa cuando se selecciona una imagen
const handleImageChange = (file) => {
  // Vuetify 3 a veces retorna un arreglo incluso sin el prop 'multiple'
  const selectedFile = Array.isArray(file) ? file[0] : file

  if (selectedFile) {
    if (imagePreview.value && imagePreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(imagePreview.value) // Limpiar URL anterior
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
  // Corregido: Usar image_url
  imagePreview.value = isEditing.value ? props.blogData.image_url : null 
}

const closeModal = () => {
  dialog.value = false
  formData.value = { ...defaultForm }
  clearImage()
  contentError.value = false
  if (editor.value) editor.value.commands.setContent('')
  if (formRef.value) formRef.value.resetValidation()
}

const handleSubmit = async () => {
  const { valid } = await formRef.value.validate()
  const plainText = editor.value ? editor.value.getText().trim() : ''
  contentError.value = plainText.length === 0

  if (!valid || contentError.value) return

  serverError.value = null // Reiniciar antes de enviar
  const payload = { ...formData.value }
  
  emit('save', { 
    data: payload, 
    isEditing: isEditing.value, 
    closeModal,
    onError: (msg) => { serverError.value = msg } // Función que recibirá el error
  })
}
</script>

<style scoped>
.editor-container {
  border: 1px solid #9e9e9e;
  border-radius: 8px;
  overflow: hidden;
  transition: border-color 0.3s;
}
.editor-container:hover {
  border-color: #212121;
}
.editor-container.border-error {
  border-color: rgb(var(--v-theme-error));
}
.editor-content-area {
  min-height: 250px;
  max-height: 400px;
  overflow-y: auto;
  cursor: text;
}
.editor-content-area :deep(.tiptap) { outline: none; }
.editor-content-area :deep(.tiptap p) { margin-bottom: 0.5rem; }
.editor-content-area :deep(.tiptap h1),
.editor-content-area :deep(.tiptap h2),
.editor-content-area :deep(.tiptap h3) {
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  line-height: 1.2;
}
.editor-content-area :deep(.tiptap ul),
.editor-content-area :deep(.tiptap ol) {
  padding-left: 1.5rem;
  margin-bottom: 0.5rem;
}
.editor-content-area :deep(.tiptap blockquote) {
  border-left: 3px solid #e0e0e0;
  padding-left: 1rem;
  color: #616161;
  font-style: italic;
}
</style>