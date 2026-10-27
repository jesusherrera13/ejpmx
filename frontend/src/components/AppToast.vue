<!-- src/components/AppToast.vue -->
<!-- Toast global: se controla desde el store de notificaciones -->
<template>
  <v-snackbar
    v-model="notifications.show"
    :color="notifications.color"
    :timeout="notifications.timeout"
    location="top right"
    rounded="lg"
    multi-line
  >
    <div class="d-flex align-center ga-3">
      <v-icon :icon="notifications.icon" />
      <span class="text-body-2 font-weight-medium">{{ notifications.message }}</span>
    </div>
    <template v-slot:actions>
      <v-btn icon="mdi-close" variant="text" size="small" @click="notifications.show = false" />
    </template>
  </v-snackbar>
</template>

<script setup>
import { useNotificationsStore } from '@/stores/notifications'

const notifications = useNotificationsStore()
</script>
