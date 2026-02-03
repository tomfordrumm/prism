import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type {
    ModelOption,
    PromptTemplateOption,
    ProviderCredentialOption,
} from '@/types/chains';

interface AgentFormState {
    name: string;
    description: string;
    provider_credential_id: number | null;
    model_name: string;
    model_choice: string;
    temperature: number | null;
    max_tokens: number | null;
    max_context_messages: number;
    system_prompt_mode: 'template' | 'inline';
    system_prompt_template_id: number | null;
    system_prompt_version_id: number | string | null;
    system_inline_content: string;
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

export function useAgentForm(options: {
    providerCredentials: ProviderCredentialOption[];
    providerCredentialModels: Record<number, ModelOption[]>;
    promptTemplates: PromptTemplateOption[];
    initialData?: Partial<AgentFormState>;
}) {
    const { providerCredentials, providerCredentialModels, promptTemplates, initialData } = options;

    const getModelsForCredential = (credentialId: number | null): ModelOption[] => {
        if (!credentialId) return [];
        return providerCredentialModels[credentialId] ?? [];
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
        providerCredentials.length === 1
            ? normalizeCredentialId(providerCredentials[0]?.value)
            : null;

    const buildInitialState = (): AgentFormState => {
        const defaultCredentialId = getDefaultCredentialId();
        const modelSelection = defaultCredentialId ? selectModelForCredential(defaultCredentialId) : null;

        if (initialData) {
            const credentialId = initialData.provider_credential_id ?? defaultCredentialId;
            const modelName = initialData.model_name;
            const modelSelectionForEdit = credentialId
                ? selectModelForCredential(credentialId, modelName)
                : { modelChoice: CUSTOM_MODEL_VALUE, modelName: modelName ?? '' };

            const initialTemplateId = initialData.system_prompt_template_id ?? null;
            const initialMode = initialData.system_prompt_mode ?? 'template';
            const resolvedVersionId =
                initialMode === 'template'
                    ? (initialData.system_prompt_version_id ?? 'latest')
                    : null;

            return {
                name: initialData.name ?? '',
                description: initialData.description ?? '',
                provider_credential_id: credentialId,
                model_name: modelSelectionForEdit.modelName,
                model_choice: modelSelectionForEdit.modelChoice,
                temperature: initialData.temperature ?? null,
                max_tokens: initialData.max_tokens ?? null,
                max_context_messages: initialData.max_context_messages ?? 20,
                system_prompt_mode: initialMode,
                system_prompt_template_id: initialTemplateId,
                system_prompt_version_id: resolvedVersionId,
                system_inline_content: initialData.system_inline_content ?? '',
            };
        }

        return {
            name: '',
            description: '',
            provider_credential_id: defaultCredentialId,
            model_name: modelSelection?.modelName ?? '',
            model_choice: modelSelection?.modelChoice ?? '',
            temperature: null,
            max_tokens: null,
            max_context_messages: 20,
            system_prompt_mode: 'template',
            system_prompt_template_id: promptTemplates[0]?.id ?? null,
            system_prompt_version_id: promptTemplates[0]?.id ? 'latest' : null,
            system_inline_content: '',
        };
    };

    const form = useForm<AgentFormState>(buildInitialState());
    const showAdvanced = ref(false);

    const providerSelectOptions = computed<ProviderSelectOption[]>(() =>
        providerCredentials
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
        const credentialId = form.provider_credential_id;
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

    const isCustomModel = computed(() => form.model_choice === CUSTOM_MODEL_VALUE);

    const selectedProviderId = computed<number | null>({
        get: () => form.provider_credential_id,
        set: (value) => {
            const normalized = normalizeCredentialId(value);
            if (!normalized) {
                form.provider_credential_id = null;
                form.model_choice = '';
                form.model_name = '';
                return;
            }

            const previousCredentialId = form.provider_credential_id;
            form.provider_credential_id = normalized;

            if (previousCredentialId !== normalized) {
                resetModelSelection(normalized);
            }
        },
    });

    const selectedModelChoice = computed<string>({
        get: () => form.model_choice,
        set: (value) => {
            form.model_choice = value;
        },
    });

    const resetModelSelection = (
        credentialId: number | null,
        preferredModelName?: string | null,
        forceCustom = false,
    ) => {
        if (!credentialId) {
            form.model_choice = '';
            form.model_name = '';
            return;
        }

        const selection = forceCustom
            ? { modelChoice: CUSTOM_MODEL_VALUE, modelName: preferredModelName ?? '' }
            : selectModelForCredential(credentialId, preferredModelName);

        form.model_choice = selection.modelChoice;
        form.model_name = selection.modelName;
    };

    const templateOptions = computed(() =>
        promptTemplates.map((template) => ({
            value: template.id,
            label: template.name,
        })),
    );

    const versionOptions = computed(() => {
        const templateId = form.system_prompt_template_id;
        const template = promptTemplates.find((t) => t.id === templateId);
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
    });

    const resolveTemplateContent = (templateId: number | null, versionId: number | string | null) => {
        if (!templateId) return '';
        const template = promptTemplates.find((item) => item.id === templateId);
        if (!template) return '';

        const resolvedVersionId =
            versionId && versionId !== 'latest' ? Number(versionId) : template.latest_version_id;
        if (!resolvedVersionId) return '';

        const version = template.versions.find((item) => item.id === resolvedVersionId);
        return version?.content ?? '';
    };

    const systemPromptText = computed(() =>
        form.system_prompt_mode === 'template'
            ? resolveTemplateContent(form.system_prompt_template_id, form.system_prompt_version_id)
            : form.system_inline_content,
    );

    const buildModelParams = () => {
        const params: Record<string, unknown> = {};

        if (form.temperature !== null && !Number.isNaN(Number(form.temperature))) {
            params.temperature = Number(form.temperature);
        }

        if (form.max_tokens !== null && !Number.isNaN(Number(form.max_tokens))) {
            params.max_tokens = Number(form.max_tokens);
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

    watch(
        () => form.model_choice,
        (choice, previousChoice) => {
            if (choice && choice !== CUSTOM_MODEL_VALUE) {
                form.model_name = choice;
                return;
            }

            if (choice === CUSTOM_MODEL_VALUE && previousChoice !== CUSTOM_MODEL_VALUE) {
                if (form.model_name === previousChoice || !form.model_name) {
                    form.model_name = '';
                }
            } else if (!choice) {
                form.model_name = '';
            }
        },
    );

    watch(
        () => form.system_prompt_template_id,
        (templateId) => {
            form.system_prompt_version_id = templateId ? 'latest' : null;
        },
    );

    return {
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
        resetModelSelection,
    };
}
