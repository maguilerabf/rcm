<template>
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="flex h-14 items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button @click="$emit('toggle-sidebar')" class="lg:hidden -ml-2 p-2 text-slate-600 hover:text-slate-900">
                <Bars3Icon class="h-6 w-6" />
            </button>
            <h1 class="text-sm font-semibold text-slate-700">{{ title }}</h1>
            <div class="ml-auto flex items-center gap-3 text-sm text-slate-500">
                <span class="hidden sm:inline">{{ auth.user?.email }}</span>
                <div class="h-8 w-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-sm font-semibold">
                    {{ initials }}
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { Bars3Icon } from '@heroicons/vue/24/outline';

defineEmits(['toggle-sidebar']);

const auth = useAuthStore();
const route = useRoute();

const title = computed(() => {
    const map = {
        'identificacion-sectores': 'Identificación Sectores',
        'identificacion-sectores-historial': 'Historial de cargas',
        'duplicados-inscritos': 'Duplicados Inscritos',
    };
    return map[route.name] || 'RCM';
});

const initials = computed(() => {
    const f = (auth.user?.firstName || '').trim();
    const l = (auth.user?.lastName || '').trim();
    if (f || l) {
        return ((f[0] || '') + (l[0] || '')).toUpperCase() || '?';
    }
    return (auth.user?.email || '?').substring(0, 2).toUpperCase();
});
</script>
