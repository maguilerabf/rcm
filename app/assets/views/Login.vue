<template>
    <div class="min-h-screen flex">
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-brand-700 via-brand-800 to-slate-900 relative overflow-hidden">
            <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 20%, white 0, transparent 50%), radial-gradient(circle at 80% 80%, white 0, transparent 40%);"></div>
            <div class="relative z-10 flex flex-col justify-between p-12 text-white w-full">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-white/10 backdrop-blur flex items-center justify-center font-bold text-xl ring-1 ring-white/20">R</div>
                    <span class="font-semibold tracking-tight text-lg">RCM</span>
                </div>
                <div>
                    <h1 class="text-4xl font-bold tracking-tight leading-tight">
                        Cruce inteligente de<br>reportes operacionales
                    </h1>
                    <p class="mt-4 text-brand-100/80 text-base max-w-md">
                        Importa tus archivos, identifica sectores, descarga resultados y compártelos por correo en segundos.
                    </p>
                </div>
                <p class="text-xs text-brand-100/60">
                    Datos protegidos por Ley 19.628 y Ley 20.584. Acceso autorizado únicamente.
                </p>
            </div>
        </div>

        <div class="flex-1 flex items-center justify-center px-6 py-12 bg-slate-50">
            <div class="w-full max-w-md">
                <div class="lg:hidden mb-8 flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-brand-600 text-white flex items-center justify-center font-bold text-xl">R</div>
                    <span class="font-semibold tracking-tight text-lg text-slate-900">RCM</span>
                </div>

                <div class="inline-flex p-1 rounded-lg bg-slate-200/70 mb-6 text-sm font-medium">
                    <button
                        type="button"
                        @click="setMode('login')"
                        :class="['px-4 py-1.5 rounded-md transition', mode === 'login' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900']"
                    >Iniciar sesión</button>
                    <button
                        type="button"
                        @click="setMode('register')"
                        :class="['px-4 py-1.5 rounded-md transition', mode === 'register' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900']"
                    >Crear cuenta</button>
                </div>

                <h2 class="text-2xl font-semibold text-slate-900">
                    {{ mode === 'login' ? 'Bienvenido de vuelta' : 'Crear nueva cuenta' }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ mode === 'login' ? 'Accede con tu cuenta corporativa.' : 'Completa los datos para registrarte.' }}
                </p>

                <!-- LOGIN -->
                <form v-if="mode === 'login'" @submit.prevent="onLogin" method="post" action="/api/login" class="mt-8 space-y-5">
                    <div>
                        <label class="label" for="login-email">Correo</label>
                        <input id="login-email" v-model="loginForm.email" type="email"
                               name="email" autocomplete="username" inputmode="email"
                               spellcheck="false" autocapitalize="off" required
                               class="input" placeholder="tu@empresa.cl">
                    </div>
                    <div>
                        <label class="label" for="login-password">Contraseña</label>
                        <input id="login-password" v-model="loginForm.password" type="password"
                               name="password" autocomplete="current-password" required
                               class="input" placeholder="••••••••">
                    </div>

                    <div v-if="auth.error" class="rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-700 flex gap-2">
                        <ExclamationCircleIcon class="h-5 w-5 flex-shrink-0" />
                        <span>{{ auth.error }}</span>
                    </div>

                    <button type="submit" class="btn-primary w-full py-2.5" :disabled="auth.loading">
                        <span v-if="!auth.loading">Entrar</span>
                        <span v-else class="inline-flex items-center gap-2">
                            <ArrowPathIcon class="h-4 w-4 animate-spin" />
                            Verificando…
                        </span>
                    </button>
                </form>

                <!-- REGISTER -->
                <form v-else @submit.prevent="onRegister" method="post" action="/api/register" class="mt-8 space-y-5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="reg-first">Nombre</label>
                            <input id="reg-first" v-model="registerForm.firstName" type="text"
                                   name="given-name" autocomplete="given-name" required
                                   class="input" placeholder="Mauricio">
                        </div>
                        <div>
                            <label class="label" for="reg-last">Apellido</label>
                            <input id="reg-last" v-model="registerForm.lastName" type="text"
                                   name="family-name" autocomplete="family-name" required
                                   class="input" placeholder="Aguilera">
                        </div>
                    </div>
                    <div>
                        <label class="label" for="reg-email">Correo</label>
                        <input id="reg-email" v-model="registerForm.email" type="email"
                               name="email" autocomplete="email" inputmode="email"
                               spellcheck="false" autocapitalize="off" required
                               class="input" placeholder="tu@empresa.cl">
                    </div>
                    <div>
                        <label class="label" for="reg-password">Contraseña</label>
                        <input id="reg-password" v-model="registerForm.password" type="password"
                               name="new-password" autocomplete="new-password" required minlength="8"
                               class="input" placeholder="Mínimo 8 caracteres">
                        <p class="mt-1 text-xs text-slate-500">Al menos 8 caracteres.</p>
                    </div>

                    <div v-if="auth.error" class="rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-700 flex gap-2">
                        <ExclamationCircleIcon class="h-5 w-5 flex-shrink-0" />
                        <span>{{ auth.error }}</span>
                    </div>

                    <button type="submit" class="btn-primary w-full py-2.5" :disabled="auth.loading">
                        <span v-if="!auth.loading">Crear cuenta</span>
                        <span v-else class="inline-flex items-center gap-2">
                            <ArrowPathIcon class="h-4 w-4 animate-spin" />
                            Creando…
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { ExclamationCircleIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const mode = ref(route.query.mode === 'register' ? 'register' : 'login');

const loginForm = reactive({ email: '', password: '' });
const registerForm = reactive({ firstName: '', lastName: '', email: '', password: '' });

function setMode(next) {
    if (mode.value === next) return;
    mode.value = next;
    auth.error = null;
}

async function goNext() {
    const redirect = route.query.redirect || { name: 'identificacion-sectores' };
    router.push(redirect);
}

async function onLogin() {
    if (await auth.login(loginForm.email, loginForm.password)) {
        await goNext();
    }
}

async function onRegister() {
    if (await auth.register(registerForm)) {
        await goNext();
    }
}
</script>
