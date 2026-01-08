<script setup lang="ts">
import ChainNodePromptSection from '@/components/chains/node/ChainNodePromptSection.vue';

interface Option {
    value: number | string | null;
    label: string;
}

interface NodeForm {
    system_mode: 'template' | 'inline';
    system_prompt_template_id: number | null;
    system_prompt_version_id: number | string | null;
    system_inline_content: string;
    user_mode: 'template' | 'inline';
    user_prompt_template_id: number | null;
    user_prompt_version_id: number | string | null;
    user_inline_content: string;
    errors: Record<string, string>;
}

const props = defineProps<{
    form: NodeForm;
    promptModeOptions: Array<{ label: string; value: 'template' | 'inline' }>;
    templateOptions: Option[];
    userTemplateOptions: Option[];
    systemVersionOptions: Option[];
    userVersionOptions: Option[];
    systemPromptText: string;
    userPromptText: string;
    systemEditorMode: 'plain' | 'markdown' | 'xml';
    systemEditorPreset: 'minimal' | 'ide';
    userEditorMode: 'plain' | 'markdown' | 'xml';
    userEditorPreset: 'minimal' | 'ide';
    variableRowsSystem: VariableRow[];
    variableRowsUser: VariableRow[];
    variablesMissingMapping: { system: string[]; user: string[] };
    systemRating?: { up: number; down: number; score: number } | null;
    userRating?: { up: number; down: number; score: number } | null;
}>();

const emit = defineEmits<{
    (event: 'update:systemEditorMode', value: 'plain' | 'markdown' | 'xml'): void;
    (event: 'update:systemEditorPreset', value: 'minimal' | 'ide'): void;
    (event: 'update:userEditorMode', value: 'plain' | 'markdown' | 'xml'): void;
    (event: 'update:userEditorPreset', value: 'minimal' | 'ide'): void;
    (event: 'update:system-mode', value: 'template' | 'inline'): void;
    (event: 'update:system-template-id', value: number | null): void;
    (event: 'update:system-version-id', value: number | string | null): void;
    (event: 'update:system-inline-content', value: string): void;
    (event: 'update:user-mode', value: 'template' | 'inline'): void;
    (event: 'update:user-template-id', value: number | null): void;
    (event: 'update:user-version-id', value: number | string | null): void;
    (event: 'update:user-inline-content', value: string): void;
    (event: 'open-mapping-studio', payload: { role: 'system' | 'user'; name: string }): void;
}>();

const openMappingStudio = (role: 'system' | 'user', name: string) => {
    emit('open-mapping-studio', { role, name });
};
</script>

<template>
    <div class="space-y-4 border-b border-border/60 pb-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Prompt config</p>
        <div class="grid gap-4 lg:grid-cols-2">
            <ChainNodePromptSection
                title="System prompt"
                role="system"
                :rating="systemRating ?? null"
                :prompt-mode-options="promptModeOptions"
                :template-options="templateOptions"
                :version-options="systemVersionOptions"
                template-placeholder="Select template"
                version-placeholder="Latest"
                :mode="props.form.system_mode"
                :template-id="props.form.system_prompt_template_id"
                :version-id="props.form.system_prompt_version_id"
                :prompt-text="systemPromptText"
                :inline-content="props.form.system_inline_content"
                :editor-mode="props.systemEditorMode"
                :editor-preset="props.systemEditorPreset"
                editor-placeholder="Write system prompt with {{variables}}"
                :error-inline-content="props.form.errors['messages_config.0.inline_content']"
                :error-config="
                    props.form.errors['messages_config.0.prompt_template_id'] ||
                    props.form.errors['messages_config.0.prompt_version_id'] ||
                    props.form.errors['messages_config.0.role'] ||
                    props.form.errors.messages_config ||
                    props.form.errors.system_prompt_template_id
                "
                :variable-rows="variableRowsSystem"
                :missing-mappings="variablesMissingMapping.system"
                @update:mode="(value) => emit('update:system-mode', value)"
                @update:template-id="(value) => emit('update:system-template-id', value)"
                @update:version-id="(value) => emit('update:system-version-id', value)"
                @update:inline-content="(value) => emit('update:system-inline-content', value)"
                @update:editor-mode="(value) => emit('update:systemEditorMode', value)"
                @update:editor-preset="(value) => emit('update:systemEditorPreset', value)"
                @open-mapping-studio="({ role, name }) => openMappingStudio(role, name)"
            />

            <ChainNodePromptSection
                title="User prompt"
                role="user"
                :rating="userRating ?? null"
                :prompt-mode-options="promptModeOptions"
                :template-options="userTemplateOptions"
                :version-options="userVersionOptions"
                template-placeholder="No user prompt"
                version-placeholder="Latest"
                :mode="props.form.user_mode"
                :template-id="props.form.user_prompt_template_id"
                :version-id="props.form.user_prompt_version_id"
                :prompt-text="userPromptText"
                :inline-content="props.form.user_inline_content"
                :editor-mode="props.userEditorMode"
                :editor-preset="props.userEditorPreset"
                editor-placeholder="Write user prompt with {{variables}}"
                :error-inline-content="props.form.errors['messages_config.1.inline_content']"
                :error-config="
                    props.form.errors['messages_config.1.prompt_template_id'] ||
                    props.form.errors['messages_config.1.prompt_version_id'] ||
                    props.form.errors['messages_config.1.role'] ||
                    props.form.errors.messages_config
                "
                :variable-rows="variableRowsUser"
                :missing-mappings="variablesMissingMapping.user"
                @update:mode="(value) => emit('update:user-mode', value)"
                @update:template-id="(value) => emit('update:user-template-id', value)"
                @update:version-id="(value) => emit('update:user-version-id', value)"
                @update:inline-content="(value) => emit('update:user-inline-content', value)"
                @update:editor-mode="(value) => emit('update:userEditorMode', value)"
                @update:editor-preset="(value) => emit('update:userEditorPreset', value)"
                @open-mapping-studio="({ role, name }) => openMappingStudio(role, name)"
            />
        </div>
    </div>
</template>
