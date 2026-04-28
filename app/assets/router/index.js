import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/Login.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/',
        component: () => import('../components/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', redirect: { name: 'identificacion-sectores' } },
            {
                path: 'modulos/identificacion-sectores',
                name: 'identificacion-sectores',
                component: () => import('../views/modules/IdentificacionSectores.vue'),
            },
            {
                path: 'modulos/identificacion-sectores/historial',
                name: 'identificacion-sectores-historial',
                component: () => import('../views/modules/HistorialCargas.vue'),
            },
            {
                path: 'modulos/duplicados-inscritos',
                name: 'duplicados-inscritos',
                component: () => import('../views/modules/DuplicadosInscritos.vue'),
            },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory('/'),
    routes,
});

router.beforeEach((to, from) => {
    const auth = useAuthStore();
    console.log('[RCM router] navigation', from.path, '→', to.path, 'authenticated=', auth.isAuthenticated);
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        console.warn('[RCM router] redirect to login (requiresAuth, no auth)');
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'identificacion-sectores' };
    }
});

window.addEventListener('rcm:unauthorized', (e) => {
    const auth = useAuthStore();
    console.warn('[RCM router] rcm:unauthorized event from', e.detail?.url, '→ kick to login');
    auth.user = null;
    if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' });
    }
});

export default router;
