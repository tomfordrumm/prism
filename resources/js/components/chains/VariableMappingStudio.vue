<script setup lang="ts">
import { computed } from 'vue';
import Tree from 'primevue/tree';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { PrimeTreeNode } from '@/composables/useAvailableDataTree';

interface MappingRowSnippet {
    prefix: string;
    match: string;
    suffix: string;
}

interface MappingRow {
    role: 'system' | 'user';
    name: string;
    mappingText: string;
    snippet: MappingRowSnippet;
}

const props = defineProps<{
    open: boolean;
    search: string;
    availableTree: PrimeTreeNode[];
    rows: MappingRow[];
    mappingTarget: { role: 'system' | 'user'; name: string } | null;
    flashKey: string | null;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'update:search', value: string): void;
    (event: 'select-target', target: { role: 'system' | 'user'; name: string } | null): void;
    (event: 'apply-mapping', payload: { role: 'system' | 'user'; name: string; value: string }): void;
    (event: 'copy-path', path: string): void;
    (event: 'tree-select', path: string): void;
    (event: 'tree-drag-start', payload: { event: DragEvent; path: string }): void;
    (event: 'mapping-drop', payload: { event: DragEvent; role: 'system' | 'user'; name: string }): void;
}>();

const show = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const search = computed({
    get: () => props.search,
    set: (value) => emit('update:search', value),
});

const onTreeSelect = (event: { node: PrimeTreeNode }) => {
    const path = event.node?.data?.path;
    if (path) {
        emit('tree-select', path);
    }
};

const setTarget = (role: 'system' | 'user', name: string) => {
    emit('select-target', { role, name });
};

const applyMapping = (role: 'system' | 'user', name: string, value: string) => {
    emit('apply-mapping', { role, name, value });
};

const handleDragStart = (event: DragEvent, path?: string) => {
    if (!path) return;
    emit('tree-drag-start', { event, path });
};

const handleDrop = (event: DragEvent, role: 'system' | 'user', name: string) => {
    emit('mapping-drop', { event, role, name });
};

const copyPath = (path?: string) => {
    if (!path) return;
    emit('copy-path', path);
};
</script>

<template>
    <Dialog :open="show" @update:open="show = $event">
        <DialogContent class="w-[90vw] sm:max-w-7xl">
            <DialogHeader>
                <DialogTitle>Variable Mapper</DialogTitle>
                <DialogDescription>Map required variables to input or previous step outputs.</DialogDescription>
            </DialogHeader>
            <div class="flex h-[70vh] gap-6">
                <div class="flex w-[360px] flex-col gap-3">
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Available Data
                    </div>
                    <Input
                        v-model="search"
                        placeholder="Search fields..."
                        class="text-sm"
                    />
                    <div class="flex-1 overflow-y-auto overflow-x-auto">
                        <Tree
                            class="w-full font-mono text-xs"
                            :value="availableTree"
                            selectionMode="single"
                            @node-select="onTreeSelect"
                        >
                            <template #default="{ node }">
                                <div class="flex items-center gap-2 border-l border-border/30 pl-2">
                                    <span class="text-sm font-sans text-foreground">{{ node.label }}</span>
                                    <span
                                        v-if="node.data?.path"
                                        class="cursor-grab text-[11px] text-muted-foreground"
                                    >
                                        {{ node.data.path }}
                                    </span>
                                    <button
                                        v-if="node.data?.path"
                                        type="button"
                                        class="ml-1 cursor-grab text-muted-foreground transition hover:text-foreground"
                                        draggable="true"
                                        title="Drag to map"
                                        @dragstart.stop="(event) => handleDragStart(event, node.data?.path)"
                                        @dragend.stop
                                    >
                                        <Icon name="move" class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        v-if="node.data?.path"
                                        type="button"
                                        class="ml-auto text-muted-foreground transition hover:text-foreground"
                                        @click.stop="copyPath(node.data.path)"
                                    >
                                        <Icon name="copy" class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </template>
                        </Tree>
                    </div>
                </div>
                <div class="hidden w-px bg-border/60 lg:block"></div>
                <div class="flex flex-1 flex-col gap-3">
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Required Mappings
                    </div>
                    <div class="flex-1 space-y-4 overflow-y-auto pr-2">
                        <button
                            v-for="row in rows"
                            :key="`${row.role}:${row.name}`"
                            type="button"
                            class="w-full rounded-md px-2 py-2 text-left transition"
                            :class="mappingTarget?.role === row.role && mappingTarget?.name === row.name ? 'bg-muted/40' : ''"
                            @click="setTarget(row.role, row.name)"
                        >
                            <div class="flex min-h-[56px] items-center gap-2 text-xs font-mono text-muted-foreground">
                                <span>{{ row.snippet.prefix }}</span>
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-900">
                                    {{ row.snippet.match }}
                                </span>
                                <span>{{ row.snippet.suffix }}</span>
                                <Icon v-if="row.mappingText" name="check" class="ml-auto h-4 w-4 text-emerald-600" />
                            </div>
                            <div class="mt-2">
                                <div
                                    class="rounded-md"
                                    @dragover.prevent
                                    @drop="(event) => handleDrop(event, row.role, row.name)"
                                >
                                    <Input
                                        :model-value="row.mappingText"
                                        placeholder="Click a data node on the left..."
                                        class="text-xs font-mono"
                                        :class="[
                                            row.mappingText ? 'text-blue-600' : '',
                                            flashKey === `${row.role}:${row.name}` ? 'ring-2 ring-emerald-300/60' : '',
                                            mappingTarget?.role === row.role && mappingTarget?.name === row.name
                                                ? 'ring-1 ring-primary/30'
                                                : '',
                                        ]"
                                        @update:model-value="(value) => applyMapping(row.role, row.name, value)"
                                        @focus="setTarget(row.role, row.name)"
                                    />
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
            <DialogFooter class="flex items-center justify-end gap-2">
                <Button variant="outline" @click="show = false">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
