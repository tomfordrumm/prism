<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex items-center justify-start gap-1 w-full">
        <div
            class="flex aspect-square size-8 items-center justify-center rounded-md text-sidebar-primary-foreground"
        >
            <AppLogoIcon
                class="size-5 fill-current text-white dark:text-black"
            />
        </div>
        <div class="text-left text-lg">
            <span class="mb-0.5 truncate leading-tight font-semibold"
                >PRISM</span
            >
        </div>
    </div>
</template>
