<template>
    <!--
        Pingüino animado para estados de carga "amables" — se usa cuando la
        validación inicial puede tardar y queremos comunicar "estamos buscando,
        aguanta un momento" en lugar de un spinner técnico.

        El cuerpo hace un wobble lateral; las patas alternan con stagger para
        simular pasos. Todo CSS, sin dependencias.
    -->
    <div class="walking-penguin-wrap" :style="{ fontSize: size + 'px' }">
        <svg viewBox="0 0 100 100" class="walking-penguin" aria-hidden="true">
            <!-- Cuerpo (elipse blanca) -->
            <ellipse cx="50" cy="60" rx="28" ry="32" fill="#1e293b" />
            <ellipse cx="50" cy="64" rx="20" ry="24" fill="#f8fafc" />

            <!-- Cabeza -->
            <circle cx="50" cy="32" r="20" fill="#1e293b" />
            <ellipse cx="50" cy="38" rx="11" ry="9" fill="#f8fafc" />

            <!-- Ojos -->
            <circle cx="44" cy="30" r="2.5" fill="#0f172a" />
            <circle cx="56" cy="30" r="2.5" fill="#0f172a" />
            <circle cx="44.6" cy="29.4" r="0.8" fill="white" />
            <circle cx="56.6" cy="29.4" r="0.8" fill="white" />

            <!-- Pico -->
            <polygon points="46,38 54,38 50,44" fill="#f59e0b" />

            <!-- Aleta izquierda -->
            <ellipse cx="22" cy="58" rx="6" ry="14" fill="#0f172a" transform="rotate(-15 22 58)" />
            <!-- Aleta derecha -->
            <ellipse cx="78" cy="58" rx="6" ry="14" fill="#0f172a" transform="rotate(15 78 58)" />

            <!-- Patas (animadas) -->
            <ellipse class="foot foot-left"  cx="40" cy="92" rx="9" ry="4" fill="#f59e0b" />
            <ellipse class="foot foot-right" cx="60" cy="92" rx="9" ry="4" fill="#f59e0b" />
        </svg>
        <div v-if="label" class="walking-penguin-label">{{ label }}</div>
    </div>
</template>

<script setup>
defineProps({
    size: { type: Number, default: 96 },
    label: { type: String, default: '' },
});
</script>

<style scoped>
.walking-penguin-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75em;
}

.walking-penguin {
    width: 1em;
    height: 1em;
    animation: wobble 0.6s ease-in-out infinite;
    transform-origin: 50% 95%;
}

.foot {
    transform-origin: center;
    animation: step 0.6s ease-in-out infinite;
}
.foot-left  { animation-delay: 0s; }
.foot-right { animation-delay: 0.3s; }

@keyframes wobble {
    0%, 100% { transform: rotate(-3deg) translateY(0); }
    50%      { transform: rotate(3deg) translateY(-2%); }
}

@keyframes step {
    0%, 100% { transform: translateY(0) scaleY(1); }
    50%      { transform: translateY(-15%) scaleY(0.8); }
}

.walking-penguin-label {
    color: #475569;
    font-size: 0.875rem;
    text-align: center;
}
</style>
