import './styles/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';

const pinia = createPinia();
const app = createApp(App);

app.use(pinia);
app.use(router);

const auth = useAuthStore();
console.log('[RCM] booting, calling /api/me...');
auth.fetchMe().finally(() => {
    console.log('[RCM] fetchMe done. authenticated=', auth.isAuthenticated, 'user=', auth.user);
    app.mount('#app');
});
