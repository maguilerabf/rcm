<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <RouterLink :to="{name:'identificacion-sectores'}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
                    <ChevronLeftIcon class="h-4 w-4" />
                    Volver a Identificación Sectores
                </RouterLink>
                <h2 class="text-xl font-semibold text-slate-900 mt-2">Historial de cargas</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Cada upload queda registrado. La carga marcada como <span class="badge-green">Activa</span>
                    es la que se usa para los cruces y la vista de duplicados.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button @click="reload" :disabled="loading" class="btn-secondary">
                    <ArrowPathIcon :class="['h-4 w-4', loading && 'animate-spin']" />
                    Recargar
                </button>
                <button @click="confirmDeleteAll = true" :disabled="!jobs.length" class="btn-danger">
                    <TrashIcon class="h-4 w-4" />
                    Borrar todo
                </button>
            </div>
        </div>

        <div v-if="loading && !jobs.length" class="card p-12 text-center text-slate-400">
            <ArrowPathIcon class="h-8 w-8 mx-auto animate-spin" />
        </div>

        <div v-else-if="!jobs.length" class="card p-12 text-center">
            <InboxIcon class="h-10 w-10 mx-auto text-slate-300 mb-3" />
            <p class="text-slate-700 font-medium">Sin cargas registradas</p>
            <p class="text-sm text-slate-500 mt-1">Cuando subas archivos, aparecerán aquí.</p>
        </div>

        <div v-else class="card overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Tipo</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Archivo</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Filas</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Estado</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Subido por</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Cuándo</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="j in jobs" :key="j.id" :class="['hover:bg-slate-50', j.isActive && 'bg-emerald-50/40']">
                        <td class="px-4 py-3">
                            <span :class="['badge', j.kind === 'telesalud' ? 'badge-blue' : 'badge-amber']">{{ j.kind }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-900 max-w-xs truncate" :title="j.originalFilename">
                            {{ j.originalFilename }}
                        </td>
                        <td class="px-4 py-3 text-right text-slate-700 tabular-nums">
                            <span v-if="j.rowsImported != null">{{ formatNumber(j.rowsImported) }}</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span :class="['badge', statusClass(j.status)]">{{ j.status }}</span>
                                <span v-if="j.isActive" class="badge-green">Activa</span>
                            </div>
                            <div v-if="j.error" class="mt-1 text-xs text-rose-600 max-w-xs truncate" :title="j.error">{{ j.error }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            <div v-if="j.createdBy">
                                <div>{{ j.createdBy.displayName }}</div>
                                <div class="text-xs text-slate-500">{{ j.createdBy.email }}</div>
                            </div>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-slate-700 whitespace-nowrap">
                            {{ formatDate(j.finishedAt || j.createdAt) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    @click="askActivate(j)"
                                    :disabled="j.isActive || j.status !== 'done' || acting === j.id"
                                    :title="j.status !== 'done' ? 'Solo cargas terminadas' : (j.isActive ? 'Ya está activa' : 'Usar esta carga')"
                                    class="btn-secondary px-2.5 py-1 text-xs"
                                >
                                    <CheckCircleIcon class="h-3.5 w-3.5" />
                                    Usar
                                </button>
                                <button
                                    @click="askDelete(j)"
                                    :disabled="acting === j.id"
                                    title="Borrar esta carga"
                                    class="btn-secondary px-2.5 py-1 text-xs hover:!bg-rose-50 hover:!text-rose-700"
                                >
                                    <TrashIcon class="h-3.5 w-3.5" />
                                    Borrar
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ConfirmDialog
            :open="!!confirmActivate"
            tone="primary"
            title="Activar esta carga"
            :message="confirmActivate ? `¿Usar la carga '${confirmActivate.originalFilename}' (${formatNumber(confirmActivate.rowsImported || 0)} filas) como activa de ${confirmActivate.kind}? Las consultas de coincidencias y duplicados pasarán a usar esta.` : ''"
            confirm-text="Activar"
            :loading="acting === confirmActivate?.id"
            @cancel="confirmActivate = null"
            @confirm="doActivate"
        />

        <ConfirmDialog
            :open="!!confirmDelete"
            tone="danger"
            title="Borrar esta carga"
            :message="confirmDelete ? `Esto elimina la carga '${confirmDelete.originalFilename}' y sus ${formatNumber(confirmDelete.rowsImported || 0)} filas asociadas en la base de datos. Si era la activa, se promueve la siguiente más reciente.` : ''"
            confirm-text="Borrar"
            :loading="acting === confirmDelete?.id"
            @cancel="confirmDelete = null"
            @confirm="doDelete"
        />

        <ConfirmDialog
            :open="confirmDeleteAll"
            tone="danger"
            title="Borrar TODAS las cargas"
            message="Esta acción elimina permanentemente todas las cargas de telesalud e inscritos, sus filas en la base de datos y los archivos en disco. El módulo de Duplicados Inscritos también quedará vacío hasta que cargues otro padrón."
            confirm-text="Borrar todo"
            require-type="BORRAR TODO"
            :loading="actingAll"
            @cancel="confirmDeleteAll = false"
            @confirm="doDeleteAll"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api/client';
import ConfirmDialog from '../../components/ConfirmDialog.vue';
import { formatRelativeDate as formatDate, formatNumber } from '../../utils/dates';
import {
    ChevronLeftIcon, ArrowPathIcon, TrashIcon, CheckCircleIcon, InboxIcon,
} from '@heroicons/vue/24/outline';

const jobs = ref([]);
const loading = ref(false);
const acting = ref(null);
const actingAll = ref(false);

const confirmActivate = ref(null);
const confirmDelete = ref(null);
const confirmDeleteAll = ref(false);

async function reload() {
    loading.value = true;
    try {
        const { data } = await api.get('/identificacion-sectores/jobs/history');
        jobs.value = data.jobs;
    } finally {
        loading.value = false;
    }
}

function askActivate(j) { confirmActivate.value = j; }
function askDelete(j) { confirmDelete.value = j; }

async function doActivate() {
    if (!confirmActivate.value) return;
    const j = confirmActivate.value;
    acting.value = j.id;
    try {
        await api.post(`/identificacion-sectores/jobs/${j.id}/activate`);
        confirmActivate.value = null;
        await reload();
    } finally {
        acting.value = null;
    }
}

async function doDelete() {
    if (!confirmDelete.value) return;
    const j = confirmDelete.value;
    acting.value = j.id;
    try {
        await api.delete(`/identificacion-sectores/jobs/${j.id}`);
        confirmDelete.value = null;
        await reload();
    } finally {
        acting.value = null;
    }
}

async function doDeleteAll() {
    actingAll.value = true;
    try {
        await api.delete('/identificacion-sectores/jobs');
        confirmDeleteAll.value = false;
        await reload();
    } finally {
        actingAll.value = false;
    }
}

function statusClass(s) {
    return ({
        done: 'badge-green',
        running: 'badge-amber',
        pending: 'badge-slate',
        failed: 'badge-red',
    })[s] || 'badge-slate';
}

// formatNumber y formatDate vienen de utils/dates (formatDate alias de formatRelativeDate)

onMounted(reload);
</script>
