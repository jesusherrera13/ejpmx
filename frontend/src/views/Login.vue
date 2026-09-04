<template>
  <v-app class="bg-mlb-dark">
    <v-container class="fill-height d-flex align-center justify-center" fluid>
      <v-card width="100%" max-width="440" class="pa-8 rounded-lg text-center" flat elevation="3">
        
        <!-- Logo de la MLB -->
        <div class="d-flex justify-center mb-6">
          <div class="mlb-logo-badge">
            <span class="blue-side"></span>
            <v-icon icon="mdi-baseball" class="baseball-icon text-white" size="24" />
            <span class="red-side"></span>
          </div>
        </div>

        <!-- Título -->
        <h1 class="text-h5 font-weight-black text-left text-grey-darken-4 line-height-tight mb-3">
          Introduce tu Dirección de Email
        </h1>

        <!-- Subtítulo -->
        <p class="text-body-2 text-left text-grey-darken-2 mb-6">
          Introduce la dirección de email para tu cuenta MLB.
        </p>

        <!-- Formulario -->
        <v-form @submit.prevent="handleContinue">
          <div class="text-left text-caption font-weight-bold text-grey-darken-3 mb-1">
            Correo electrónico
          </div>
          <v-text-field
            v-model="email"
            variant="outlined"
            density="comfortable"
            placeholder="ejemplo@correo.com"
            color="black"
            class="mb-4 text-field-custom"
            hide-details="auto"
            type="email"
            required
          />

          <v-text-field
            v-model="password"
            variant="outlined"
            density="comfortable"
            placeholder="Password"
            color="black"
            class="mb-4 text-field-custom"
            hide-details="auto"
            type="password"
            required
          />

          <!-- Botón Continuar con estado de carga y bloqueo -->
          <v-btn
            type="submit"
            color="black"
            block
            size="large"
            class="text-capitalize rounded-pill font-weight-bold my-4 btn-continue"
            flat
            :loading="isLoading"
            :disabled="isLoading"
          >
            Continuar
          </v-btn>
        </v-form>

        <!-- Enlaces de soporte/registro -->
        <div class="mt-6 mb-8">
          <a href="#" class="text-decoration-none text-blue-dark font-weight-bold d-block mb-2 text-body-2">
            ¿Necesitas ayuda?
          </a>
          <div class="text-body-2 text-grey-darken-3">
            ¿Eres Nuevo a MLB? 
            <a href="#" class="text-decoration-none text-blue-dark font-weight-bold ml-1">
              Inscríbete
            </a>
          </div>
        </div>

        <!-- Footer legal -->
        <p class="text-xxs text-grey-darken-1 leading-normal px-2">
          Al iniciar sesión, entiendo y acepto estar sujeto a los 
          <a href="#" class="text-decoration-none text-blue-dark font-weight-bold">Términos de Uso</a> 
          y la 
          <a href="#" class="text-decoration-none text-blue-dark font-weight-bold">Política de Privacidad</a> 
          de MLB.com.
        </p>
      </v-card>
    </v-container>

    <!-- Notificación de Error -->
    <v-snackbar
      v-model="showError"
      color="error"
      timeout="4000"
      location="top"
    >
      {{ errorMessage }}
      <template v-slot:actions>
        <v-btn
          color="white"
          variant="text"
          @click="showError = false"
        >
          Cerrar
        </v-btn>
      </template>
    </v-snackbar>
  </v-app>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth';

const email = ref('eljuegoperfectomx13@gmail.com');
const password = ref('S3v0y4$123');

// Nuevas variables de estado para la UI
const isLoading = ref(false);
const showError = ref(false);
const errorMessage = ref('');

const router = useRouter()

const handleContinue = async () => {
  // 1. Iniciamos el estado de carga y ocultamos errores previos
  isLoading.value = true;
  showError.value = false;

  try {
    const authStore = useAuthStore();
    const response = await authStore.login(email.value, password.value);

    if (authStore.user) {
      router.push('/');
    } else {
      errorMessage.value = response.message || 'Error desconocido al iniciar sesión.';
      showError.value = true;
    }
  } catch (error) {
    console.error(error);
    const data = error.response?.data;
    
    // 2. Evaluamos la respuesta de Django para mostrar un mensaje amigable al usuario
    if (data?.detail === "No active account found with the given credentials") { //
      errorMessage.value = 'El correo o la contraseña son incorrectos.'; //[cite: 1]
    } else {
      errorMessage.value = data?.detail || data?.message || 'Ocurrió un error de conexión con el servidor.';
    }
    
    showError.value = true;
  } finally {
    // 3. Apagamos el estado de carga sin importar si fue exitoso o falló
    isLoading.value = false;
  }
}
</script>

<style scoped>
/* Fondo oficial Azul Oscuro MLB */
.bg-mlb-dark {
  background-color: #0a2240 !important;
}

/* Color azul para los enlaces */
.text-blue-dark {
  color: #002d62 !important;
}

/* Tipografía de tamaño extra pequeño para el disclaimer */
.text-xxs {
  font-size: 0.68rem !important;
  line-height: 1.4;
}

/* Recreación CSS del Logo MLB (Silueta azul y roja) */
.mlb-logo-badge {
  display: flex;
  position: relative;
  width: 72px;
  height: 38px;
  border-radius: 6px;
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
  background-color: #002d62;
}
.red-side {
  position: absolute;
  right: 0;
  top: 0;
  width: 50%;
  height: 100%;
  background-color: #ba0021;
}
.baseball-icon {
  position: relative;
  z-index: 2;
}

/* Estilización fina del input text de Vuetify */
.text-field-custom :deep(.v-field__outline) {
  --v-field-border-width: 1px !important;
  --v-field-border-opacity: 1 !important;
  border-color: #8c8c8c !important;
}
.text-field-custom :deep(.v-field--focused .v-field__outline) {
  border-color: #000000 !important;
}

/* Efecto hover del botón de continuar */
.btn-continue {
  letter-spacing: 0.5px;
  transition: opacity 0.2s;
}
.btn-continue:hover {
  opacity: 0.9;
}
</style>