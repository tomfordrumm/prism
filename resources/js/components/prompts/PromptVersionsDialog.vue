<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { VersionPayload } from '@/types/prompts';

interface Props {
    open: boolean;
    versions: VersionPayload[];
    selectedVersionId: number | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'select', version: VersionPayload): void;
}>();

const isSelected = (versionId: number) => props.selectedVersionId === versionId;
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Versions</DialogTitle>
                <DialogDescription>Select a version to view its content.</DialogDescription>
            </DialogHeader>
            <div class="max-h-96 space-y-2 overflow-y-auto">
                <button
                    v-for="version in versions"
                    :key="version.id"
                    type="button"
                    @click="emit('select', version)"
                    :class="[
                        'w-full rounded-md border px-3 py-2 text-left text-sm transition',
                        isSelected(version.id)
                            ? 'border-primary bg-primary/10 text-foreground'
                            : 'border-border/60 hover:border-primary/70',
                    ]"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-foreground">v{{ version.version }}</span>
                        <span class="text-[11px] text-muted-foreground">{{ version.created_at }}</span>
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ version.changelog || 'Initial version' }}
                    </div>
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
