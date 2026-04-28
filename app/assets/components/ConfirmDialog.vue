<template>
    <TransitionRoot as="template" :show="open">
        <Dialog as="div" class="relative z-50" @close="cancel">
            <TransitionChild as="template" enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100" leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild as="template" enter="ease-out duration-200" enter-from="opacity-0 translate-y-4 sm:scale-95" enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-150" leave-from="opacity-100 translate-y-0 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:scale-95">
                        <DialogPanel class="relative w-full max-w-md rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 p-6">
                            <div class="flex items-start gap-3">
                                <div :class="['h-10 w-10 rounded-full flex items-center justify-center flex-shrink-0', iconBg]">
                                    <component :is="iconComponent" :class="['h-5 w-5', iconFg]" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <DialogTitle class="text-base font-semibold text-slate-900">{{ title }}</DialogTitle>
                                    <p class="mt-1 text-sm text-slate-600 whitespace-pre-line">{{ message }}</p>
                                </div>
                            </div>

                            <div v-if="requireType" class="mt-4">
                                <label class="label" for="confirm-type">
                                    Para confirmar, escribe <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-xs">{{ requireType }}</span>
                                </label>
                                <input id="confirm-type" v-model="typed" type="text" class="input" autocomplete="off">
                            </div>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <button @click="cancel" class="btn-secondary">{{ cancelText }}</button>
                                <button @click="confirm" :disabled="!canConfirm || loading" :class="confirmBtnClass">
                                    <ArrowPathIcon v-if="loading" class="h-4 w-4 animate-spin" />
                                    <span>{{ loading ? 'Procesando…' : confirmText }}</span>
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
import { ref, computed, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { ExclamationTriangleIcon, TrashIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    open: Boolean,
    title: { type: String, default: '¿Confirmar acción?' },
    message: { type: String, default: '' },
    confirmText: { type: String, default: 'Confirmar' },
    cancelText: { type: String, default: 'Cancelar' },
    tone: { type: String, default: 'danger' }, // danger | warning | primary
    requireType: { type: String, default: null }, // si se setea, el usuario debe escribirlo
    loading: Boolean,
});
const emit = defineEmits(['confirm', 'cancel']);

const typed = ref('');
watch(() => props.open, (open) => { if (open) typed.value = ''; });

const canConfirm = computed(() => !props.requireType || typed.value === props.requireType);

const iconComponent = computed(() => props.tone === 'danger' ? TrashIcon : ExclamationTriangleIcon);
const iconBg = computed(() => ({
    danger: 'bg-rose-100',
    warning: 'bg-amber-100',
    primary: 'bg-brand-100',
}[props.tone] || 'bg-rose-100'));
const iconFg = computed(() => ({
    danger: 'text-rose-700',
    warning: 'text-amber-700',
    primary: 'text-brand-700',
}[props.tone] || 'text-rose-700'));
const confirmBtnClass = computed(() => {
    const base = 'btn px-4 py-2 text-white';
    return ({
        danger: `${base} bg-rose-600 hover:bg-rose-700 disabled:opacity-50`,
        warning: `${base} bg-amber-600 hover:bg-amber-700 disabled:opacity-50`,
        primary: `${base} bg-brand-600 hover:bg-brand-700 disabled:opacity-50`,
    }[props.tone]);
});

function confirm() { if (canConfirm.value && !props.loading) emit('confirm'); }
function cancel() { if (!props.loading) emit('cancel'); }
</script>
