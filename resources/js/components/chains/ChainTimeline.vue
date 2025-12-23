<script setup lang="ts">
import Timeline from 'primevue/timeline';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ChainNodePayload } from '@/types/chains';

interface TimelineItem {
    id: number | string;
    type: 'input' | 'step' | 'output';
    name: string;
    model?: string | null;
    provider?: string | null;
    order?: number;
    rawNode?: ChainNodePayload | null;
}

const props = defineProps<{
    items: TimelineItem[];
    activeId: number | string | null;
}>();

const emit = defineEmits<{
    (event: 'select', id: number | string): void;
    (event: 'move', node: ChainNodePayload, delta: number): void;
    (event: 'delete', nodeId: number): void;
}>();

const isActive = (id: number | string) => id === props.activeId;

const selectItem = (item: TimelineItem) => {
    emit('select', item.id);
};

const moveNode = (node: ChainNodePayload | null | undefined, delta: number) => {
    if (!node) return;
    emit('move', node, delta);
};

const deleteNode = (node: ChainNodePayload | null | undefined) => {
    if (!node) return;
    emit('delete', node.id);
};
</script>

<template>
    <div class="chain-timeline overflow-y-auto pr-1">
        <Timeline :value="items" align="left" layout="vertical" class="timeline">
            <template #marker="slotProps">
                <div
                    :class="[
                        'flex h-7 w-7 items-center justify-center rounded-full border text-xs font-semibold transition',
                        isActive(slotProps.item.id)
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background text-muted-foreground',
                    ]"
                >
                    <span v-if="slotProps.item.type === 'step'">#{{ slotProps.item.order }}</span>
                    <Icon
                        v-else
                        :name="slotProps.item.type === 'input' ? 'logIn' : 'logOut'"
                        class="h-4 w-4"
                    />
                </div>
            </template>
            <template #content="slotProps">
                <div
                    v-if="slotProps.item.type === 'step'"
                    :class="[
                        'group ml-2 rounded-md border border-transparent px-3 py-2 text-sm transition hover:bg-primary/5 cursor-pointer',
                        isActive(slotProps.item.id)
                            ? 'border-l-4 border-primary bg-primary/10 shadow-sm'
                            : 'bg-background/60',
                    ]"
                    @click="selectItem(slotProps.item)"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-foreground">{{ slotProps.item.name }}</p>
                            <p class="text-[11px] text-muted-foreground">
                                {{ slotProps.item.model || 'No model' }} ·
                                {{ slotProps.item.provider || 'No provider' }}
                            </p>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 opacity-0 transition hover:opacity-100 group-hover:opacity-100"
                                >
                                    <Icon name="moreVertical" class="h-4 w-4 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-40">
                                <DropdownMenuItem @click="moveNode(slotProps.item.rawNode, -1)">
                                    Move up
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="moveNode(slotProps.item.rawNode, 1)">
                                    Move down
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    class="text-destructive"
                                    @click="deleteNode(slotProps.item.rawNode)"
                                >
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
                <div
                    v-else
                    class="ml-2 rounded-md bg-background/60 px-3 py-1 text-xs font-semibold text-muted-foreground"
                >
                    {{ slotProps.item.name }}
                </div>
            </template>
        </Timeline>
    </div>
</template>
