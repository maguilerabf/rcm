<template>
    <div class="max-w-lg mx-auto">
        <div v-if="state === 'checking'" class="card p-6 text-slate-600 flex items-center gap-3">
            <ArrowPathIcon class="h-5 w-5 animate-spin" />Verificando solicitud…
        </div>

        <div v-else-if="state === 'invalid'" class="card p-6">
            <div class="flex items-start gap-3 text-rose-700">
                <ExclamationCircleIcon class="h-6 w-6 flex-shrink-0" />
                <div>
                    <p class="font-medium text-lg">Solicitud no encontrada</p>
                    <p class="mt-1 text-sm">El enlace ya fue procesado o no es válido. Podés revisar la lista en <RouterLink :to="{ name: 'usuarios' }" class="underline">Usuarios</RouterLink>.</p>
                </div>
            </div>
        </div>

        <div v-else-if="state === 'form'" class="card p-6">
            <div class="text-sm uppercase tracking-wider text-slate-500">Solicitud</div>
            <h2 class="mt-1 font-semibold text-2xl text-slate-900">{{ user.displayName }}</h2>
            <p class="text-slate-600">{{ user.email }}</p>
            <p class="mt-1 text-xs text-slate-500">Registrado el {{ formatDateTime(user.createdAt) }}</p>

            <div class="mt-6 space-y-3">
                <div>
                    <label class="label">Rol al aprobar</label>
                    <select v-model="form.role" class="input">
                        <option value="ROLE_USER">Usuario</option>
                        <option value="ROLE_ADMIN">Admin</option>
                    </select>
                </div>

                <div v-if="error" class="text-sm text-rose-600">{{ error }}</div>

                <div class="flex gap-2 pt-2">
                    <button @click="reject" class="btn-danger flex-1" :disabled="busy">Rechazar</button>
                    <button @click="approve" class="btn-primary flex-1" :disabled="busy">Aprobar</button>
                </div>
            </div>
        </div>

        <div v-else-if="state === 'done'" class="card p-6 text-center">
            <CheckCircleIcon class="h-12 w-12 mx-auto text-emerald-500" />
            <p class="mt-3 font-semibold text-xl text-slate-900">{{ doneMessage }}</p>
            <RouterLink :to="{ name: 'usuarios' }" class="btn-primary mt-4 inline-flex">Ver usuarios</RouterLink>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import api from '../api/client';
import { ArrowPathIcon, ExclamationCircleIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const route = useRoute();
const token = String(route.params.token || '');

const state = ref('checking');
const user = ref(null);
const error = ref(null);
const busy = ref(false);
const doneMessage = ref('');
const form = reactive({ role: 'ROLE_USER' });

function formatDateTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' });
}

onMounted(async () => {
    if (!token || !/^[a-f0-9]{64}$/.test(token)) { state.value = 'invalid'; return; }
    const actionParam = route.query.action;
    try {
        const { data } = await api.get(`/admin/users/by-token/${token}`);
        user.value = data.user;
        state.value = 'form';
        if (actionParam === 'reject') await reject();
    } catch (_) {
        state.value = 'invalid';
    }
});

async function approve() {
    busy.value = true; error.value = null;
    try {
        await api.post(`/admin/users/by-token/${token}`, { action: 'approve', roles: [form.role] });
        doneMessage.value = `${user.value.displayName} fue aprobado.`;
        state.value = 'done';
        window.dispatchEvent(new CustomEvent('rcm:data-changed'));
    } catch (e) {
        error.value = e.response?.data?.error || 'No se pudo aprobar.';
    } finally { busy.value = false; }
}

async function reject() {
    busy.value = true; error.value = null;
    try {
        await api.post(`/admin/users/by-token/${token}`, { action: 'reject' });
        doneMessage.value = `${user.value.displayName} fue rechazado.`;
        state.value = 'done';
        window.dispatchEvent(new CustomEvent('rcm:data-changed'));
    } catch (e) {
        error.value = e.response?.data?.error || 'No se pudo rechazar.';
    } finally { busy.value = false; }
}
</script>
