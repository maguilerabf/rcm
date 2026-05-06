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
        path: '/recuperar',
        name: 'forgot-password',
        component: () => import('../views/ForgotPassword.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/recuperar/:token',
        name: 'reset-password',
        component: () => import('../views/ResetPassword.vue'),
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
            {
                path: 'usuarios',
                name: 'usuarios',
                component: () => import('../views/Usuarios.vue'),
                meta: { superAdminOnly: true },
            },
            {
                path: 'admin/aprobar/:token',
                name: 'aprobar-token',
                component: () => import('../views/AprobarToken.vue'),
                meta: { superAdminOnly: true },
            },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory('/'),
    routes,
});

router.beforeEach((to) => {
    const auth = useAuthStore();
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'identificacion-sectores' };
    }
    if (to.meta.superAdminOnly && !auth.isSuperAdmin) {
        return { name: 'identificacion-sectores' };
    }
});

window.addEventListener('rcm:unauthorized', () => {
    const auth = useAuthStore();
    auth.user = null;
    if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' });
    }
});

export default router;
