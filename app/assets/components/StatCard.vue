<template>
    <div class="card p-4 flex items-center gap-4">
        <div :class="['h-10 w-10 rounded-lg flex items-center justify-center flex-shrink-0', toneClass]">
            <component :is="icon" class="h-5 w-5" />
        </div>
        <div class="min-w-0">
            <div class="text-xs text-slate-500 uppercase tracking-wide font-medium">{{ label }}</div>
            <div class="text-2xl font-semibold text-slate-900 mt-0.5">{{ formatted }}</div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], default: 0 },
    icon: { type: [Object, Function], required: true },
    tone: { type: String, default: 'slate' },
});

const toneClass = computed(() => ({
    brand: 'bg-brand-50 text-brand-700',
    red: 'bg-rose-50 text-rose-700',
    amber: 'bg-amber-50 text-amber-700',
    green: 'bg-emerald-50 text-emerald-700',
    slate: 'bg-slate-100 text-slate-700',
})[props.tone] || 'bg-slate-100 text-slate-700');

const formatted = computed(() => {
    if (typeof props.value === 'number') {
        return new Intl.NumberFormat('es-CL').format(props.value);
    }
    return props.value;
});
</script>
