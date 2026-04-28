<template>
    <button type="button" :class="cls" @click="$emit('click')">
        <slot />
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    active: Boolean,
    tone: { type: String, default: 'slate' },
});
defineEmits(['click']);

const cls = computed(() => {
    const base = 'inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium transition ring-1';
    if (!props.active) {
        return `${base} bg-white text-slate-600 ring-slate-200 hover:bg-slate-50`;
    }
    const map = {
        slate: 'bg-slate-900 text-white ring-slate-900',
        red: 'bg-rose-600 text-white ring-rose-600',
        amber: 'bg-amber-500 text-white ring-amber-500',
        brand: 'bg-brand-600 text-white ring-brand-600',
    };
    return `${base} ${map[props.tone] || map.slate}`;
});
</script>
