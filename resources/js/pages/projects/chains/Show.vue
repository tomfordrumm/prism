<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import chainRoutes from '@/routes/projects/chains';
import providerCredentialRoutes from '@/routes/provider-credentials';
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, toRefs, watch } from 'vue';

import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { Button } from '@/components/ui/button';
import ChainTimeline from '@/components/chains/ChainTimeline.vue';
import AvailableDataPanel from '@/components/chains/AvailableDataPanel.vue';
import VariableMappingStudio from '@/components/chains/VariableMappingStudio.vue';
import ChainDescriptionModal from '@/components/chains/ChainDescriptionModal.vue';
import ChainRunPanel from '@/components/chains/ChainRunPanel.vue';
import ProviderCredentialModal from '@/components/providers/ProviderCredentialModal.vue';
import ChainNodeOutputSection from '@/components/chains/node/ChainNodeOutputSection.vue';
import ChainNodePromptConfig from '@/components/chains/node/ChainNodePromptConfig.vue';
import ChainNodeSettingsPanel from '@/components/chains/node/ChainNodeSettingsPanel.vue';
import ChainNodeModelSettings from '@/components/chains/node/ChainNodeModelSettings.vue';
import ChainHeader from '@/components/chains/ChainHeader.vue';
import ChainNodeEditorHeader from '@/components/chains/node/ChainNodeEditorHeader.vue';
import { useAvailableDataTree } from '@/composables/useAvailableDataTree';
import { useChainNodeForm } from '@/composables/useChainNodeForm';
import { useMappingDragDrop } from '@/composables/useMappingDragDrop';
import { useVariableMapping } from '@/composables/useVariableMapping';
import type {
    ChainNodePayload,
    ContextSample,
    ModelOption,
    PromptTemplateOption,
    ProviderCredentialOption,
} from '@/types/chains';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface ChainPayload {
    id: number;
    name: string;
    description?: string | null;
}

interface Option {
    value: number | string;
    label: string;
    provider?: string;
}

interface Props {
    project: ProjectPayload;
    chain: ChainPayload;
    nodes: ChainNodePayload[];
    providerCredentials: ProviderCredentialOption[];
    providerCredentialModels: Record<number, ModelOption[]>;
    providerOptions: Option[];
    promptTemplates: PromptTemplateOption[];
    datasets: Option[];
    contextSample: ContextSample;
}

const props = defineProps<Props>();
const { nodes, providerCredentials, providerCredentialModels, promptTemplates, contextSample } = toRefs(props);

const templateOptions = computed(() =>
    props.promptTemplates.map((template) => ({
        value: template.id,
        label: template.name,
    })),
);

const userTemplateOptions = computed(() => [
    { value: null, label: 'No user prompt' },
    ...templateOptions.value,
]);

const {
    nodeForm,
    editingNodeId,
    currentOrderIndex,
    providerSelectOptions,
    modelSelectOptions,
    isCustomModel,
    selectedProviderId,
    selectedModelChoice,
    systemVersionOptions,
    userVersionOptions,
    systemPromptText,
    userPromptText,
    openCreateDrawer: resetNodeForm,
    openEditDrawer: loadNodeForm,
    buildMessagesConfig,
    buildModelParams,
    normalizeVersionSelection,
} = useChainNodeForm({
    nodes,
    providerCredentials,
    providerCredentialModels,
    promptTemplates,
});

const chainForm = useForm({
    name: props.chain.name,
    description: props.chain.description ?? '',
});

const chainNameEditing = ref(false);
const chainNameInputRef = ref<HTMLInputElement | null>(null);
const descriptionModalOpen = ref(false);
const chainNameBeforeEdit = ref(props.chain.name);

const nodeNameEditing = ref(false);
const nodeNameInputRef = ref<HTMLInputElement | null>(null);
const showAdvancedModelSettings = ref(false);
const showAvailableData = ref(false);
const mappingStudioOpen = ref(false);
const systemEditorMode = ref<'plain' | 'markdown' | 'xml'>('plain');
const systemEditorPreset = ref<'minimal' | 'ide'>('minimal');
const userEditorMode = ref<'plain' | 'markdown' | 'xml'>('plain');
const userEditorPreset = ref<'minimal' | 'ide'>('minimal');
const toast = useToast();
const availableDataSearch = ref('');
const mappingStudioSearch = ref('');

