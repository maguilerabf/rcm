<template>
    <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-slate-900 px-5 pb-4">
        <div class="flex h-16 items-center gap-3 pt-2">
            <div class="h-9 w-9 rounded-xl bg-brand-500 text-white flex items-center justify-center font-bold">R</div>
            <span class="text-white font-semibold tracking-tight">RCM</span>
        </div>

        <nav class="flex flex-1 flex-col">
            <div class="text-[11px] uppercase tracking-wider text-slate-400 px-2 mb-2">Módulos</div>
            <ul class="flex flex-col gap-1">
                <li v-for="item in items" :key="item.to.name">
                    <RouterLink
                        :to="item.to"
                        @click="$emit('navigate')"
                        class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white"
                        active-class="bg-slate-800 text-white"
                    >
                        <component :is="item.icon" class="h-5 w-5 text-slate-400 group-hover:text-brand-400" />
                        <span class="flex-1">{{ item.label }}</span>
                        <span
                            v-if="item.badge !== undefined && item.badge !== null"
                            :class="[
                                'inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 rounded-full text-[11px] font-semibold',
                                item.badge > 0 ? 'bg-rose-500 text-white' : 'bg-slate-700 text-slate-400'
                            ]"
                            :title="`${item.badge} coincidencias completas`"
                        >
                            {{ formatBadge(item.badge) }}
                        </span>
                    </RouterLink>
                </li>
            </ul>

            <div class="mt-auto">
                <div class="border-t border-slate-800 pt-4 px-2">
                    <div class="text-xs text-slate-400">Conectado como</div>
                    <div class="text-sm text-white truncate">{{ auth.displayName }}</div>
                    <div v-if="auth.user?.email" class="text-xs text-slate-400 truncate">{{ auth.user.email }}</div>
                    <button class="mt-3 w-full text-left text-sm text-slate-300 hover:text-white inline-flex items-center gap-2" @click="onLogout">
                        <ArrowLeftStartOnRectangleIcon class="h-4 w-4" />
                        Salir
                    </button>
                </div>
            </div>
        </nav>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../api/client';
import { useAuthStore } from '../stores/auth';
import { Squares2X2Icon, UsersIcon, ArrowLeftStartOnRectangleIcon } from '@heroicons/vue/24/outline';

defineEmits(['navigate']);

const auth = useAuthStore();
const router = useRouter();

const duplicadosFullCount = ref(null);

const items = computed(() => [
    { to: { name: 'identificacion-sectores' }, label: 'Identificación Sectores', icon: Squares2X2Icon },
    { to: { name: 'duplicados-inscritos' }, label: 'Duplicados Inscritos', icon: UsersIcon, badge: duplicadosFullCount.value },
]);

function formatBadge(n) {
    if (n > 99) return '99+';
    return String(n);
}

async function loadCounters() {
    if (!auth.isAuthenticated) return;
    try {
        const { data } = await api.get('/duplicados-inscritos/stats');
        duplicadosFullCount.value = data.fullGroups;
    } catch (_) { /* silencioso */ }
}

async function onLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}

// Refrescar contadores cuando otra parte del SPA emite "rcm:data-changed".
// Lo emiten: HistorialCargas (delete one / delete all), upload exitoso, etc.
function onDataChanged() {
    loadCounters();
}
onMounted(() => window.addEventListener('rcm:data-changed', onDataChanged));
onUnmounted(() => window.removeEventListener('rcm:data-changed', onDataChanged));

onMounted(loadCounters);
</script>
