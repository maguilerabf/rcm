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
auth.fetchMe().finally(() => {
    app.mount('#app');
});
