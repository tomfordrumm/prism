import { computed, watch, type ComputedRef, type Ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

export interface ProviderCredentialOption {
    value: number;
    label: string;
    provider: string;
}

export interface PromptModelOption {
    id: string;
    name: string;
    display_name: string;
}

interface PromptRunFormState {
    provider_credential_id: number | null;
    model_name: string;
    variables: string;
}

export type PromptRunForm = ReturnType<typeof useForm<PromptRunFormState>>;

export const usePromptRunForm = (options: {
    providerCredentials: Ref<ProviderCredentialOption[]>;
    providerCredentialModels: Ref<Record<number, PromptModelOption[]>>;
    initialVariables: Ref<string> | ComputedRef<string>;
}) => {
    const runForm = useForm<PromptRunFormState>({
        provider_credential_id: options.providerCredentials.value[0]?.value ?? null,
        model_name: '',
        variables: options.initialVariables.value,
    });

    const modelOptions = computed(() => {
        const credentialId = runForm.provider_credential_id;
        if (!credentialId) return [];

        return options.providerCredentialModels.value[credentialId] ?? [];
    });

    const syncModelSelection = () => {
        const firstModel = modelOptions.value[0];
        runForm.model_name = firstModel ? firstModel.id : '';
    };

    const canRunPrompt = computed(
        () =>
            Boolean(runForm.provider_credential_id) &&
            Boolean(runForm.model_name) &&
            options.providerCredentials.value.length > 0,
    );

    watch(
        () => runForm.provider_credential_id,
        () => syncModelSelection(),
        { immediate: true },
    );

    watch(
        () => options.initialVariables.value,
        (value) => {
            runForm.variables = value;
        },
        { immediate: true },
    );

    return {
        runForm,
        modelOptions,
        canRunPrompt,
        syncModelSelection,
    };
};