const updateChain = () => {
    chainForm
        .transform((data) => ({
            name: data.name,
            description: data.description || null,
        }))
        .put(chainRoutes.update({ project: props.project.uuid, chain: props.chain.id }).url, {
            preserveScroll: true,
        });
};

const startNameEdit = () => {
    chainNameEditing.value = true;
    chainNameBeforeEdit.value = chainForm.name;
    nextTick(() => {
        chainNameInputRef.value?.focus();
        chainNameInputRef.value?.select();
    });
};

const commitChainName = () => {
    const trimmed = chainForm.name.trim();
    if (!trimmed) {
        chainForm.name = props.chain.name;
        chainNameEditing.value = false;
        return;
    }

    chainForm.name = trimmed;
    chainNameEditing.value = false;
    if (trimmed !== chainNameBeforeEdit.value) {
        updateChain();
    }
};

const openDescriptionEditor = () => {
    descriptionModalOpen.value = true;
};

const saveDescription = () => {
    updateChain();
    descriptionModalOpen.value = false;
};

const sortedNodes = computed(() => [...props.nodes].sort((a, b) => a.order_index - b.order_index));
const activeNodeId = ref<number | 'new' | 'input' | 'output' | null>(null);
const hasProviderCredentials = computed(() => props.providerCredentials.length > 0);
const promptModeOptions = [
    { label: 'Template', value: 'template' },
    { label: 'Custom', value: 'inline' },
];

const { filteredAvailableDataTree, filteredStudioDataTree, previousSteps } = useAvailableDataTree({
    contextSample,
    currentOrderIndex,
    availableDataSearch,
    mappingStudioSearch,
});

const timelineItems = computed(() => {
    const items: Array<{
        id: number | string;
        type: 'input' | 'step' | 'output';
        name: string;
        model?: string | null;
        provider?: string | null;
        order?: number;
        rawNode?: ChainNodePayload;
    }> = [
        { id: 'input', type: 'input', name: 'Input' },
        ...sortedNodes.value.map((node) => ({
            id: node.id,
            type: 'step',
            name: node.name,
            model: node.model_name,
            provider: node.provider_credential?.name ?? null,
            order: node.order_index,
            rawNode: node,
        })),
        { id: 'output', type: 'output', name: 'Output' },
    ];

    return items;
});
const {
    mappingTarget,
    variableRowsSystem,
    variableRowsUser,
    variablesMissingMapping,
    mappingRowsStudio,
    applyMappingText,
} = useVariableMapping({
    promptTemplates,
    previousSteps,
    nodeForm,
    systemPromptText,
    userPromptText,
});

const {
    mappingFlashKey,
    insertPlaceholder,
    onStudioTreeSelect,
    handleTreeDragStart,
    handleMappingDrop,
    copyPath,
} = useMappingDragDrop({
    mappingTarget,
    applyMappingText,
});

const openMappingStudio = (role?: 'system' | 'user', name?: string) => {
    mappingStudioOpen.value = true;
    mappingTarget.value = role && name ? { role, name } : null;
};

const openCreateDrawer = () => {
    nodeNameEditing.value = false;
    resetNodeForm();
};

const openEditDrawer = (node: ChainNodePayload) => {
    nodeNameEditing.value = false;
    loadNodeForm(node);
};

watch(
    sortedNodes,
    (nodes) => {
        if (activeNodeId.value === 'new') {
            return;
        }

        const exists = nodes.some((node) => node.id === activeNodeId.value);
        if (!exists) {
            activeNodeId.value = nodes[0]?.id ?? null;
        }
    },
    { immediate: false },
);

