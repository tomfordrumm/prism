<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PromptEditor from '@/components/PromptEditor.vue';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import { Label } from '@/components/ui/label';
import VariableChips from '@/components/chains/node/VariableChips.vue';
import Icon from '@/components/Icon.vue';

interface Option {
    value: number | string | null;
    label: string;
}

interface VariableRow {
    name: string;
    mappingText: string;
}

const props = defineProps<{
    title: string;
    role: 'system' | 'user';
    promptModeOptions: Array<{ label: string; value: 'template' | 'inline' }>;
    templateOptions: Option[];
    versionOptions: Option[];
    templatePlaceholder: string;
    versionPlaceholder: string;
    mode: 'template' | 'inline';
    templateId: number | null;
    versionId: number | string | null;
    promptText: string;
    inlineContent: string;
    editorMode: 'plain' | 'markdown' | 'xml';
    editorPreset: 'minimal' | 'ide';
    editorPlaceholder: string;
    errorInlineContent?: string;
    errorConfig?: string;
    variableRows: VariableRow[];
    missingMappings: string[];
    rating?: { up: number; down: number; score: number } | null;
}>();

const emit = defineEmits<{
    (event: 'update:mode', value: 'template' | 'inline'): void;
    (event: 'update:template-id', value: number | null): void;
    (event: 'update:version-id', value: number | string | null): void;
    (event: 'update:inline-content', value: string): void;
    (event: 'update:editor-mode', value: 'plain' | 'markdown' | 'xml'): void;
    (event: 'update:editor-preset', value: 'minimal' | 'ide'): void;
    (event: 'open-mapping-studio', payload: { role: 'system' | 'user'; name: string }): void;
}>();

const openMappingStudio = (name: string) => {
    emit('open-mapping-studio', { role: props.role, name });
};
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <Label>{{ title }}</Label>
                <span
                    v-if="rating"
                    class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-muted px-2 py-0.5 text-[11px] text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <Icon name="thumbsUp" class="h-3 w-3" />
                        {{ rating.up }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <Icon name="thumbsDown" class="h-3 w-3" />
                        {{ rating.down }}
                    </span>
                </span>
            </div>
            <SelectButton
                :model-value="mode"
                :options="promptModeOptions"
                option-label="label"
                option-value="value"
                size="small"
                @update:model-value="(value) => emit('update:mode', value)"
            />
        </div>
        <div v-if="mode === 'template'" class="grid w-full gap-2 md:grid-cols-2">
            <Select
                :model-value="templateId"
                :options="templateOptions"
                option-label="label"
                option-value="value"
                :placeholder="templatePlaceholder"
                filter
                size="small"
                class="w-full"
                @update:model-value="(value) => emit('update:template-id', value)"
            />
            <Select
                :model-value="versionId"
                :options="versionOptions"
                option-label="label"
                option-value="value"
                :placeholder="versionPlaceholder"
                filter
                size="small"
                class="w-full"
                :disabled="!templateId"
                @update:model-value="(value) => emit('update:version-id', value)"
            />
        </div>
        <div
            v-else
            class="flex w-full items-center rounded-md border border-dashed border-border/70 bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground"
        >
            Manual input
        </div>
        <div class="relative">
            <PromptEditor
                :model-value="mode === 'template' ? promptText : inlineContent"
                :mode="editorMode"
                :preset="editorPreset"
                :read-only="mode === 'template'"
                :placeholder="editorPlaceholder"
                height="220px"
                show-controls
                @update:mode="(value) => emit('update:editor-mode', value)"
                @update:preset="(value) => emit('update:editor-preset', value)"
                @update:model-value="
                    (value) => {
                        if (mode === 'inline') {
                            emit('update:inline-content', value);
                        }
                    }
                "
            />
            <span
                v-if="mode === 'template'"
                class="pointer-events-none absolute right-3 top-9 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
            >
                Read-only template
            </span>
        </div>
        <InputError :message="errorInlineContent" />
        <InputError :message="errorConfig" />
        <div v-if="variableRows.length" class="space-y-2">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                    {{ title }} variables
                </p>
                <span v-if="missingMappings.length" class="text-[11px] text-amber-600">
                    Incomplete mappings
                </span>
            </div>
            <VariableChips :rows="variableRows" @select="openMappingStudio" />
        </div>
    </div>
</template>
