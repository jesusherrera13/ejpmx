<template>
  <v-app class="bg-mlb-dark">
    <v-container class="fill-height d-flex align-center justify-center" fluid>
      <!-- theme="light": la tarjeta siempre es blanca aunque el sistema esté en modo oscuro -->
      <v-card theme="light" color="white" width="100%" max-width="390" class="login-card rounded-lg" flat>

        <!-- Logo de la MLB -->
        <div class="d-flex justify-center mb-10">
          <div class="mlb-logo-badge">
            <span class="blue-side"></span>
            <v-icon icon="mdi-baseball" class="baseball-icon text-white" size="24" />
            <span class="red-side"></span>
          </div>
        </div>

        <!-- Título -->
        <h1 class="login-title mb-2">
          Introduce tu Dirección de Email
        </h1>

        <!-- Subtítulo -->
        <p class="login-subtitle mb-4">
          Introduce la dirección de email para tu cuenta MLB.
        </p>

        <!-- Formulario -->
        <v-form @submit.prevent="handleContinue">
          <label class="field-label" for="login-email">Correo electrónico</label>
          <v-text-field
            id="login-email"
            v-model="email"
            variant="outlined"
            color="black"
            class="mb-4 text-field-custom"
            hide-details="auto"
            type="email"
            autocomplete="email"
            required
          />

          <label class="field-label" for="login-password">Contraseña</label>
          <v-text-field
            id="login-password"
            v-model="password"
            variant="outlined"
            color="black"
            class="mb-4 text-field-custom"
            hide-details="auto"
            type="password"
            autocomplete="current-password"
            required
          />

          <!-- Botón Continuar con estado de carga y bloqueo -->
          <v-btn
            type="submit"
            block
            height="50"
            class="text-none rounded-pill btn-continue"
            flat
            :loading="isLoading"
            :disabled="isLoading"
          >
            Continuar
          </v-btn>
        </v-form>

        <!-- Enlaces de soporte/registro -->
        <div class="text-center mt-12 mb-10 login-links">
          <a href="#" class="link-blue d-block mb-1">
            ¿Necesitas ayuda?
          </a>
          <div>
            ¿Eres Nuevo a MLB?
            <a href="#" class="link-blue ml-1">Inscríbete</a>
          </div>
        </div>

        <!-- Footer legal -->
        <p class="login-legal text-center px-2">
          Al iniciar sesión, entiendo y acepto estar sujeto a los
          <a href="#" class="link-blue">Términos de Uso</a>
          y la
          <a href="#" class="link-blue">Política de Privacidad</a>
          de MLB.com.
        </p>
      </v-card>
    </v-container>

  </v-app>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { getErrorMessage } from '@/utils/errors';

const email = ref('');
const password = ref('');

// Estado de la UI; los errores se muestran en el toast global
const isLoading = ref(false);
const notify = useNotificationsStore();

const router = useRouter()

const handleContinue = async () => {
  isLoading.value = true;

  try {
    const authStore = useAuthStore();
    const response = await authStore.login(email.value, password.value);

    if (authStore.user) {
      router.push('/');
    } else {
      notify.error(response.message || 'Error desconocido al iniciar sesión.');
    }
  } catch (error) {
    console.error(error);
    notify.error(getErrorMessage(error, 'Ocurrió un error de conexión con el servidor.'));
  } finally {
    // Apagamos el estado de carga sin importar si fue exitoso o falló
    isLoading.value = false;
  }
}
</script>

<style scoped>
/* Fondo oficial Azul Oscuro MLB */
.bg-mlb-dark {
  background-color: #041e42 !important;
}

/* Tarjeta blanca */
.login-card {
  padding: 56px 26px 24px;
  color: #000;
}

/* Tipografía */
.login-title {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.25;
  color: #000;
  text-align: left;
}
.login-subtitle {
  font-size: 1rem;
  line-height: 1.5;
  color: #000;
  text-align: left;
}
.field-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  color: #000;
  text-align: left;
  margin-bottom: 4px;
}
.login-links {
  font-size: 0.875rem;
  color: #000;
}
.login-legal {
  font-size: 0.69rem;
  line-height: 1.6;
  color: #000;
}

/* Color azul para los enlaces */
.link-blue {
  color: #0d5cc4;
  font-weight: 700;
  text-decoration: none;
}
.link-blue:hover {
  text-decoration: underline;
}

/* Recreación CSS del Logo MLB (Silueta azul y roja) */
.mlb-logo-badge {
  display: flex;
  position: relative;
  width: 74px;
  height: 40px;
  border-radius: 4px;
  overflow: hidden;
  align-items: center;
  justify-content: center;
}
.blue-side {
  position: absolute;
  left: 0;
  top: 0;
  width: 50%;
  height: 100%;
  background-color: #041e42;
}
.red-side {
  position: absolute;
  right: 0;
  top: 0;
  width: 50%;
  height: 100%;
  background-color: #bf0d3e;
}
.baseball-icon {
  position: relative;
  z-index: 2;
}

/* Estilización fina del input text de Vuetify */
.text-field-custom :deep(.v-field) {
  border-radius: 4px;
  background-color: #fff;
}
.text-field-custom :deep(.v-field__input) {
  min-height: 52px;
  color: #000;
}
.text-field-custom :deep(.v-field__outline) {
  --v-field-border-width: 1px;
  --v-field-border-opacity: 1;
  color: #000;
}

/* Botón Continuar */
.btn-continue {
  background-color: #060a0d !important;
  color: #fff !important;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0;
  transition: opacity 0.2s;
}
.btn-continue:hover {
  opacity: 0.9;
}
</style>
