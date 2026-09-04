import axios from 'axios';
// IMPORTANTE: Asegúrate de importar tu router aquí para poder redirigir al login si el refresh falla
import {router} from '@/router'; 

const app_name = `${import.meta.env.VITE_APP_NAME}`;

const axiosClient = axios.create({
    withCredentials: true,
    withXSRFToken: true,
    baseURL: import.meta.env.VITE_API_URL
});

axiosClient.interceptors.request.use((config) => {
    let user = JSON.parse(localStorage.getItem(`${app_name}`) || '{}');

    // Es buena práctica validar que el token de acceso exista antes de inyectarlo
    if (user.token) {
        config.headers.Authorization = `Bearer ${user.token}`;
    }

    return config;
});

axiosClient.interceptors.response.use(
    (response) => response, // Si la petición es exitosa, simplemente la devolvemos
    async (error) => {
        const originalRequest = error.config;

        console.log('X');

        // Si el error es 401 (No autorizado) y NO hemos intentado reintentar esta petición aún
        if (error.response?.status === 401 && !originalRequest._retry) {
            originalRequest._retry = true; // Marcamos la petición para no caer en un bucle infinito

            try {
                let user = JSON.parse(localStorage.getItem(`${app_name}`) || '{}');
                console.log('x', user);
                const refreshToken = user.refresh;

                if (refreshToken) {
                    // 1. Pedimos un nuevo access token a Django
                    // NOTA: Usamos 'axios.post' normal, NO 'axiosClient.post' para evitar que 
                    // esta misma petición pase por el interceptor y cree un bucle.
                    const response = await axios.post(`${import.meta.env.VITE_API_URL}/token/refresh/`, {
                        refresh: refreshToken
                    });

                    // 2. Si es exitoso, actualizamos el token en el LocalStorage
                    user.access = response.data.access;
                    localStorage.setItem(`${app_name}`, JSON.stringify(user));

                    // 3. Actualizamos el header de la petición original que falló
                    originalRequest.headers.Authorization = `Bearer ${response.data.access}`;

                    // 4. Volvemos a lanzar la petición original con el nuevo token
                    return axiosClient(originalRequest);
                }
            } catch (refreshError) {
                // Si el refresh token también expiró o es inválido, forzamos el cierre de sesión
                console.error("La sesión ha expirado por completo.");
                localStorage.removeItem(`${app_name}`);
                
                router.push('/'); // Descomenta esto para redirigir al login
                
                // Rechazamos el error para que los .catch() de tus componentes se enteren
                return Promise.reject(refreshError);
            }
        }

        if (error.response?.status === 403) {
            // router.push('/auth/403'); // redirige a tu página 403
            console.warn("No tienes permisos para esta acción.");
        }

        return Promise.reject(error);
    }
);

export default axiosClient;