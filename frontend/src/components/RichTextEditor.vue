<template>
  <div :class="['rte', { 'rte--error': error, 'rte--focused': focused }]">
    <!-- Barra de herramientas: los botones no quitan el foco al editor (mousedown.prevent) -->
    <div class="rte-toolbar d-flex flex-wrap align-center ga-1 px-2 py-1">
      <template v-for="(group, gi) in TOOL_GROUPS" :key="gi">
        <v-divider v-if="gi > 0" vertical class="mx-1 my-2" />
        <v-btn
          v-for="tool in group"
          :key="tool.id"
          :icon="tool.icon"
          :title="tool.title"
          size="small"
          density="comfortable"
          variant="text"
          :color="active[tool.id] ? 'primary' : 'grey-darken-2'"
          :disabled="tool.id === 'undo' ? !canUndo : tool.id === 'redo' ? !canRedo : false"
          @mousedown.prevent
          @click="runTool(tool.id)"
        />
      </template>
    </div>

    <!-- Tiptap se monta directo en este div, fuera de la reactividad de Vue -->
    <div ref="editorEl" class="rte-content" :style="{ minHeight, maxHeight }" @click="focusEditor" />

    <div class="rte-footer d-flex justify-end px-3 py-1 text-caption text-grey-darken-1">
      {{ words }} {{ words === 1 ? 'palabra' : 'palabras' }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref, shallowRef, watch } from 'vue'
import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import { CharacterCount, Placeholder } from '@tiptap/extensions'

/**
 * Editor de texto enriquecido (Tiptap) con v-model en HTML.
 * El editor no vive en el estado reactivo: al escribir solo se actualizan los botones activos
 * y el contador, y el HTML se emite con un retraso corto, así el formulario que lo contiene
 * no se vuelve a dibujar en cada tecla.
 */
