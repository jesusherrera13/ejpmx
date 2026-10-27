<!-- src/components/RowActionsMenu.vue -->
<!-- Botón de tres puntos con las acciones de un registro (Editar / Eliminar y las del slot) -->
<template>
  <v-menu location="bottom end">
    <template v-slot:activator="{ props: activatorProps }">
      <v-btn
        v-bind="activatorProps"
        icon="mdi-dots-vertical"
        :size="size"
        variant="text"
        color="grey-darken-2"
      />
    </template>
    <v-list density="compact" min-width="150" class="rounded-lg">
      <!-- Acciones propias del módulo (p. ej. Configuración en Torneos) -->
      <slot />
      <v-list-item prepend-icon="mdi-pencil-outline" title="Editar" @click="emit('edit')" />
      <v-list-item
        prepend-icon="mdi-delete-outline"
        title="Eliminar"
        base-color="error"
        :disabled="!canDelete"
        @click="emit('delete')"
      />
    </v-list>
  </v-menu>
</template>

<script setup>
defineProps({
  size: { type: String, default: 'small' },
  // Algunos módulos aún no tienen borrado en el backend
  canDelete: { type: Boolean, default: true }
})

const emit = defineEmits(['edit', 'delete'])
</script>
