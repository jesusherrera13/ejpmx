<template>
  <footer class="site-footer bg-brand-blue text-white">
    <div class="footer-inner d-flex flex-column flex-md-row align-center justify-space-between ga-6 px-4 px-md-8 py-8">
      <!-- Marca -->
      <div class="text-center text-md-left">
        <router-link to="/" class="text-white text-decoration-none">
          <span class="font-weight-black text-h6">EL JUEGO PERFECTO</span>
          <span class="text-caption font-weight-medium ml-1">MX</span>
        </router-link>
        <div class="text-body-2 footer-muted mt-1">Noticias, videos y estadísticas de béisbol</div>
      </div>

      <!-- Redes y contacto -->
      <nav class="d-flex flex-wrap justify-center ga-2" aria-label="Redes sociales y contacto">
        <v-btn
          v-for="link in LINKS"
          :key="link.label"
          :href="link.href"
          :target="link.external ? '_blank' : undefined"
          :rel="link.external ? 'noopener' : undefined"
          :prepend-icon="link.icon"
          variant="outlined"
          color="white"
          class="text-none rounded-pill footer-link"
        >
          {{ link.label }}
        </v-btn>
      </nav>
    </div>

    <div class="footer-bottom text-center text-caption footer-muted py-3 px-4">
      © {{ year }} El Juego Perfecto MX. Todos los derechos reservados.
    </div>
  </footer>
</template>

<script setup lang="ts">
const LINKS = [
  { label: 'YouTube', icon: 'mdi-youtube', href: 'https://www.youtube.com/@eljuegoperfectomx', external: true },
  { label: 'Facebook', icon: 'mdi-facebook', href: 'https://www.facebook.com/eljuegoperfectomx', external: true },
  { label: 'eljuegoperfectomx13@gmail.com', icon: 'mdi-email-outline', href: 'mailto:eljuegoperfectomx13@gmail.com', external: false },
]

const year = new Date().getFullYear()
</script>

<style scoped>
.site-footer {
  margin-top: 48px;
}
.footer-inner {
  max-width: 1440px;
  margin: 0 auto;
}
.footer-muted {
  color: rgba(255, 255, 255, 0.75);
}
.footer-link {
  border-color: rgba(255, 255, 255, 0.5);
}
.footer-bottom {
  border-top: 1px solid rgba(255, 255, 255, 0.15);
}
</style>
