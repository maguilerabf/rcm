<template>
    <div class="card overflow-hidden">
        <button type="button" @click="open = !open" class="w-full px-5 py-4 flex items-start gap-3 text-left hover:bg-slate-50 transition">
            <div :class="['mt-0.5 h-2 w-2 rounded-full flex-shrink-0', dotClass]"></div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span :class="['badge', badgeClass]">{{ matchLabel }}</span>
                    <span class="text-sm text-slate-500">{{ group.criterio }}</span>
                </div>
                <div class="mt-1.5 text-sm font-medium text-slate-900 truncate">
                    {{ headline }}
                </div>
                <div class="mt-0.5 text-xs text-slate-500">
                    <span class="font-medium text-slate-700">{{ group.distinctRuns }}</span> RUN distintos ·
                    {{ group.rowCount }} registros
                </div>
            </div>
            <ChevronDownIcon :class="['h-5 w-5 text-slate-400 transition flex-shrink-0', open && 'rotate-180']" />
        </button>

        <div v-if="open" class="border-t border-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">RUN</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Nombres</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Apellido P.</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Apellido M.</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Fecha nac.</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Sector</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, rowIdx) in group.rows" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-2 font-mono text-slate-900 whitespace-nowrap">{{ row.run_dv }}</td>
                            <td :class="['px-4 py-2 whitespace-nowrap', cellClass('nombres', row.nombres, rowIdx)]">{{ row.nombres }}</td>
                            <td :class="['px-4 py-2 whitespace-nowrap', cellClass('apellido_paterno', row.apellido_paterno, rowIdx)]">{{ row.apellido_paterno }}</td>
                            <td :class="['px-4 py-2 whitespace-nowrap', cellClass('apellido_materno', row.apellido_materno, rowIdx)]">{{ row.apellido_materno || '—' }}</td>
                            <td :class="['px-4 py-2 whitespace-nowrap', cellClass('fecha_nacimiento', row.fecha_nacimiento, rowIdx)]">{{ row.fecha_nacimiento || '—' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span :class="['badge', sectorClass(row.sector)]">{{ row.sector || '—' }}</span>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ row.estado || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    group: { type: Object, required: true },
});
const open = ref(false);

const matchLabel = computed(() => ({
    full: 'Coincidencia completa',
    partial: 'Coincidencia parcial',
}[props.group.matchType] || props.group.matchType));

const badgeClass = computed(() => props.group.matchType === 'full' ? 'badge-red' : 'badge-amber');
const dotClass = computed(() => props.group.matchType === 'full' ? 'bg-rose-500' : 'bg-amber-500');

const headline = computed(() => {
    const k = props.group.key;
    const parts = [
        k.nombres, k.apellido_paterno, k.apellido_materno,
    ].filter(Boolean).join(' ');
    return parts + (k.fecha_nacimiento ? ` · ${k.fecha_nacimiento}` : '');
});

/**
 * Para una celda dada, devuelve la clase. En PARTIAL, si el valor es distinto
 * (ignorando tildes/case/Y-I) al de la primera fila del grupo, se resalta en ámbar.
 * Solo se resaltan los campos relevantes (nombres / apellidos / fecha).
 */
function cellClass(field, value, rowIdx) {
    if (props.group.matchType !== 'partial') return 'text-slate-700';
    if (rowIdx === 0) return 'text-slate-700'; // primera fila = referencia
    if (!['nombres', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento'].includes(field)) return 'text-slate-700';

    const ref = normalize(props.group.rows[0][field]);
    const cur = normalize(value);
    if (ref !== cur) return 'bg-amber-50 text-amber-900 font-medium';
    return 'text-slate-700';
}

function normalize(v) {
    if (v === null || v === undefined) return '';
    return String(v)
        .normalize('NFD').replace(/[̀-ͯ]/g, '') // quitar tildes
        .toUpperCase()
        .trim()
        .replace(/Y/g, 'I');
}

function sectorClass(s) {
    if (!s) return 'badge-slate';
    if (/azul/i.test(s)) return 'badge-blue';
    if (/rojo/i.test(s)) return 'badge-red';
    if (/verde/i.test(s)) return 'badge-green';
    return 'badge-slate';
}
</script>
