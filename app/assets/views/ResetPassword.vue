<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-50 px-6 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-brand-600 text-white flex items-center justify-center font-bold text-xl">R</div>
                <span class="font-semibold tracking-tight text-lg text-slate-900">RCM</span>
            </div>

            <!-- VALIDANDO TOKEN -->
            <div v-if="state === 'checking'" class="flex items-center gap-3 text-slate-600">
                <ArrowPathIcon class="h-5 w-5 animate-spin" />
                <span class="text-sm">Verificando enlace…</span>
            </div>

            <!-- TOKEN INVÁLIDO / EXPIRADO -->
            <div v-else-if="state === 'invalid'" class="space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">Enlace no válido</h2>
                <div class="rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-4 text-sm text-rose-700 flex gap-3">
                    <ExclamationCircleIcon class="h-5 w-5 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="font-medium">El enlace expiró o ya fue utilizado.</p>
                        <p class="mt-1">Por seguridad cada enlace de recuperación dura una hora y solo puede usarse una vez.</p>
                    </div>
                </div>
                <RouterLink :to="{ name: 'forgot-password' }" class="btn-primary w-full py-2.5 inline-flex items-center justify-center gap-2 no-underline">
                    Pedir un enlace nuevo
                </RouterLink>
            </div>

            <!-- FORMULARIO DE NUEVA CONTRASEÑA -->
            <div v-else-if="state === 'form'">
                <h2 class="text-2xl font-semibold text-slate-900">Nueva contraseña</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Definí una nueva contraseña para <strong>{{ email }}</strong>.
                </p>

                <form @submit.prevent="onSubmit" class="mt-8 space-y-5">
                    <div>
                        <label class="label" for="reset-password">Contraseña</label>
                        <input id="reset-password" v-model="password" type="password"
                               name="new-password" autocomplete="new-password" required minlength="8"
                               class="input" placeholder="Mínimo 8 caracteres">
                    </div>
                    <div>
                        <label class="label" for="reset-password-2">Confirmar contraseña</label>
                        <input id="reset-password-2" v-model="passwordConfirm" type="password"
                               name="new-password" autocomplete="new-password" required minlength="8"
                               class="input" placeholder="Repetir">
                    </div>

                    <div v-if="error" class="rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-700 flex gap-2">
                        <ExclamationCircleIcon class="h-5 w-5 flex-shrink-0" />
                        <span>{{ error }}</span>
                    </div>

                    <button type="submit" class="btn-primary w-full py-2.5" :disabled="loading">
                        <span v-if="!loading">Cambiar contraseña</span>
                        <span v-else class="inline-flex items-center gap-2">
                            <ArrowPathIcon class="h-4 w-4 animate-spin" />
                            Guardando…
                        </span>
                    </button>
                </form>
            </div>

            <!-- ÉXITO -->
            <div v-else-if="state === 'done'" class="space-y-5">
                <h2 class="text-2xl font-semibold text-slate-900">¡Contraseña actualizada!</h2>
                <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-4 text-sm text-emerald-800 flex gap-3">
                    <CheckCircleIcon class="h-5 w-5 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="font-medium">Listo, tu contraseña fue cambiada.</p>
                        <p class="mt-1">Ya podés iniciar sesión con la nueva contraseña.</p>
                    </div>
                </div>
                <RouterLink :to="{ name: 'login' }" class="btn-primary w-full py-2.5 inline-flex items-center justify-center gap-2 no-underline">
                    Ir al inicio de sesión
                </RouterLink>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import api from '../api/client';
import {
    ExclamationCircleIcon,
    ArrowPathIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const token = String(route.params.token || '');

const state = ref('checking');         // checking | invalid | form | done
const email = ref('');
const password = ref('');
const passwordConfirm = ref('');
const loading = ref(false);
const error = ref(null);

onMounted(async () => {
    if (!token || token.length !== 64) {
        state.value = 'invalid';
        return;
    }
    try {
        const { data } = await api.get('/password/validate', { params: { token }, _silent: true });
        if (data.valid) {
            email.value = data.email;
            state.value = 'form';
        } else {
            state.value = 'invalid';
        }
    } catch (_) {
        state.value = 'invalid';
    }
});

async function onSubmit() {
    error.value = null;
    if (password.value.length < 8) {
        error.value = 'La contraseña debe tener al menos 8 caracteres.';
        return;
    }
    if (password.value !== passwordConfirm.value) {
        error.value = 'Las contraseñas no coinciden.';
        return;
    }
    loading.value = true;
    try {
        await api.post('/password/reset', { token, password: password.value }, { _silent: true });
        state.value = 'done';
    } catch (e) {
        error.value = e.response?.data?.error || 'No se pudo cambiar la contraseña.';
    } finally {
        loading.value = false;
    }
}
</script>
