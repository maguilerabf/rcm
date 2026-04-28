<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-50 px-6 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-brand-600 text-white flex items-center justify-center font-bold text-xl">R</div>
                <span class="font-semibold tracking-tight text-lg text-slate-900">RCM</span>
            </div>

            <h2 class="text-2xl font-semibold text-slate-900">Recuperar contraseña</h2>
            <p class="mt-1 text-sm text-slate-500">
                Ingresa el correo de tu cuenta y te enviaremos un enlace para restablecer la contraseña.
            </p>

            <form v-if="!sent" @submit.prevent="onSubmit" class="mt-8 space-y-5">
                <div>
                    <label class="label" for="forgot-email">Correo</label>
                    <input id="forgot-email" v-model="email" type="email"
                           name="email" autocomplete="email" inputmode="email"
                           spellcheck="false" autocapitalize="off" required
                           class="input" placeholder="tu@empresa.cl">
                </div>

                <div v-if="error" class="rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-700 flex gap-2">
                    <ExclamationCircleIcon class="h-5 w-5 flex-shrink-0" />
                    <span>{{ error }}</span>
                </div>

                <button type="submit" class="btn-primary w-full py-2.5" :disabled="loading">
                    <span v-if="!loading">Enviar enlace</span>
                    <span v-else class="inline-flex items-center gap-2">
                        <ArrowPathIcon class="h-4 w-4 animate-spin" />
                        Enviando…
                    </span>
                </button>

                <div class="text-center">
                    <RouterLink :to="{ name: 'login' }" class="text-sm text-slate-600 hover:text-slate-900 inline-flex items-center gap-1">
                        <ArrowLeftIcon class="h-4 w-4" />
                        Volver al inicio de sesión
                    </RouterLink>
                </div>
            </form>

            <div v-else class="mt-8 space-y-5">
                <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-4 text-sm text-emerald-800 flex gap-3">
                    <CheckCircleIcon class="h-5 w-5 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="font-medium">Solicitud recibida.</p>
                        <p class="mt-1">{{ message }}</p>
                        <p class="mt-2 text-xs text-emerald-700">El enlace expira en una hora. Revisa tu bandeja de entrada y la carpeta de spam.</p>
                    </div>
                </div>
                <RouterLink :to="{ name: 'login' }" class="btn-primary w-full py-2.5 inline-flex items-center justify-center gap-2 no-underline">
                    Volver al inicio de sesión
                </RouterLink>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api/client';
import {
    ExclamationCircleIcon,
    ArrowPathIcon,
    CheckCircleIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const email = ref('');
const loading = ref(false);
const error = ref(null);
const sent = ref(false);
const message = ref('');

async function onSubmit() {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await api.post('/password/request', { email: email.value }, { _silent: true });
        message.value = data.message || 'Si el correo está registrado, te llegará un enlace en los próximos minutos.';
        sent.value = true;
    } catch (e) {
        error.value = e.response?.data?.error || 'No se pudo enviar la solicitud. Intentá de nuevo.';
    } finally {
        loading.value = false;
    }
}
</script>
