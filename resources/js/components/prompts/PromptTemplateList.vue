<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { DraftTemplate, TemplateListItem } from '@/types/prompts';

interface Props {
    items: Array<TemplateListItem | DraftTemplate>;
    selectedTemplateId: number | string | null;
    search: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (event: 'update:search', value: string): void;
    (event: 'select', id: number | string): void;
    (event: 'create-draft'): void;
}>();

const searchValue = computed({
    get: () => props.search,
    set: (value) => emit('update:search', value),
});
</script>

<template>
    <div class="flex h-full flex-col border-r border-border/70 bg-white">
        <div class="border-b border-border/60 px-4 py-4">
            <div class="flex items-center gap-2">
                <Input
                    v-model="searchValue"
                    type="search"
                    placeholder="Search prompts..."
                    class="w-full text-sm"
                />
                <Button size="sm" variant="outline" @click="$emit('create-draft')">New</Button>
            </div>
        </div>

        <div class="flex-1 space-y-1 overflow-y-auto">
            <button
                v-for="template in items"
                :key="template.id"
                type="button"
                @click="$emit('select', template.id)"
                :class="[
                    'w-full border-l-2 px-4 py-3 text-left transition',
                    selectedTemplateId === template.id
                        ? 'border-primary bg-white'
                        : 'border-l-transparent hover:bg-white/70',
                ]"
            >
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-foreground">{{ template.name }}</span>
                    <span class="text-[11px] font-medium text-muted-foreground">
                        v{{ template.latest_version ?? 0 }}
                    </span>
                </div>
                <p class="mt-1 line-clamp-1 text-xs text-muted-foreground">
                    {{ template.description || 'No description' }}
                </p>
            </button>

            <div
                v-if="items.length === 0"
                class="px-4 py-4 text-center text-sm text-muted-foreground"
            >
                No templates found.
            </div>
        </div>
    </div>
</template>