const props = withDefaults(defineProps<{
  modelValue?: string
  placeholder?: string
  error?: boolean
  minHeight?: string
  maxHeight?: string
}>(), {
  modelValue: '',
  placeholder: 'Escribe aquí…',
  error: false,
  minHeight: '320px',
  maxHeight: '55vh',
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

type ToolId =
  | 'bold' | 'italic' | 'underline' | 'strike'
  | 'h1' | 'h2'
  | 'bulletList' | 'orderedList' | 'blockquote'
  | 'link'
  | 'undo' | 'redo' | 'clear'

const TOOL_GROUPS: { id: ToolId, icon: string, title: string }[][] = [
  [
    { id: 'bold', icon: 'mdi-format-bold', title: 'Negritas (Ctrl+B)' },
    { id: 'italic', icon: 'mdi-format-italic', title: 'Cursiva (Ctrl+I)' },
    { id: 'underline', icon: 'mdi-format-underline', title: 'Subrayado (Ctrl+U)' },
    { id: 'strike', icon: 'mdi-format-strikethrough', title: 'Tachado' },
  ],
  [
    { id: 'h1', icon: 'mdi-format-header-1', title: 'Título 1' },
    { id: 'h2', icon: 'mdi-format-header-2', title: 'Título 2' },
  ],
  [
    { id: 'bulletList', icon: 'mdi-format-list-bulleted', title: 'Lista' },
    { id: 'orderedList', icon: 'mdi-format-list-numbered', title: 'Lista numerada' },
    { id: 'blockquote', icon: 'mdi-format-quote-close', title: 'Cita' },
    { id: 'link', icon: 'mdi-link-variant', title: 'Enlace' },
  ],
  [
    { id: 'undo', icon: 'mdi-undo', title: 'Deshacer (Ctrl+Z)' },
    { id: 'redo', icon: 'mdi-redo', title: 'Rehacer (Ctrl+Y)' },
    { id: 'clear', icon: 'mdi-format-clear', title: 'Quitar formato' },
  ],
]

const editorEl = ref<HTMLElement | null>(null)
const editor = shallowRef<Editor | null>(null)
const focused = ref(false)
const words = ref(0)
const canUndo = ref(false)
const canRedo = ref(false)
const active = reactive<Record<string, boolean>>({})

// Último HTML emitido, para no reinyectar en el editor lo que él mismo produjo
let lastHtml = props.modelValue || ''
let emitTimer: ReturnType<typeof setTimeout> | null = null

const flush = () => {
  if (emitTimer) {
    clearTimeout(emitTimer)
    emitTimer = null
  }
  if (!editor.value) return
  const html = editor.value.isEmpty ? '' : editor.value.getHTML()
  if (html !== lastHtml) {
    lastHtml = html
    emit('update:modelValue', html)
  }
}

const scheduleEmit = () => {
  if (emitTimer) clearTimeout(emitTimer)
  emitTimer = setTimeout(flush, 300)
}

// Solo asigna cuando el valor cambia, para no disparar a Vue en cada tecla
const setIfChanged = <T,>(target: { value: T }, value: T) => {
  if (target.value !== value) target.value = value
}

const refreshToolbar = () => {
  const ed = editor.value
  if (!ed) return
  const next: Record<string, boolean> = {
    bold: ed.isActive('bold'),
    italic: ed.isActive('italic'),
    underline: ed.isActive('underline'),
    strike: ed.isActive('strike'),
    h1: ed.isActive('heading', { level: 1 }),
    h2: ed.isActive('heading', { level: 2 }),
    bulletList: ed.isActive('bulletList'),
    orderedList: ed.isActive('orderedList'),
    blockquote: ed.isActive('blockquote'),
    link: ed.isActive('link'),
  }
  for (const key in next) {
    if (active[key] !== next[key]) active[key] = next[key]
  }
  setIfChanged(canUndo, ed.can().undo())
  setIfChanged(canRedo, ed.can().redo())
  setIfChanged(words, ed.storage.characterCount.words())
}

const runTool = (id: ToolId) => {
  const ed = editor.value
  if (!ed) return
  const chain = ed.chain().focus()
  switch (id) {
    case 'bold': chain.toggleBold().run(); break
    case 'italic': chain.toggleItalic().run(); break
    case 'underline': chain.toggleUnderline().run(); break
    case 'strike': chain.toggleStrike().run(); break
    case 'h1': chain.toggleHeading({ level: 1 }).run(); break
    case 'h2': chain.toggleHeading({ level: 2 }).run(); break
    case 'bulletList': chain.toggleBulletList().run(); break
    case 'orderedList': chain.toggleOrderedList().run(); break
    case 'blockquote': chain.toggleBlockquote().run(); break
    case 'undo': chain.undo().run(); break
    case 'redo': chain.redo().run(); break
    case 'clear': chain.clearNodes().unsetAllMarks().run(); break
    case 'link': {
      const previous = ed.getAttributes('link').href || ''
      const url = window.prompt('URL del enlace (deja vacío para quitarlo)', previous)
      if (url === null) return
      if (url.trim() === '') {
        chain.extendMarkRange('link').unsetLink().run()
      } else {
        const href = /^https?:\/\//i.test(url.trim()) ? url.trim() : `https://${url.trim()}`
        chain.extendMarkRange('link').setLink({ href }).run()
      }
      break
    }
  }
}

const focusEditor = () => {
  if (editor.value && !editor.value.isFocused) editor.value.commands.focus()
}

onMounted(() => {
  editor.value = new Editor({
    element: editorEl.value!,
    content: props.modelValue || '',
    extensions: [
      StarterKit.configure({
        heading: { levels: [1, 2, 3] },
        link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
      }),
      Placeholder.configure({ placeholder: props.placeholder }),
      CharacterCount,
    ],
    onUpdate: scheduleEmit,
    onTransaction: refreshToolbar,
    onFocus: () => { focused.value = true },
    onBlur: () => {
      focused.value = false
      flush()
    },
  })
  refreshToolbar()
})

// Contenido que llega desde fuera (al abrir una publicación para editar o al limpiar)
watch(() => props.modelValue, (value) => {
  const html = value || ''
  if (!editor.value || html === lastHtml) return
  lastHtml = html
  editor.value.commands.setContent(html, { emitUpdate: false })
  refreshToolbar()
})

onBeforeUnmount(() => {
  flush()
  editor.value?.destroy()
})

defineExpose({
  // HTML al momento (sin esperar el retraso), para guardar
  getHTML: () => {
    flush()
    return lastHtml
  },
  isEmpty: () => !editor.value || editor.value.getText().trim().length === 0,
  focus: focusEditor,
})
</script>

<style scoped>
.rte {
  border: 1px solid rgba(0, 0, 0, 0.38);
  border-radius: 8px;
  overflow: hidden;
  background: #ffffff;
  transition: border-color 0.2s;
}
.rte:hover {
  border-color: rgba(0, 0, 0, 0.87);
}
.rte--focused {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}
.rte--error {
  border-color: rgb(var(--v-theme-error));
}
.rte-toolbar {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #f4f4f6;
  border-bottom: 1px solid #e4e4ea;
}
.rte-footer {
  border-top: 1px solid #f0f0f4;
  background: #fafafc;
}
.rte-content {
  overflow-y: auto;
  padding: 16px 20px;
  cursor: text;
  font-size: 1rem;
  line-height: 1.7;
  color: #1c1c28;
}
.rte-content :deep(.tiptap) {
  outline: none;
  min-height: 100%;
}
.rte-content :deep(.tiptap > * + *) { margin-top: 0.75em; }
.rte-content :deep(.tiptap h1) { font-size: 1.7rem; font-weight: 800; line-height: 1.25; margin-top: 1.2em; }
.rte-content :deep(.tiptap h2) { font-size: 1.4rem; font-weight: 700; line-height: 1.3; margin-top: 1.2em; }
.rte-content :deep(.tiptap h3) { font-size: 1.15rem; font-weight: 700; line-height: 1.3; margin-top: 1em; }
.rte-content :deep(.tiptap ul),
.rte-content :deep(.tiptap ol) { padding-left: 1.5rem; }
.rte-content :deep(.tiptap blockquote) {
  border-left: 3px solid rgb(var(--v-theme-primary));
  padding-left: 1rem;
  color: #55556a;
  font-style: italic;
}
.rte-content :deep(.tiptap a) {
  color: rgb(var(--v-theme-primary));
  text-decoration: underline;
}
.rte-content :deep(.tiptap p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  float: left;
  height: 0;
  color: #9e9eb0;
  pointer-events: none;
}
</style>
