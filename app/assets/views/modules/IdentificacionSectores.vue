<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Identificación Sectores</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Sube los reportes, cruza por <span class="font-medium">RUN+DV</span> y obtén la lista de coincidencias con su sector.
                </p>
            </div>
            <RouterLink :to="{name:'identificacion-sectores-historial'}" class="btn-secondary self-start sm:self-auto">
                <ClockIcon class="h-4 w-4" />
                Historial de cargas
            </RouterLink>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <UploadCard
                kind="telesalud"
                title="Reporte Telesalud"
                description="Archivo .xlsx con la columna 'Nº identificador' (RUN+DV concatenado)."
                :icon="DocumentTextIcon"
                :latest-job="jobs.telesalud"
                @updated="refreshAll"
            />
            <UploadCard
                kind="inscritos"
                title="Reporte Inscritos"
                description="Padrón de inscritos con columnas RUN, DV y SECTOR."
                :icon="UserGroupIcon"
                :latest-job="jobs.inscritos"
                @updated="refreshAll"
            />
        </div>

        <section class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Coincidencias</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <span v-if="!ready">Sube ambos reportes para ver el cruce.</span>
                        <span v-else>{{ formatNumber(total) }} coincidencias entre los reportes cargados.</span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <MagnifyingGlassIcon class="h-4 w-4 absolute left-3 top-2.5 text-slate-400" />
                        <input v-model="search" @input="onSearchInput" type="text" placeholder="Buscar por nombre, RUN, email…" class="input pl-9 w-64" :disabled="!ready" />
                    </div>
                    <select v-model="sector" @change="reload(1)" class="input w-52" :disabled="!ready">
                        <option :value="null">Todos los sectores</option>
                        <option v-for="s in sectores" :key="s" :value="s">{{ s }}</option>
                        <option value="__sin_sector__">— Sin sector / no informado —</option>
                    </select>
                    <button @click="downloadXlsx" :disabled="!ready || downloading" class="btn-secondary">
                        <ArrowPathIcon v-if="downloading" class="h-4 w-4 animate-spin" />
                        <ArrowDownTrayIcon v-else class="h-4 w-4" />
                        Descargar
                    </button>
                    <button @click="emailDialog = true" :disabled="!ready" class="btn-primary">
                        <PaperAirplaneIcon class="h-4 w-4" />
                        Enviar por correo
                    </button>
                </div>
            </div>

            <div v-if="!ready" class="p-10 text-center text-slate-500 text-sm">
                <InboxIcon class="h-10 w-10 mx-auto text-slate-300 mb-3" />
                Aún no hay datos suficientes para cruzar.
            </div>

            <div v-else class="overflow-auto" style="max-height: calc(100vh - 480px); min-height: 400px;">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 sticky top-0 z-10">
                        <tr>
                            <th v-for="col in columns" :key="col.key" class="px-3 py-2.5 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider whitespace-nowrap">
                                {{ col.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading">
                            <td :colspan="columns.length" class="px-3 py-12 text-center text-slate-400">
                                <ArrowPathIcon class="h-6 w-6 mx-auto animate-spin" />
                            </td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td :colspan="columns.length" class="px-3 py-12 text-center text-slate-400 text-sm">Sin resultados con los filtros actuales.</td>
                        </tr>
                        <tr v-else v-for="row in rows" :key="row.external_id" class="hover:bg-slate-50">
                            <td v-for="col in columns" :key="col.key" class="px-3 py-2 text-slate-700 whitespace-nowrap">
                                <template v-if="col.key === 'sector'">
                                    <span :class="['badge', sectorBadgeClass(row.sector)]">{{ row.sector || '—' }}</span>
                                </template>
                                <template v-else-if="col.key === 'fecha_solicitud'">
                                    {{ formatDate(row.fecha_solicitud) }}
                                </template>
                                <template v-else-if="col.key === 'prioridad'">
                                    <span class="badge-slate">P{{ row.prioridad }}</span>
                                </template>
                                <template v-else>{{ row[col.key] || '—' }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="ready && total > 0" class="px-5 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-600">
                <span>Mostrando {{ rangeStart }}–{{ rangeEnd }} de {{ formatNumber(total) }}</span>
                <div class="flex items-center gap-2">
                    <button @click="reload(page - 1)" :disabled="page <= 1 || loading" class="btn-secondary px-2 py-1">
                        <ChevronLeftIcon class="h-4 w-4" />
                    </button>
                    <span class="text-xs text-slate-500">Página {{ page }} / {{ totalPages }}</span>
                    <button @click="reload(page + 1)" :disabled="page >= totalPages || loading" class="btn-secondary px-2 py-1">
                        <ChevronRightIcon class="h-4 w-4" />
                    </button>
                    <select v-model.number="perPage" @change="reload(1)" class="input w-20 text-xs py-1">
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                        <option :value="200">200</option>
                    </select>
                </div>
            </div>
        </section>

        <EmailDialog
            :open="emailDialog"
            title="Enviar coincidencias por correo"
            default-subject="Coincidencias - Identificación Sectores"
            endpoint="/identificacion-sectores/coincidencias/email"
            :extra-payload="{ sector }"
            :context-label="sector ? `Filtrado por sector: ${sector}` : null"
            @close="emailDialog = false"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api/client';
import UploadCard from '../../components/UploadCard.vue';
import EmailDialog from '../../components/EmailDialog.vue';
import { formatAbsoluteDate as formatDate, formatNumber } from '../../utils/dates';
import {
    DocumentTextIcon, UserGroupIcon, MagnifyingGlassIcon,
    ArrowDownTrayIcon, PaperAirplaneIcon, ArrowPathIcon,
    InboxIcon, ChevronLeftIcon, ChevronRightIcon, ClockIcon,
} from '@heroicons/vue/24/outline';

const columns = [
    { key: 'sector', label: 'Sector' },
    { key: 'cesfam', label: 'Cesfam' },
    { key: 'prioridad', label: 'Prioridad' },
    { key: 'fecha_solicitud', label: 'Fecha solicitud' },
    { key: 'tipo_prestador', label: 'Prestador' },
    { key: 'motivo_consulta', label: 'Motivo' },
    { key: 'nombre', label: 'Nombre' },
    { key: 'apellido_paterno', label: 'Apellido P.' },
    { key: 'apellido_materno', label: 'Apellido M.' },
    { key: 'num_identificador', label: 'RUN' },
    { key: 'edad', label: 'Edad' },
    { key: 'genero', label: 'Género' },
    { key: 'email', label: 'Email' },
    { key: 'telefono', label: 'Teléfono' },
    { key: 'estado', label: 'Estado' },
];

const jobs = ref({ telesalud: null, inscritos: null });
const rows = ref([]);
const total = ref(0);
const sectores = ref([]);
const page = ref(1);
const perPage = ref(50);
const search = ref('');
const sector = ref(null);
const loading = ref(false);
const downloading = ref(false);
const emailDialog = ref(false);

const ready = computed(() => jobs.value.telesalud?.status === 'done' && jobs.value.inscritos?.status === 'done');
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
const rangeStart = computed(() => total.value === 0 ? 0 : (page.value - 1) * perPage.value + 1);
const rangeEnd = computed(() => Math.min(page.value * perPage.value, total.value));

async function loadJobs() {
    const { data } = await api.get('/identificacion-sectores/jobs');
    jobs.value = data;
}

async function reload(targetPage = 1) {
    if (!ready.value) {
        rows.value = []; total.value = 0; sectores.value = []; return;
    }
    loading.value = true;
    page.value = Math.max(1, targetPage);
    try {
        const { data } = await api.get('/identificacion-sectores/coincidencias', {
            params: { page: page.value, perPage: perPage.value, search: search.value || undefined, sector: sector.value || undefined },
        });
        rows.value = data.rows;
        total.value = data.total;
        sectores.value = data.sectores;
    } finally {
        loading.value = false;
    }
}

let searchTimer = null;
function onSearchInput() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => reload(1), 300);
}

async function refreshAll() {
    await loadJobs();
    await reload(1);
}

async function downloadXlsx() {
    downloading.value = true;
    try {
        const url = sector.value
            ? `/identificacion-sectores/coincidencias/export?sector=${encodeURIComponent(sector.value)}`
            : '/identificacion-sectores/coincidencias/export';
        const res = await api.get(url, { responseType: 'blob' });
        const cd = res.headers['content-disposition'] || '';
        const m = cd.match(/filename="([^"]+)"/);
        const filename = m ? m[1] : 'coincidencias.xlsx';
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

function sectorBadgeClass(s) {
    if (!s) return 'badge-slate';
    if (/azul/i.test(s)) return 'badge-blue';
    if (/rojo/i.test(s)) return 'badge-red';
    if (/verde/i.test(s)) return 'badge-green';
    return 'badge-amber';
}

// formatNumber y formatDate vienen de utils/dates

onMounted(refreshAll);
</script>
