<script setup lang="ts">
import Tree from 'primevue/tree';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PrimeTreeNode } from '@/composables/useAvailableDataTree';

defineProps<{
    open: boolean;
    orderIndex: number;
    search: string;
    tree: PrimeTreeNode[];
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'update:search', value: string): void;
    (event: 'select', path: string): void;
}>();

const onTreeSelect = (event: { node: PrimeTreeNode }) => {
    const path = event.node?.data?.path;
    if (path) {
        emit('select', path);
    }
};
</script>

<template>
    <div
        v-if="open"
        class="absolute right-0 top-0 z-10 h-full w-full max-w-sm border-l border-border/60 bg-background/95 p-3 backdrop-blur"
    >
        <div class="flex items-center justify-between border-b border-border/60 pb-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Available data</span>
            <Button variant="ghost" size="icon" class="h-7 w-7" @click="emit('update:open', false)">
                <Icon name="x" class="h-4 w-4 text-muted-foreground" />
            </Button>
        </div>
        <div class="mt-2 text-[11px] text-muted-foreground">Order {{ orderIndex }}</div>
        <Input
            :model-value="search"
            placeholder="Search fields..."
            class="mt-3"
            @update:model-value="(value) => emit('update:search', value)"
        />
        <Tree
            class="mt-3 w-full font-mono text-xs"
            :value="tree"
            selectionMode="single"
            @node-select="onTreeSelect"
        >
            <template #default="{ node }">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-sans text-foreground">{{ node.label }}</span>
                    <span v-if="node.data?.path" class="text-[11px] text-muted-foreground">
                        {{ node.data.path }}
                    </span>
                </div>
            </template>
        </Tree>
        <div class="mt-4 flex justify-end">
            <Button variant="outline" size="sm" @click="emit('update:open', false)">
                Close
            </Button>
        </div>
    </div>
</template>