watch(
    activeNodeId,
    (id) => {
        if (id === 'new') {
            openCreateDrawer();
            return;
        }

        if (id === 'input' || id === 'output' || id === null) {
            editingNodeId.value = null;
            return;
        }

        const node = sortedNodes.value.find((item) => item.id === id);
        if (node) {
            openEditDrawer(node);
        }
    },
    { immediate: true },
);

const saveNode = () => {
    if (nodeForm.system_mode === 'template' && !nodeForm.system_prompt_template_id) {
        nodeForm.setError('system_prompt_template_id', 'System prompt template is required');
        return;
    }
    nodeForm.clearErrors('system_prompt_template_id');

    nodeForm
        .transform((data) => {
            const payload = {
                name: data.name,
                provider_credential_id: data.provider_credential_id ? Number(data.provider_credential_id) : null,
                model_name: data.model_name,
                model_params: buildModelParams({ temperature: data.temperature, max_tokens: data.max_tokens }),
                messages_config: buildMessagesConfig(
                    data.system_mode,
                    data.system_prompt_template_id ? Number(data.system_prompt_template_id) : null,
                    normalizeVersionSelection(data.system_prompt_version_id),
                    data.system_inline_content,
                    data.system_variables,
                    data.user_mode,
                    data.user_prompt_template_id ? Number(data.user_prompt_template_id) : null,
                    normalizeVersionSelection(data.user_prompt_version_id),
                    data.user_inline_content,
                    data.user_variables,
                ),
                output_schema_definition: data.output_schema_definition || null,
                stop_on_validation_error: data.stop_on_validation_error,
                order_index: Number(data.order_index) || 1,
            };
            return payload;
        })
        [editingNodeId.value ? 'put' : 'post'](
            editingNodeId.value
                ? chainRoutes.nodes.update({
                      project: props.project.uuid,
                      chain: props.chain.id,
                      chainNode: editingNodeId.value,
                  }).url
                : chainRoutes.nodes.store({
                      project: props.project.uuid,
                      chain: props.chain.id,
                  }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Step saved',
                        detail: 'Chain step saved successfully.',
                        life: 3000,
                    });
                },
                onError: () => {
                    toast.add({
                        severity: 'error',
                        summary: 'Save failed',
                        detail: 'Please fix the errors and try again.',
                        life: 4000,
                    });
                },
            },
        );
};

const updateOrder = (node: ChainNodePayload, delta: number) => {
    const newOrder = Math.max(1, node.order_index + delta);
    router.put(
        chainRoutes.nodes.update({
            project: props.project.uuid,
            chain: props.chain.id,
            chainNode: node.id,
        }).url,
        {
            name: node.name,
            provider_credential_id: node.provider_credential_id,
            model_name: node.model_name,
            model_params: node.model_params,
            messages_config: node.messages_config,
            output_schema: node.output_schema,
            stop_on_validation_error: node.stop_on_validation_error,
            order_index: newOrder,
        },
        { preserveScroll: true },
    );
};

const deleteNode = (nodeId: number) => {
    router.delete(
        chainRoutes.nodes.destroy({
            project: props.project.uuid,
            chain: props.chain.id,
            chainNode: nodeId,
        }).url,
        { preserveScroll: true },
    );
};

const providerModalOpen = ref(false);
const providerForm = useForm({
    provider: props.providerOptions[0]?.value ?? 'openai',
    name: '',
    api_key: '',
    metadataJson: '',
});

const submitProviderCredential = () => {
    let metadata: Record<string, unknown> | null = null;

    if (providerForm.metadataJson.trim()) {
        try {
            metadata = JSON.parse(providerForm.metadataJson) as Record<string, unknown>;
            providerForm.clearErrors('metadataJson');
        } catch {
            providerForm.setError('metadataJson', 'Metadata must be valid JSON');
            return;
        }
    }

    providerForm
        .transform((data) => ({
            provider: data.provider,
            name: data.name,
            api_key: data.api_key,
            metadata,
        }))
        .post(providerCredentialRoutes.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                providerModalOpen.value = false;
                providerForm.reset();
                providerForm.provider = props.providerOptions[0]?.value ?? 'openai';
                providerForm.metadataJson = '';

                router.reload({
                    only: ['providerCredentials', 'providerCredentialModels'],
                });
            },
        });
};

