<script setup lang="ts">
import { nextTick, type Ref } from 'vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    nodeNameEditing: boolean;
    nodeName: string;
    activeNodeId: number | string | null;
    nodeNameInputRef: Ref<HTMLInputElement | null>;
}>();

const emit = defineEmits<{
    (event: 'update:nodeNameEditing', value: boolean): void;
    (event: 'update:nodeName', value: string): void;
    (event: 'save-node'): void;
    (event: 'toggle-available-data'): void;
}>();

const startEdit = () => {
    emit('update:nodeNameEditing', true);
    nextTick(() => {
        props.nodeNameInputRef.value?.focus();
    });
};
</script>

<template>
    <div class="flex items-center justify-between border-b border-border px-3 py-2">
        <div class="flex items-center gap-2">
            <div v-if="nodeNameEditing" class="flex items-center gap-2">
                <Input
                    :ref="nodeNameInputRef"
                    :model-value="nodeName"
                    class="h-9 w-72 text-xl font-semibold"
                    placeholder="Step name"
                    @blur="emit('save-node')"
                    @keyup.enter.prevent="emit('save-node')"
                    @update:model-value="(value) => emit('update:nodeName', value)"
                />
            </div>
            <div v-else class="flex items-center gap-2">
                <h2
                    class="cursor-pointer text-xl font-semibold text-foreground"
                    @click="startEdit"
                >
                    {{ nodeName || (activeNodeId === 'new' ? 'New step' : 'Select step') }}
                </h2>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8"
                    @click="startEdit"
                >
                    <Icon name="pencil" class="h-4 w-4 text-muted-foreground" />
                </Button>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                @click="emit('toggle-available-data')"
            >
                <Icon name="info" class="h-4 w-4 text-muted-foreground" />
            </Button>
        </div>
    </div>
</template>
