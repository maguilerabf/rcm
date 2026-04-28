<template>
    <TransitionRoot as="template" :show="open">
        <Dialog as="div" class="relative z-50" @close="close">
            <TransitionChild as="template" enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100" leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild as="template" enter="ease-out duration-200" enter-from="opacity-0 translate-y-4 sm:scale-95" enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-150" leave-from="opacity-100 translate-y-0 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:scale-95">
                        <DialogPanel class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-6">
                            <DialogTitle class="text-base font-semibold text-slate-900">{{ title }}</DialogTitle>
                            <p class="mt-1 text-sm text-slate-500">El xlsx se generará y enviará en segundo plano.</p>

                            <div class="mt-5 space-y-4">
                                <div>
                                    <label class="label">Destinatarios</label>
                                    <input v-model="recipients" type="text" class="input" placeholder="alguien@correo.cl, otro@correo.cl">
                                    <p class="mt-1 text-xs text-slate-500">Separa varios correos con comas.</p>
                                </div>
                                <div>
                                    <label class="label">Asunto</label>
                                    <input v-model="subject" type="text" class="input" :placeholder="defaultSubject">
                                </div>
                                <div>
                                    <label class="label">Mensaje (opcional)</label>
                                    <textarea v-model="body" rows="3" class="input" placeholder="Adjunto el archivo solicitado…"></textarea>
                                </div>
                                <div v-if="contextLabel" class="text-xs text-slate-500">
                                    {{ contextLabel }}
                                </div>
                            </div>

                            <div v-if="error" class="mt-4 rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-2.5 text-sm text-rose-700">{{ error }}</div>
                            <div v-if="success" class="mt-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-2.5 text-sm text-emerald-700">Correo encolado para envío.</div>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button @click="close" class="btn-secondary">Cancelar</button>
                                <button @click="send" :disabled="sending" class="btn-primary">
                                    <ArrowPathIcon v-if="sending" class="h-4 w-4 animate-spin" />
                                    <PaperAirplaneIcon v-else class="h-4 w-4" />
                                    {{ sending ? 'Enviando…' : 'Enviar' }}
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { PaperAirplaneIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import api from '../api/client';

const props = defineProps({
    open: Boolean,
    title: { type: String, default: 'Enviar por correo' },
    defaultSubject: { type: String, default: 'Reporte RCM' },
    endpoint: { type: String, required: true },         // ej: '/identificacion-sectores/coincidencias/email'
    extraPayload: { type: Object, default: () => ({}) }, // ej: { sector: 'Sector Azul' }  o  { matchTypes: 'full', search: '...' }
    contextLabel: { type: String, default: null },       // texto descriptivo de filtros activos
});
const emit = defineEmits(['close']);

const recipients = ref('');
const subject = ref('');
const body = ref('');
const sending = ref(false);
const error = ref(null);
const success = ref(false);

watch(() => props.open, (open) => {
    if (open) {
        error.value = null;
        success.value = false;
        if (!subject.value) subject.value = props.defaultSubject;
    }
});

function close() {
    if (sending.value) return;
    emit('close');
}

async function send() {
    error.value = null;
    success.value = false;
    const to = recipients.value.split(',').map(s => s.trim()).filter(Boolean);
    if (!to.length) {
        error.value = 'Indica al menos un destinatario.';
        return;
    }
    sending.value = true;
    try {
        await api.post(props.endpoint, {
            to,
            subject: subject.value || props.defaultSubject,
            body: body.value,
            ...props.extraPayload,
        });
        success.value = true;
        setTimeout(() => emit('close'), 1500);
    } catch (e) {
        error.value = e.response?.data?.error || 'No se pudo encolar el envío.';
    } finally {
        sending.value = false;
    }
}
</script>
