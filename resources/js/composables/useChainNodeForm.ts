import { computed, ref, watch, type Ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type {
    ChainNodeMessage,
    ChainNodePayload,
    ModelOption,
    PromptTemplateOption,
    ProviderCredentialOption,
    VariableMapping,
} from '@/types/chains';

interface NodeFormState {
    name: string;
    provider_credential_id: number | null;
    model_name: string;
    model_choice: string;
    temperature: number | null;
    max_tokens: number | null;
    system_mode: 'template' | 'inline';
    system_prompt_template_id: number | null;
    system_prompt_version_id: number | string | null;
    system_inline_content: string;
    system_variables: Record<string, VariableMapping>;
    user_mode: 'template' | 'inline';
    user_prompt_template_id: number | null;
    user_prompt_version_id: number | string | null;
    user_inline_content: string;
    user_variables: Record<string, VariableMapping>;
    output_schema_definition: string;
    stop_on_validation_error: boolean;
    order_index: number;
}

interface ProviderSelectOption {
    label: string;
    value: number;
}

interface ModelSelectOption {
    label: string;
    value: string;
    credentialId: number;
    modelId: string;
}

const CUSTOM_MODEL_VALUE = 'custom';

const normalizeCredentialId = (value: number | string | undefined | null): number | null => {
    if (value === null || value === undefined) return null;
    const parsed = typeof value === 'number' ? value : Number(value);
    return Number.isNaN(parsed) ? null : parsed;
};

const normalizeVariableMap = (value: unknown): Record<string, VariableMapping> => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return {};
    }

    return value as Record<string, VariableMapping>;
};

