<template>
    <div class="max-w-6xl mx-auto">
        <h2 class="font-semibold text-2xl text-slate-900 mb-1">Usuarios</h2>
        <p class="text-sm text-slate-500 mb-4">
            Solo el super-admin (vos) puede ver esta pantalla. Aquí aparecen las solicitudes de registro y los usuarios activos.
        </p>

        <div v-if="pendingUsers.length > 0" class="card p-4 mb-6 border-l-4 border-amber-400">
            <div class="flex items-center gap-2 mb-3">
                <ClockIcon class="h-5 w-5 text-amber-500" />
                <h3 class="font-semibold text-lg text-slate-900">Pendientes de aprobación</h3>
                <span class="badge-amber">{{ pendingUsers.length }}</span>
            </div>
            <ul class="divide-y divide-slate-100">
                <li v-for="u in pendingUsers" :key="u.id" class="py-3">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-900">{{ u.displayName }}</div>
                            <div class="text-xs text-slate-500 truncate">{{ u.email }} · {{ formatDateTime(u.createdAt) }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select v-model="approvalForm[u.id].role" class="rounded-md border-0 ring-1 ring-slate-300 text-sm px-2 py-1 focus:ring-brand-400 focus:outline-none">
                                <option value="ROLE_USER">Usuario</option>
                                <option value="ROLE_ADMIN">Admin</option>
                            </select>
                            <button @click="approve(u)" class="btn-primary text-xs px-3 py-1.5">Aprobar</button>
                            <button @click="reject(u)" class="btn-danger text-xs px-3 py-1.5">Rechazar</button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div class="card overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                <h3 class="font-semibold text-lg text-slate-900">Usuarios</h3>
                <span class="text-xs text-slate-500">{{ allUsers.length }} en total</span>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-700">
                    <tr>
                        <th class="text-left px-4 py-2.5 font-semibold">Usuario</th>
                        <th class="text-left px-4 py-2.5 font-semibold">Rol</th>
                        <th class="text-left px-4 py-2.5 font-semibold">Estado</th>
                        <th class="text-left px-4 py-2.5 font-semibold">Registrado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="u in allUsers" :key="u.id" class="hover:bg-slate-50">
                        <td class="px-4 py-2.5">
                            <div class="font-medium text-slate-900">{{ u.displayName }}</div>
                            <div class="text-xs text-slate-500">{{ u.email }}</div>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="badge-slate">{{ roleLabel(u.roles) }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <span :class="statusClass(u.status)">{{ statusLabel(u.status) }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ formatDateTime(u.createdAt) }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <button v-if="u.status === 'active' && !isSelf(u)" @click="changeRole(u)" class="text-brand-700 hover:text-brand-800 text-xs">Cambiar rol</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../api/client';
import { useAuthStore } from '../stores/auth';
import { ClockIcon } from '@heroicons/vue/24/outline';

const auth = useAuthStore();
const allUsers = ref([]);
const approvalForm = reactive({});

function statusLabel(s) { return ({ pending: 'Pendiente', active: 'Activo', rejected: 'Rechazado' })[s] ?? s; }
function statusClass(s) {
    return ({ pending: 'badge-amber', active: 'badge-green', rejected: 'badge-red' })[s] ?? 'badge-slate';
}
function roleLabel(roles) {
    if (!roles) return 'Usuario';
    if (roles.includes('ROLE_ADMIN')) return 'Admin';
    return 'Usuario';
}
function isSelf(u) { return u.email === auth.user?.email; }

function formatDateTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' });
}

const pendingUsers = computed(() => allUsers.value.filter((u) => u.status === 'pending'));

async function load() {
    const { data } = await api.get('/admin/users');
    allUsers.value = data.users;
    for (const u of data.users) {
        if (u.status === 'pending' && !approvalForm[u.id]) {
            approvalForm[u.id] = { role: 'ROLE_USER' };
        }
    }
    window.dispatchEvent(new CustomEvent('rcm:data-changed'));
}

async function approve(u) {
    const roles = [approvalForm[u.id].role];
    await api.post(`/admin/users/${u.id}/approve`, { roles });
    await load();
}

async function reject(u) {
    if (!confirm(`¿Rechazar la solicitud de ${u.email}?`)) return;
    await api.post(`/admin/users/${u.id}/reject`);
    await load();
}

async function changeRole(u) {
    const isAdmin = u.roles?.includes('ROLE_ADMIN');
    const next = isAdmin ? 'ROLE_USER' : 'ROLE_ADMIN';
    if (!confirm(`Cambiar rol de ${u.email} a ${next === 'ROLE_ADMIN' ? 'Admin' : 'Usuario'}?`)) return;
    await api.patch(`/admin/users/${u.id}`, { roles: [next] });
    await load();
}

onMounted(load);
</script>
