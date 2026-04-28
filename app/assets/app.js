import './styles/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';

async function bootstrap() {
    const pinia = createPinia();
    const app = createApp(App);

    app.use(pinia);

    // Resolver la sesión ANTES de instalar el router. Si no, el primer
    // beforeEach corre con auth.user=null y manda al login aunque la
    // cookie sea válida.
    const auth = useAuthStore();
    try {
        await auth.fetchMe();
    } catch (_) { /* sigue como anónimo */ }

    app.use(router);
    await router.isReady();
    app.mount('#app');
}

bootstrap();
