<!-- App.vue -->
<template>
  <v-app class="bg-grey-lighten-4">
    <router-view />
    <AppToast />
  </v-app>
</template>

<script setup>
import AppToast from '@/components/AppToast.vue'
</script>
