<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import agentRoutes from '@/routes/projects/agents';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ChainNodeModelSettings from '@/components/chains/node/ChainNodeModelSettings.vue';
import ChainNodePromptSection from '@/components/chains/node/ChainNodePromptSection.vue';
import { useAgentForm } from '@/composables/useAgentForm';
import type {
    ModelOption,
    PromptTemplateOption,
    ProviderCredentialOption,
} from '@/types/chains';
import { Bot, MessagesSquare } from 'lucide-vue-next';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface Props {
    project: ProjectPayload;
    providerCredentials: ProviderCredentialOption[];
    providerCredentialModels: Record<number, ModelOption[]>;
    promptTemplates: PromptTemplateOption[];
}

const props = defineProps<Props>();

const promptModeOptions = [
    { label: 'Template', value: 'template' as const },
    { label: 'Custom', value: 'inline' as const },
];

const {
    form,
    showAdvanced,
    providerSelectOptions,
    modelSelectOptions,
    isCustomModel,
    selectedProviderId,
    selectedModelChoice,
    templateOptions,
    versionOptions,
    systemPromptText,
    buildModelParams,
    normalizeVersionSelection,
} = useAgentForm({
    providerCredentials: props.providerCredentials,
    providerCredentialModels: props.providerCredentialModels,
    promptTemplates: props.promptTemplates,
});

const editorMode = ref<'plain' | 'markdown' | 'xml'>('plain');
const editorPreset = ref<'minimal' | 'ide'>('minimal');

const submit = () => {
    form.transform((data) => ({
        name: data.name,
        description: data.description || null,
        provider_credential_id: data.provider_credential_id,
        model_name: data.model_name,
        model_params: buildModelParams(),
        system_prompt_mode: data.system_prompt_mode,
        system_prompt_template_id:
            data.system_prompt_mode === 'template' ? data.system_prompt_template_id : null,
        system_prompt_version_id:
            data.system_prompt_mode === 'template'
                ? normalizeVersionSelection(data.system_prompt_version_id)
                : null,
        system_inline_content:
            data.system_prompt_mode === 'inline' ? data.system_inline_content : null,
        max_context_messages: data.max_context_messages,
    })).post(agentRoutes.store({ project: props.project.uuid }).url);
};
</script>

<script lang="ts">
import { ref } from 'vue';
</script>

<template>
    <ProjectLayout :project="project" title-suffix="New Agent">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                    <Bot class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-foreground">New Agent</h2>
                    <p class="text-sm text-muted-foreground">
                        Create an AI assistant with a custom system prompt.
                    </p>
                </div>
            </div>
        </div>

        <form class="mt-6 space-y-6 rounded-lg border border-border bg-card p-6" @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">
                            Name
                            <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            name="name"
                            placeholder="e.g., Customer Support Bot"
                            required
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            name="description"
                            rows="3"
                            placeholder="What this agent should do..."
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                            :disabled="form.processing"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <ChainNodePromptSection
                        title="System prompt"
                        role="system"
                        :prompt-mode-options="promptModeOptions"
                        :template-options="templateOptions"
                        :version-options="versionOptions"
                        template-placeholder="Select template"
                        version-placeholder="Latest"
                        :mode="form.system_prompt_mode"
                        :template-id="form.system_prompt_template_id"
                        :version-id="form.system_prompt_version_id"
                        :prompt-text="systemPromptText"
                        :inline-content="form.system_inline_content"
                        :editor-mode="editorMode"
                        :editor-preset="editorPreset"
                        editor-placeholder="Enter the system prompt that defines this agent's behavior..."
                        :error-inline-content="form.errors.system_inline_content"
                        :error-config="form.errors.system_prompt_template_id"
                        :variable-rows="[]"
                        :missing-mappings="[]"
                        @update:mode="form.system_prompt_mode = $event"
                        @update:template-id="form.system_prompt_template_id = $event"
                        @update:version-id="form.system_prompt_version_id = $event"
                        @update:inline-content="form.system_inline_content = $event"
                        @update:editor-mode="editorMode = $event"
                        @update:editor-preset="editorPreset = $event"
                    />
                </div>

                <div class="space-y-4">
                    <ChainNodeModelSettings
                        :form="form"
                        :provider-options="providerSelectOptions"
                        :model-options="modelSelectOptions"
                        :selected-provider-id="selectedProviderId"
                        :selected-model-choice="selectedModelChoice"
                        :is-custom-model="isCustomModel"
                        :show-advanced="showAdvanced"
                        @update:provider-credential-id="selectedProviderId = $event"
                        @update:model-choice="selectedModelChoice = $event"
                        @update:show-advanced="showAdvanced = $event"
                        @update:model-name="form.model_name = $event"
                        @update:temperature="form.temperature = $event"
                        @update:max-tokens="form.max_tokens = $event"
                    />

                    <div class="grid gap-2">
                        <Label for="max_context_messages" class="text-sm flex items-center gap-2">
                            <MessagesSquare class="h-3.5 w-3.5" />
                            Max Context Messages
                        </Label>
                        <Input
                            id="max_context_messages"
                            v-model="form.max_context_messages"
                            type="number"
                            name="max_context_messages"
                            min="1"
                            max="100"
                            :disabled="form.processing"
                        />
                        <p class="text-xs text-muted-foreground">
                            Number of recent messages to include in context (default: 20)
                        </p>
                        <InputError :message="form.errors.max_context_messages" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-border">
                <Button type="submit" :disabled="form.processing">
                    <span v-if="form.processing">Creating...</span>
                    <span v-else>Create Agent</span>
                </Button>
                <Button 
                    variant="outline" 
                    :href="agentRoutes.index({ project: project.uuid }).url" 
                    as="a"
                    :disabled="form.processing"
                >
                    Cancel
                </Button>
            </div>
        </form>
    </ProjectLayout>
</template>
