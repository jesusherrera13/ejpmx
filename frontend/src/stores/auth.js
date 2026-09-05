import { defineStore } from 'pinia'; 
import { useRouter } from 'vue-router'
import axiosClient from '@/axios';

const app_name = `${import.meta.env.VITE_APP_NAME}`;

const router = useRouter();

let user = {id: '', name: '', email: '', initial: ''};

try {
    var decoded = localStorage.getItem(`${app_name}`);
    decoded = JSON.parse(decoded);
    decoded.initial = decoded.name.substring(0, 1);
    decoded.name = (`${decoded.name}`).trim();
    user = decoded ? decoded : {id: '', name: '', email: '', initial: ''};
}
catch(ex) {
    console.log(ex);
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: user, // Limpiado el @ts-ignore
        returnUrl: null,
        system_modules: [],
        auth_sidebar: [],
        isLoggingOut: false
    }),
    actions: {
        async login(email, password) { // Eliminados los tipos de TypeScript
            const response = await axiosClient.post(`/login`, { email, password });

            if (response.status) {
                this.user = response.data;
                this.user.initial = this.user.name.substring(0, 1);
                localStorage.setItem(`${app_name}`, JSON.stringify(response.data));
            }

            return { ...response.data };
        }, 
        async logout() {
            try {
                let user = JSON.parse(localStorage.getItem(`${app_name}`) || '{}');
                console.log(`app_name ${app_name}`)
                
                const response = await axiosClient.post('/logout/', {
                    refresh_token: user.refresh
                });

                console.log(response.status);
                if(response.status == 205) {
                    //router.push('/');
                    // this.user = {id: '', name: '', email: '', initial: ''};
                    // localStorage.removeItem(app_name);
                }

                this.user = {id: '', name: '', email: '', initial: ''};
                localStorage.removeItem(app_name);
                
            }
            catch (error) { // Eliminado ': any'
                if (error.response?.data?.message !== 'Unauthenticated.') {
                    throw error;
                }
                this.user = {id: '', name: '', email: '', initial: ''};
                localStorage.removeItem(app_name);
            }
            finally {
                // this.user = {id: '', name: '', email: '', initial: ''};
                this.user = {id: '', name: '', email: '', initial: ''};
                localStorage.removeItem(app_name);
            }
        }
    }
});