export function useChainNodeForm(options: {
    nodes: Ref<ChainNodePayload[]>;
    providerCredentials: Ref<ProviderCredentialOption[]>;
    providerCredentialModels: Ref<Record<number, ModelOption[]>>;
    promptTemplates: Ref<PromptTemplateOption[]>;
}) {
    const { nodes, providerCredentials, providerCredentialModels, promptTemplates } = options;

    const versionLookup = computed(() => {
        const map = new Map<
            number,
            { templateId: number; templateName: string; version: number; created_at: string | null }
        >();

        promptTemplates.value.forEach((template) => {
            template.versions.forEach((version) => {
                map.set(version.id, {
                    templateId: template.id,
                    templateName: template.name,
                    version: version.version,
                    created_at: version.created_at ?? null,
                });
            });
        });

        return map;
    });

    const parseMessagesConfig = (messages: ChainNodeMessage[]) => {
        const systemEntry = messages.find((message) => message.role === 'system');
        const userEntry = messages.find((message) => message.role === 'user');

        const resolveTemplateId = (entry: ChainNodeMessage | undefined) => {
            if (entry?.prompt_template_id) return entry.prompt_template_id;
            if (entry?.prompt_version_id) {
                return versionLookup.value.get(entry.prompt_version_id)?.templateId ?? null;
            }
            return null;
        };

        return {
            system_mode: systemEntry?.mode ?? 'template',
            system_prompt_template_id: resolveTemplateId(systemEntry),
            system_prompt_version_id: systemEntry?.prompt_version_id ?? null,
            system_variables: normalizeVariableMap(systemEntry?.variables),
            system_inline_content: systemEntry?.inline_content ?? '',
            user_mode: userEntry?.mode ?? 'template',
            user_prompt_template_id: resolveTemplateId(userEntry),
            user_prompt_version_id: userEntry?.prompt_version_id ?? null,
            user_variables: normalizeVariableMap(userEntry?.variables),
            user_inline_content: userEntry?.inline_content ?? '',
        };
    };

    const buildMessagesConfig = (
        systemMode: 'template' | 'inline',
        systemTemplateId: number | null,
        systemVersionId: number | null,
        systemInlineContent: string,
        systemVariables: Record<string, VariableMapping>,
        userMode: 'template' | 'inline',
        userTemplateId: number | null,
        userVersionId: number | null,
        userInlineContent: string,
        userVariables: Record<string, VariableMapping>,
    ): ChainNodeMessage[] => {
        const config: ChainNodeMessage[] = [];

        if (systemMode === 'inline' || systemTemplateId) {
            config.push({
                role: 'system',
                mode: systemMode,
                prompt_template_id: systemTemplateId,
                prompt_version_id: systemVersionId,
                inline_content: systemMode === 'inline' ? systemInlineContent : null,
                variables: normalizeVariableMap(systemVariables),
            });
        }

        if (userMode === 'inline' || userTemplateId) {
            config.push({
                role: 'user',
                mode: userMode,
                prompt_template_id: userTemplateId,
                prompt_version_id: userVersionId,
                inline_content: userMode === 'inline' ? userInlineContent : null,
                variables: normalizeVariableMap(userVariables),
            });
        }

        return config;
    };

    const extractModelParams = (params: Record<string, unknown> | null) => ({
        temperature: typeof params?.temperature === 'number' ? params.temperature : null,
        max_tokens: typeof params?.max_tokens === 'number' ? params.max_tokens : null,
    });

    const buildModelParams = (data: { temperature: number | null; max_tokens: number | null }) => {
        const params: Record<string, unknown> = {};

        if (data.temperature !== null && !Number.isNaN(Number(data.temperature))) {
            params.temperature = Number(data.temperature);
        }

        if (data.max_tokens !== null && !Number.isNaN(Number(data.max_tokens))) {
            params.max_tokens = Number(data.max_tokens);
        }

        return Object.keys(params).length ? params : null;
    };

    const normalizeVersionSelection = (value: number | string | null): number | null => {
        if (value === null || value === '' || value === 'latest') {
            return null;
        }

        const num = Number(value);
        return Number.isNaN(num) ? null : num;
    };

    const getModelsForCredential = (credentialId: number | null): ModelOption[] => {
        if (!credentialId) return [];

        return providerCredentialModels.value[credentialId] ?? [];
    };

    const providerSelectOptions = computed<ProviderSelectOption[]>(() =>
        providerCredentials.value
            .map((credential) => {
                const credentialId = normalizeCredentialId(credential.value);
                if (!credentialId) return null;
                return {
                    label: credential.label,
                    value: credentialId,
                };
            })
            .filter((option): option is ProviderSelectOption => Boolean(option)),
    );

    const modelSelectOptions = computed<ModelSelectOption[]>(() => {
        const credentialId = nodeForm.provider_credential_id;
        if (!credentialId) return [];

        const models = getModelsForCredential(credentialId);
        const items: ModelSelectOption[] = models.map((model) => ({
            label: `${model.display_name} (${model.name})`,
            value: model.id,
            credentialId,
            modelId: model.id,
        }));

        items.push({
            label: 'Custom model...',
            value: CUSTOM_MODEL_VALUE,
            credentialId,
            modelId: CUSTOM_MODEL_VALUE,
        });

        return items;
    });

    const buildVersionOptions = (templateId: number | null) => {
        const template = promptTemplates.value.find((t) => t.id === templateId);
        if (!template) return [];

        return [
            {
                value: 'latest',
                label: 'Latest',
            },
            ...template.versions.map((version) => {
                const timestamp = version.created_at ? ` - ${version.created_at}` : '';
                return {
                    value: version.id,
                    label: `v${version.version}${timestamp}`,
                };
            }),
        ];
    };

    const resolveTemplateContent = (templateId: number | null, versionId: number | string | null) => {
        if (!templateId) return '';
        const template = promptTemplates.value.find((item) => item.id === templateId);
        if (!template) return '';

        const resolvedVersionId =
            versionId && versionId !== 'latest' ? Number(versionId) : template.latest_version_id;
        if (!resolvedVersionId) return '';

        const version = template.versions.find((item) => item.id === resolvedVersionId);
        return version?.content ?? '';
    };

    const selectModelForCredential = (credentialId: number | null, preferredModelName?: string | null) => {
        const options = getModelsForCredential(credentialId);

        if (preferredModelName && options.some((option) => option.id === preferredModelName)) {
            return { modelChoice: preferredModelName, modelName: preferredModelName };
        }

        const firstOption = options[0];

        if (firstOption) {
            return { modelChoice: firstOption.id, modelName: firstOption.id };
        }

        return { modelChoice: CUSTOM_MODEL_VALUE, modelName: preferredModelName ?? '' };
    };

    const getDefaultCredentialId = () =>
        providerCredentials.value.length === 1
            ? normalizeCredentialId(providerCredentials.value[0]?.value)
            : null;

    const buildInitialNodeFormState = (): NodeFormState => {
        const defaultCredentialId = getDefaultCredentialId();
        const modelSelection = defaultCredentialId ? selectModelForCredential(defaultCredentialId) : null;

        return {
            name: '',
            provider_credential_id: defaultCredentialId,
            model_name: modelSelection?.modelName ?? '',
            model_choice: modelSelection?.modelChoice ?? '',
            temperature: null,
            max_tokens: null,
            system_mode: 'template',
            system_prompt_template_id: null,
            system_prompt_version_id: null,
            system_inline_content: '',
            system_variables: {},
            user_mode: 'template',
            user_prompt_template_id: null,
            user_prompt_version_id: null,
            user_inline_content: '',
            user_variables: {},
            output_schema_definition: '',
            stop_on_validation_error: false,
            order_index: nodes.value.length + 1,
        };
    };

    const nodeForm = useForm<NodeFormState>(buildInitialNodeFormState());
    const editingNodeId = ref<number | null>(null);

    const currentOrderIndex = computed(() => Number(nodeForm.order_index) || nodes.value.length + 1);
    const isCustomModel = computed(() => nodeForm.model_choice === CUSTOM_MODEL_VALUE);

    const selectedProviderId = computed<number | null>({
        get: () => {
            return nodeForm.provider_credential_id;
        },
        set: (value) => {
            const normalized = normalizeCredentialId(value);
            if (!normalized) {
                nodeForm.provider_credential_id = null;
                nodeForm.model_choice = '';
                nodeForm.model_name = '';
                return;
            }

            const previousCredentialId = nodeForm.provider_credential_id;
            nodeForm.provider_credential_id = normalized;

            if (previousCredentialId !== normalized) {
                resetModelSelection(normalized);
            }
        },
    });

    const selectedModelChoice = computed<string>({
        get: () => nodeForm.model_choice,
        set: (value) => {
            nodeForm.model_choice = value;
        },
    });

    const resetModelSelection = (
        credentialId: number | null,
        preferredModelName?: string | null,
        forceCustom = false,
    ) => {
        if (!credentialId) {
            nodeForm.model_choice = '';
            nodeForm.model_name = '';
            return;
        }

        const selection = forceCustom
            ? { modelChoice: CUSTOM_MODEL_VALUE, modelName: preferredModelName ?? '' }
            : selectModelForCredential(credentialId, preferredModelName);

        nodeForm.model_choice = selection.modelChoice;
        nodeForm.model_name = selection.modelName;
    };

    const systemVersionOptions = computed(() => buildVersionOptions(nodeForm.system_prompt_template_id));
    const userVersionOptions = computed(() => buildVersionOptions(nodeForm.user_prompt_template_id));

    const systemPromptText = computed(() =>
        nodeForm.system_mode === 'template'
            ? resolveTemplateContent(nodeForm.system_prompt_template_id, nodeForm.system_prompt_version_id)
            : nodeForm.system_inline_content,
    );

    const userPromptText = computed(() =>
        nodeForm.user_mode === 'template'
            ? resolveTemplateContent(nodeForm.user_prompt_template_id, nodeForm.user_prompt_version_id)
            : nodeForm.user_inline_content,
    );

    const openCreateDrawer = () => {
        editingNodeId.value = null;
        nodeForm.reset();
        nodeForm.name = '';
        const defaultCredentialId = getDefaultCredentialId();
        nodeForm.provider_credential_id = defaultCredentialId;
        resetModelSelection(defaultCredentialId);
        nodeForm.temperature = null;
        nodeForm.max_tokens = null;
        nodeForm.system_mode = 'template';
        nodeForm.system_prompt_template_id = promptTemplates.value[0]?.id ?? null;
        nodeForm.system_prompt_version_id = null;
        nodeForm.system_inline_content = '';
        nodeForm.system_variables = {};
        nodeForm.user_mode = 'template';
        nodeForm.user_prompt_template_id = null;
        nodeForm.user_prompt_version_id = null;
        nodeForm.user_inline_content = '';
        nodeForm.user_variables = {};
        nodeForm.output_schema_definition = '';
        nodeForm.stop_on_validation_error = false;
        nodeForm.order_index = nodes.value.length + 1;
    };

    const openEditDrawer = (node: ChainNodePayload) => {
        editingNodeId.value = node.id;
        const prompts = parseMessagesConfig(node.messages_config);
        const params = extractModelParams(node.model_params);
        nodeForm.name = node.name;
        nodeForm.provider_credential_id = node.provider_credential_id;
        resetModelSelection(node.provider_credential_id, node.model_name);
        nodeForm.temperature = params.temperature;
        nodeForm.max_tokens = params.max_tokens;
        nodeForm.system_mode = prompts.system_mode;
        nodeForm.system_prompt_template_id = prompts.system_prompt_template_id;
        nodeForm.system_prompt_version_id = prompts.system_prompt_version_id;
        nodeForm.system_inline_content = prompts.system_inline_content || '';
        nodeForm.system_variables = prompts.system_variables || {};
        nodeForm.user_mode = prompts.user_mode;
        nodeForm.user_prompt_template_id = prompts.user_prompt_template_id;
        nodeForm.user_prompt_version_id = prompts.user_prompt_version_id;
        nodeForm.user_inline_content = prompts.user_inline_content || '';
        nodeForm.user_variables = prompts.user_variables || {};
        nodeForm.output_schema_definition = node.output_schema_definition || '';
        nodeForm.stop_on_validation_error = node.stop_on_validation_error;
        nodeForm.order_index = node.order_index;
    };

    watch(
        () => nodeForm.model_choice,
        (choice, previousChoice) => {
            if (choice && choice !== CUSTOM_MODEL_VALUE) {
                nodeForm.model_name = choice;
                return;
            }

            if (choice === CUSTOM_MODEL_VALUE && previousChoice !== CUSTOM_MODEL_VALUE) {
                if (nodeForm.model_name === previousChoice || !nodeForm.model_name) {
                    nodeForm.model_name = '';
                }
            } else if (!choice) {
                nodeForm.model_name = '';
            }
        },
    );

    watch(
        () => nodeForm.system_prompt_template_id,
        (templateId) => {
            nodeForm.system_prompt_version_id = templateId ? 'latest' : null;
        },
    );

    watch(
        () => nodeForm.user_prompt_template_id,
        (templateId) => {
            nodeForm.user_prompt_version_id = templateId ? 'latest' : null;
        },
    );

    watch(
        () => providerCredentials.value,
        (credentials) => {
            if (nodeForm.provider_credential_id || credentials.length !== 1) return;
            const defaultCredentialId = normalizeCredentialId(credentials[0]?.value);
            if (!defaultCredentialId) return;
            nodeForm.provider_credential_id = defaultCredentialId;
            resetModelSelection(defaultCredentialId);
        },
        { deep: true },
    );

    return {
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
        openCreateDrawer,
        openEditDrawer,
        resetModelSelection,
        buildMessagesConfig,
        buildModelParams,
        normalizeVersionSelection,
        parseMessagesConfig,
    };
}
