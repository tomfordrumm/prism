<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        width="200"
        height="200"
        viewBox="0 0 200 200"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
    >
        <!-- Внешний контур перевернутой призмы -->
        <path
            d="M40 60L160 60L100 160L40 60Z"
            stroke="black"
            stroke-width="8"
            stroke-linejoin="round"
        />

        <!-- Внутренние грани (создающие объем и 4D-эффект) -->
        <path
            d="M40 60L100 110L160 60"
            stroke="black"
            stroke-width="4"
            stroke-linejoin="round"
            opacity="0.8"
        />
        <path
            d="M100 110V160"
            stroke="black"
            stroke-width="4"
            stroke-linecap="round"
            opacity="0.8"
        />

        <!-- "Ядро" внутри - точка фокуса/синтеза -->
        <path d="M100 95L115 110L100 125L85 110L100 95Z" fill="black" />

        <!-- Дополнительные технологичные акценты по углам -->
        <circle cx="40" cy="60" r="3" fill="black" />
        <circle cx="160" cy="60" r="3" fill="black" />
        <circle cx="100" cy="160" r="3" fill="black" />
    </svg>
</template>
