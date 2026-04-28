<template>
    <div :class="['card p-5 transition', disabled && 'opacity-60 pointer-events-none']">
        <div class="flex items-start gap-3">
            <div class="h-10 w-10 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center flex-shrink-0">
                <component :is="icon" class="h-5 w-5" />
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ description }}</p>
            </div>
            <span :class="['badge-' + statusColor]" class="capitalize">{{ statusLabel }}</span>
        </div>

        <div v-if="disabled" class="mt-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-3 py-2 text-xs text-amber-800 flex items-center gap-2">
            <ArrowPathIcon class="h-3.5 w-3.5 animate-spin flex-shrink-0" />
            Esperando que termine la subida del otro archivo…
        </div>

        <div v-if="!disabled" class="mt-4 space-y-3">
            <label class="block">
                <span class="sr-only">Archivo</span>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".xlsx,.csv"
                    :disabled="uploading || isProcessing"
                    @change="onFileChange"
                    class="block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                />
            </label>

            <div v-if="selectedFile" class="text-xs text-slate-500 flex items-center justify-between gap-2">
                <span class="truncate">{{ selectedFile.name }} · {{ formatSize(selectedFile.size) }}</span>
                <button v-if="!uploading && !isProcessing" @click="clear" class="text-slate-400 hover:text-rose-600">Quitar</button>
            </div>

            <div v-if="latestJob" :class="['rounded-lg ring-1 px-3 py-2.5 text-xs space-y-1 transition', justUpdated ? 'bg-emerald-50 ring-emerald-200' : 'bg-slate-50 ring-slate-200']">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-slate-500 flex items-center gap-1.5">
                        <CheckCircleIcon v-if="justUpdated" class="h-3.5 w-3.5 text-emerald-600" />
                        {{ justUpdated ? 'Recién procesado' : 'Última carga' }}
                    </span>
                    <span class="text-slate-700 font-medium" :title="formatAbsoluteDate(latestJob.finishedAt || latestJob.createdAt)">
                        {{ formatRelativeDate(latestJob.finishedAt || latestJob.createdAt) }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-slate-700 truncate" :title="latestJob.originalFilename">{{ latestJob.originalFilename }}</span>
                    <span v-if="latestJob.rowsImported != null" class="text-emerald-700 font-medium tabular-nums whitespace-nowrap">{{ formatNumber(latestJob.rowsImported) }} filas</span>
                </div>
                <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono">
                    <span>#{{ latestJob.id?.substring(0, 8) }}</span>
                    <span>{{ formatAbsoluteDate(latestJob.finishedAt || latestJob.createdAt) }}</span>
                </div>
                <div v-if="latestJob.error" class="text-rose-600">{{ latestJob.error }}</div>
            </div>

            <div v-if="uploading || isProcessing" class="rounded-lg bg-brand-50 ring-1 ring-brand-200 px-3 py-2 text-xs text-brand-800 flex items-center gap-2">
                <ArrowPathIcon class="h-3.5 w-3.5 animate-spin flex-shrink-0" />
                <span v-if="uploading">Subiendo archivo… El procesamiento se inicia automáticamente.</span>
                <span v-else>Procesando ({{ activeJob?.status }})… puedes seguir usando la aplicación.</span>
            </div>

            <p v-if="uploadError" class="text-xs text-rose-600">{{ uploadError }}</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onUnmounted, watch } from 'vue';
import api from '../api/client';
import { formatAbsoluteDate, formatRelativeDate, formatNumber } from '../utils/dates';
import { CloudArrowUpIcon, ArrowPathIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    kind: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, required: true },
    icon: { type: [Object, Function], required: true },
    latestJob: { type: Object, default: null },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['done', 'updated', 'upload-start', 'upload-end']);

const fileInput = ref(null);
const selectedFile = ref(null);
const uploading = ref(false);
const uploadError = ref(null);
const activeJob = ref(null);
let pollHandle = null;

const isProcessing = computed(() => activeJob.value && ['pending', 'running'].includes(activeJob.value.status));

const statusLabel = computed(() => {
    if (!props.latestJob) return 'sin datos';
    return props.latestJob.status;
});
const statusColor = computed(() => {
    const s = props.latestJob?.status;
    if (s === 'done') return 'green';
    if (s === 'failed') return 'red';
    if (s === 'running' || s === 'pending') return 'amber';
    return 'slate';
});

function onFileChange(e) {
    selectedFile.value = e.target.files?.[0] || null;
    uploadError.value = null;
    // Auto-iniciar la subida apenas el usuario selecciona el archivo.
    if (selectedFile.value) {
        upload();
    }
}
function clear() {
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

async function upload() {
    if (!selectedFile.value) return;
    uploading.value = true;
    uploadError.value = null;
    emit('upload-start', props.kind);
    try {
        const fd = new FormData();
        fd.append('file', selectedFile.value);
        const { data } = await api.post(`/identificacion-sectores/upload/${props.kind}`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        activeJob.value = data.job;
        clear();
        startPolling();
    } catch (e) {
        uploadError.value = e.response?.data?.error || 'Error al subir el archivo.';
    } finally {
        uploading.value = false;
        emit('upload-end', props.kind);  // libera el otro card
    }
}

function startPolling() {
    stopPolling();
    pollHandle = setInterval(async () => {
        if (!activeJob.value) return;
        try {
            const { data } = await api.get(`/identificacion-sectores/jobs/${activeJob.value.id}`);
            activeJob.value = data.job;
            if (['done', 'failed'].includes(data.job.status)) {
                stopPolling();
                emit('done', data.job);
                emit('updated');
            }
        } catch (e) {
            // silencioso, intentaremos de nuevo
        }
    }, 1500);
}
function stopPolling() {
    if (pollHandle) clearInterval(pollHandle);
    pollHandle = null;
}
onUnmounted(stopPolling);

function formatSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

// Marca el card en verde por 8s después de que cambia el ID del job activo (= se procesó upload nuevo).
const justUpdated = ref(false);
let highlightTimer = null;
watch(() => props.latestJob?.id, (newId, oldId) => {
    if (oldId && newId && oldId !== newId) {
        justUpdated.value = true;
        if (highlightTimer) clearTimeout(highlightTimer);
        highlightTimer = setTimeout(() => { justUpdated.value = false; }, 8000);
    }
});
</script>
