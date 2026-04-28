<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">Duplicados en Inscritos</h2>
            <p class="mt-1 text-sm text-slate-500">
                Detecta personas con <span class="font-medium">distinto RUN+DV</span> pero coincidencia
                completa o parcial en nombre, apellidos y fecha de nacimiento. Útil para identificar quienes
                cambiaron de RUN provisorio a permanente.
            </p>
        </div>

        <div v-if="!job" class="card p-8 text-center">
            <ExclamationTriangleIcon class="h-10 w-10 mx-auto text-amber-400 mb-3" />
            <p class="text-slate-700 font-medium">Aún no has cargado un padrón de inscritos.</p>
            <p class="text-sm text-slate-500 mt-1">
                Anda a <RouterLink :to="{name:'identificacion-sectores'}" class="text-brand-600 hover:underline">Identificación Sectores</RouterLink>
                y sube el archivo de inscritos.
            </p>
        </div>

        <template v-else>
            <!-- Stats -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Grupos detectados" :value="stats.totalGroups || 0" :icon="UsersIcon" tone="brand" />
                <StatCard label="Personas en duplicados" :value="stats.totalPersons || 0" :icon="UserGroupIcon" tone="slate" />
                <StatCard label="Coincidencia completa" :value="stats.fullGroups || 0" :icon="CheckBadgeIcon" tone="red" />
                <StatCard label="Coincidencia parcial" :value="stats.partialGroups || 0" :icon="ExclamationCircleIcon" tone="amber" />
            </div>

            <!-- Filtros -->
            <div class="card p-4 flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="flex flex-wrap gap-2">
                    <FilterPill v-for="opt in matchOptions" :key="opt.value"
                        :active="selectedTypes.includes(opt.value) || (opt.value === 'all' && selectedTypes.length === 0)"
                        :tone="opt.tone"
                        @click="toggleType(opt.value)">
                        {{ opt.label }}
                    </FilterPill>
                </div>
                <div class="lg:ml-auto flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <MagnifyingGlassIcon class="h-4 w-4 absolute left-3 top-2.5 text-slate-400" />
                        <input v-model="search" @input="onSearchInput" type="text" placeholder="Nombre, apellido o RUN…" class="input pl-9 w-72" />
                    </div>
                    <button @click="reload" :disabled="loading" class="btn-secondary">
                        <ArrowPathIcon :class="['h-4 w-4', loading && 'animate-spin']" />
                        Recargar
                    </button>
                    <button @click="downloadXlsx" :disabled="!groups.length || downloading" class="btn-secondary">
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        {{ downloading ? 'Generando…' : 'Descargar' }}
                    </button>
                    <button @click="emailDialog = true" :disabled="!groups.length" class="btn-primary">
                        <PaperAirplaneIcon class="h-4 w-4" />
                        Enviar por correo
                    </button>
                </div>
            </div>

            <!-- Resultados -->
            <div v-if="loading && groups.length === 0" class="card p-12 text-center text-slate-400">
                <ArrowPathIcon class="h-8 w-8 mx-auto animate-spin" />
                <p class="mt-3 text-sm">Analizando padrón…</p>
            </div>

            <div v-else-if="groups.length === 0" class="card p-12 text-center text-slate-400">
                <CheckCircleIcon class="h-10 w-10 mx-auto text-emerald-400 mb-3" />
                <p class="text-slate-700 font-medium">Sin duplicados detectados</p>
                <p class="text-sm mt-1">
                    Con los filtros actuales no encontré personas con distinto RUN que coincidan en los campos relevantes.
                </p>
            </div>

            <div v-else class="space-y-3">
                <DuplicateGroupCard v-for="(g, idx) in groups" :key="idx" :group="g" />
                <p v-if="groups.length === 1000" class="text-xs text-slate-500 text-center">
                    Mostrando los primeros 1000 grupos por tipo. Filtra por nombre o RUN para acotar la búsqueda.
                </p>
            </div>
        </template>

        <EmailDialog
            :open="emailDialog"
            title="Enviar duplicados por correo"
            default-subject="Duplicados Inscritos - RCM"
            endpoint="/duplicados-inscritos/email"
            :extra-payload="emailExtraPayload"
            :context-label="emailContextLabel"
            @close="emailDialog = false"
        />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api/client';
import StatCard from '../../components/StatCard.vue';
import FilterPill from '../../components/FilterPill.vue';
import DuplicateGroupCard from '../../components/DuplicateGroupCard.vue';
import EmailDialog from '../../components/EmailDialog.vue';
import {
    UsersIcon, UserGroupIcon, CheckBadgeIcon, ExclamationCircleIcon,
    MagnifyingGlassIcon, ArrowPathIcon, ArrowDownTrayIcon, PaperAirplaneIcon,
    ExclamationTriangleIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const matchOptions = [
    { value: 'all', label: 'Todas', tone: 'slate' },
    { value: 'full', label: 'Coincidencia completa', tone: 'red' },
    { value: 'partial', label: 'Coincidencia parcial', tone: 'amber' },
];

const job = ref(null);
const groups = ref([]);
const stats = reactive({});
const selectedTypes = ref([]); // vacío = todas
const search = ref('');
const loading = ref(false);
const downloading = ref(false);
const emailDialog = ref(false);

const emailContextLabel = computed(() => {
    const parts = [];
    if (selectedTypes.value.length) parts.push(`Tipos: ${selectedTypes.value.join(', ')}`);
    if (search.value) parts.push(`Búsqueda: "${search.value}"`);
    return parts.length ? `Filtros: ${parts.join(' · ')}` : 'Sin filtros (envía todos los grupos detectados)';
});

const emailExtraPayload = computed(() => ({
    matchTypes: selectedTypes.value.length ? selectedTypes.value.join(',') : null,
    search: search.value || null,
}));

function toggleType(value) {
    if (value === 'all') {
        selectedTypes.value = [];
    } else if (selectedTypes.value.includes(value)) {
        selectedTypes.value = selectedTypes.value.filter(t => t !== value);
    } else {
        selectedTypes.value = [...selectedTypes.value, value];
    }
    reload();
}

let searchTimer = null;
function onSearchInput() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(reload, 350);
}

const queryParams = computed(() => {
    const p = {};
    if (selectedTypes.value.length > 0) p.matchTypes = selectedTypes.value.join(',');
    if (search.value) p.search = search.value;
    return p;
});

async function reload() {
    loading.value = true;
    try {
        const { data } = await api.get('/duplicados-inscritos', { params: queryParams.value });
        job.value = data.job;
        groups.value = data.groups;
        Object.assign(stats, data.stats);
    } finally {
        loading.value = false;
    }
}

async function downloadXlsx() {
    downloading.value = true;
    try {
        const res = await api.get('/duplicados-inscritos/export', { params: queryParams.value, responseType: 'blob' });
        const cd = res.headers['content-disposition'] || '';
        const m = cd.match(/filename="([^"]+)"/);
        const filename = m ? m[1] : 'duplicados_inscritos.xlsx';
        const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    } finally {
        downloading.value = false;
    }
}

onMounted(reload);
</script>
