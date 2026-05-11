<template>
    <!--
        Pingüino animado para estados de carga "amables". Camina horizontalmente
        de izquierda a derecha y vuelve (flip al llegar al borde), con bob
        vertical sutil y patas alternadas. Todo CSS, sin dependencias.
    -->
    <div class="walking-penguin-wrap" :style="{ fontSize: size + 'px' }">
        <div class="walking-penguin-track">
            <svg viewBox="0 0 100 100" class="walking-penguin" aria-hidden="true">
                <!-- Cuerpo -->
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

                <!-- Aletas -->
                <ellipse cx="22" cy="58" rx="6" ry="14" fill="#0f172a" transform="rotate(-15 22 58)" />
                <ellipse cx="78" cy="58" rx="6" ry="14" fill="#0f172a" transform="rotate(15 78 58)" />

                <!-- Patas (animadas) -->
                <ellipse class="foot foot-left"  cx="40" cy="92" rx="9" ry="4" fill="#f59e0b" />
                <ellipse class="foot foot-right" cx="60" cy="92" rx="9" ry="4" fill="#f59e0b" />
            </svg>
        </div>
        <div class="walking-penguin-label">{{ label }}</div>
    </div>
</template>

<script setup>
defineProps({
    size: { type: Number, default: 96 },
    label: { type: String, default: 'Cargando…' },
});
</script>

<style scoped>
.walking-penguin-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75em;
}

/* Track horizontal: el pingüino se desplaza dentro de este ancho. */
.walking-penguin-track {
    width: 6em;
    height: 1em;
    position: relative;
}

.walking-penguin {
    width: 1em;
    height: 1em;
    position: absolute;
    top: 0;
    left: 50%;
    margin-left: -0.5em;
    animation: walk-x 4s linear infinite, bob 0.5s ease-in-out infinite;
    transform-origin: 50% 95%;
}

.foot {
    transform-origin: center;
    animation: step 0.5s ease-in-out infinite;
}
.foot-left  { animation-delay: 0s; }
.foot-right { animation-delay: 0.25s; }

/* Movimiento horizontal con flip al cambiar de dirección. */
@keyframes walk-x {
    0%   { transform: translateX(-200%) scaleX(1); }
    46%  { transform: translateX( 200%) scaleX(1); }
    50%  { transform: translateX( 200%) scaleX(-1); }
    96%  { transform: translateX(-200%) scaleX(-1); }
    100% { transform: translateX(-200%) scaleX(1); }
}

/* Bob vertical sutil (combina con la animación horizontal vía multiple animations). */
@keyframes bob {
    0%, 100% { translate: 0 0; }
    50%      { translate: 0 -8%; }
}

@keyframes step {
    0%, 100% { transform: translateY(0) scaleY(1); }
    50%      { transform: translateY(-20%) scaleY(0.75); }
}

.walking-penguin-label {
    color: #475569;
    font-size: 0.18em;
    text-align: center;
}
</style>
