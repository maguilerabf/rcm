import './styles/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';

async function bootstrap() {
    const pinia = createPinia();
    const app = createApp(App);

    // 1. Pinia primero (necesario para que useAuthStore funcione).
    app.use(pinia);

    // 2. Resolver la sesión ANTES de instalar el router. Si no, el primer
    //    beforeEach corre con auth.user=null y manda al login aunque la
    //    cookie sea válida.
    const auth = useAuthStore();
    console.log('[RCM] booting, calling /api/me...');
    try {
        await auth.fetchMe();
    } catch (e) {
        console.warn('[RCM] fetchMe falló (sigue como anónimo):', e?.message || e);
    }
    console.log('[RCM] fetchMe done. authenticated=', auth.isAuthenticated, 'user=', auth.user);

    // 3. Recién ahora montar router + app.
    app.use(router);
    await router.isReady();
    app.mount('#app');
}

bootstrap();
