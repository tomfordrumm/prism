<script setup lang="ts">
import { Button } from '@/components/ui/button';

const props = defineProps<{
    canSave: boolean;
    saveLabel: string;
}>();

const emit = defineEmits<{
    (event: 'cancel'): void;
    (event: 'save'): void;
}>();
</script>

<template>
    <div class="space-y-6">
        <slot name="sections" />
        <div class="flex items-center justify-end gap-2">
            <Button variant="outline" @click="emit('cancel')">Cancel</Button>
            <Button :disabled="!props.canSave" @click="emit('save')">
                {{ props.saveLabel }}
            </Button>
        </div>
    </div>
</template>
