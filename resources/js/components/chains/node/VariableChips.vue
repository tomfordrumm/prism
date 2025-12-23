<script setup lang="ts">
import Icon from '@/components/Icon.vue';

interface VariableRow {
    name: string;
    mappingText: string;
}

const props = defineProps<{
    rows: VariableRow[];
}>();

const emit = defineEmits<{
    (event: 'select', name: string): void;
}>();
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="row in props.rows"
            :key="row.name"
            type="button"
            class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-xs font-semibold text-foreground transition hover:border-primary/60 hover:bg-primary/5"
            :class="!row.mappingText ? 'border-rose-300' : 'border-border/60'"
            @click="emit('select', row.name)"
        >
            <span>{{ row.name }}</span>
            <span v-if="row.mappingText" class="text-xs font-normal text-muted-foreground">
                {{ row.mappingText }}
            </span>
            <Icon v-if="!row.mappingText" name="unlink" class="h-3.5 w-3.5 text-amber-600" />
        </button>
    </div>
</template>