const runModalOpen = ref(false);

</script>

<template>
    <ProjectLayout :project="project" :title-suffix="`Chains - ${chain.name}`">
        <Toast />
        <div class="flex flex-col gap-4">
            <ChainHeader
                :back-href="chainRoutes.index(project.uuid).url"
                :chain-name-editing="chainNameEditing"
                :chain-form="chainForm"
                :chain-name-input-ref="chainNameInputRef"
                @start-name-edit="startNameEdit"
                @commit-name="commitChainName"
                @update:chain-name="chainForm.name = $event"
                @open-description="openDescriptionEditor"
                @run="runModalOpen = true"
                @save="updateChain"
            />

            <div class="flex flex-col gap-4 lg:flex-row lg:gap-0 lg:h-[calc(100vh-8rem)] lg:overflow-hidden">
                <div class="flex flex-col gap-3 lg:h-full lg:w-[30%] lg:min-w-[260px] lg:max-w-[360px] lg:pr-4 lg:overflow-y-auto">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">Steps</h3>
                            <p class="text-sm text-muted-foreground">Chain flow navigator.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="!hasProviderCredentials"
                                size="sm"
                                variant="outline"
                                @click="providerModalOpen = true"
                            >
                                Add provider
                            </Button>
                            <Button size="sm" @click="activeNodeId = 'new'">+ Add step</Button>
                        </div>
                    </div>

                    <ChainTimeline
                        :items="timelineItems"
                        :active-id="activeNodeId"
                        @select="(id) => { activeNodeId = id; }"
                        @move="updateOrder"
                        @delete="deleteNode"
                    />
                </div>

                <div class="flex min-h-[520px] flex-1 flex-col lg:h-full lg:overflow-hidden lg:border-l lg:border-border/60 lg:pl-4">
                    <ChainNodeEditorHeader
                        :node-name-editing="nodeNameEditing"
                        :node-name="nodeForm.name"
                        :active-node-id="activeNodeId"
                        :node-name-input-ref="nodeNameInputRef"
                        @update:node-name-editing="nodeNameEditing = $event"
                        @update:node-name="nodeForm.name = $event"
                        @save-node="saveNode"
                        @toggle-available-data="showAvailableData = !showAvailableData"
                    />
                    <div class="flex-1 overflow-y-auto px-1 py-3">
                        <div v-if="!activeNodeId" class="flex h-full flex-col items-center justify-center gap-3 text-center">
                            <i class="pi pi-sitemap text-4xl text-muted-foreground/70"></i>
                            <div class="text-sm text-muted-foreground">Select a step to edit</div>
                            <Button size="sm" @click="activeNodeId = 'new'">Create Step</Button>
                        </div>
                        <div v-else class="space-y-4">
                            <div class="relative">
                                <ChainNodeSettingsPanel
                                    :can-save="!nodeForm.processing"
                                    :save-label="editingNodeId ? 'Save changes' : 'Create step'"
                                    @cancel="activeNodeId = null"
                                    @save="saveNode"
                                >
                                    <template #sections>
                                        <ChainNodeModelSettings
                                            :form="nodeForm"
                                            :provider-options="providerSelectOptions"
                                            :model-options="modelSelectOptions"
                                            :selected-provider-id="selectedProviderId"
                                            :selected-model-choice="selectedModelChoice"
                                            :is-custom-model="isCustomModel"
                                            :show-advanced="showAdvancedModelSettings"
                                            @update:provider-credential-id="selectedProviderId = $event"
                                            @update:model-choice="selectedModelChoice = $event"
                                            @update:show-advanced="showAdvancedModelSettings = $event"
                                            @update:model-name="nodeForm.model_name = $event"
                                            @update:temperature="nodeForm.temperature = $event"
                                            @update:max-tokens="nodeForm.max_tokens = $event"
                                        />

                                        <ChainNodePromptConfig
                                            :form="nodeForm"
                                            :prompt-mode-options="promptModeOptions"
                                            :template-options="templateOptions"
                                            :user-template-options="userTemplateOptions"
                                            :system-version-options="systemVersionOptions"
                                            :user-version-options="userVersionOptions"
                                            :system-prompt-text="systemPromptText"
                                            :user-prompt-text="userPromptText"
                                            :system-editor-mode="systemEditorMode"
                                            :system-editor-preset="systemEditorPreset"
                                            :user-editor-mode="userEditorMode"
                                            :user-editor-preset="userEditorPreset"
                                            :variable-rows-system="variableRowsSystem"
                                            :variable-rows-user="variableRowsUser"
                                            :variables-missing-mapping="variablesMissingMapping"
                                            @update:system-mode="nodeForm.system_mode = $event"
                                            @update:system-template-id="nodeForm.system_prompt_template_id = $event"
                                            @update:system-version-id="nodeForm.system_prompt_version_id = $event"
                                            @update:system-inline-content="nodeForm.system_inline_content = $event"
                                            @update:user-mode="nodeForm.user_mode = $event"
                                            @update:user-template-id="nodeForm.user_prompt_template_id = $event"
                                            @update:user-version-id="nodeForm.user_prompt_version_id = $event"
                                            @update:user-inline-content="nodeForm.user_inline_content = $event"
                                            @update:systemEditorMode="systemEditorMode = $event"
                                            @update:systemEditorPreset="systemEditorPreset = $event"
                                            @update:userEditorMode="userEditorMode = $event"
                                            @update:userEditorPreset="userEditorPreset = $event"
                                            @open-mapping-studio="({ role, name }) => openMappingStudio(role, name)"
                                        />

                                        <ChainNodeOutputSection
                                            :form="nodeForm"
                                            @update:output-schema-definition="nodeForm.output_schema_definition = $event"
                                            @update:stop-on-validation-error="nodeForm.stop_on_validation_error = $event"
                                        />
                                    </template>
                                </ChainNodeSettingsPanel>

                                <AvailableDataPanel
                                    :open="showAvailableData"
                                    :order-index="currentOrderIndex"
                                    :search="availableDataSearch"
                                    :tree="filteredAvailableDataTree"
                                    @update:open="showAvailableData = $event"
                                    @update:search="availableDataSearch = $event"
                                    @select="insertPlaceholder"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ProjectLayout>

    <VariableMappingStudio
        v-model:open="mappingStudioOpen"
        v-model:search="mappingStudioSearch"
        :available-tree="filteredStudioDataTree"
        :rows="mappingRowsStudio"
        :mapping-target="mappingTarget"
        :flash-key="mappingFlashKey"
        @select-target="(target) => { mappingTarget = target; }"
        @apply-mapping="({ role, name, value }) => applyMappingText(role, name, value)"
        @copy-path="copyPath"
        @tree-select="onStudioTreeSelect"
        @tree-drag-start="({ event, path }) => handleTreeDragStart(event, path)"
        @mapping-drop="({ event, role, name }) => handleMappingDrop(event, role, name)"
    />

    <ProviderCredentialModal
        v-model:open="providerModalOpen"
        :provider-options="props.providerOptions"
        :form="providerForm"
        @update:provider="providerForm.provider = $event"
        @update:name="providerForm.name = $event"
        @update:api-key="providerForm.api_key = $event"
        @update:metadata-json="providerForm.metadataJson = $event"
        @submit="submitProviderCredential"
    />

    <ChainDescriptionModal
        v-model:open="descriptionModalOpen"
        :form="chainForm"
        @update:description="chainForm.description = $event"
        @save="saveDescription"
    />

    <ChainRunPanel
        v-model:open="runModalOpen"
        :project-uuid="props.project.uuid"
        :chain-id="props.chain.id"
        :nodes="nodes"
        :datasets="props.datasets"
    />

</template>

<style scoped>
:global(.chain-timeline .p-timeline-event-opposite) {
    flex: 0;
}
</style>
