<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import type { TemplateVariable } from '@/types/prompts';

interface Props {
    open: boolean;
    variables: TemplateVariable[];
}

defineProps<Props>();
const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Variables</DialogTitle>
                <DialogDescription>
                    Extracted automatically from <code v-pre>{{ variable }}</code> placeholders.
                </DialogDescription>
            </DialogHeader>
            <div v-if="variables.length" class="mt-3 space-y-2">
                <div
                    v-for="variable in variables"
                    :key="variable.name"
                    class="rounded-md border border-border/60 px-3 py-2 text-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-foreground">{{ variable.name }}</span>
                        <span class="text-xs uppercase text-muted-foreground">{{ variable.type ?? 'string' }}</span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ variable.description || 'No description' }}
                    </p>
                </div>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">No variables detected.</p>
            <DialogFooter class="mt-4">
                <Button variant="outline" size="sm" @click="emit('update:open', false)">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
