<script setup lang="ts">
import { computed, ref, toRefs, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import RunChainModal from '@/components/chains/RunChainModal.vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { usePromptRunForm } from '@/composables/usePromptRunForm';
import { usePromptInputBuilder } from '@/composables/usePromptInputBuilder';
import type { PromptModelOption, ProviderCredentialOption } from '@/composables/usePromptRunForm';

interface PromptVariable {
    name: string;
    type?: string;
    description?: string | null;
}

interface DatasetOption {
    value: number;
    label: string;
}

interface Props {
    open: boolean;
    projectUuid: string;
    promptTemplateId: number;
    variables: PromptVariable[];
    datasets: DatasetOption[];
    providerCredentials: ProviderCredentialOption[];
    providerCredentialModels: Record<number, PromptModelOption[]>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const { variables, providerCredentials, providerCredentialModels, datasets } = toRefs(props);

const defaultVariables = computed(() => {
    if (!variables.value.length) return '{}';
    const vars: Record<string, unknown> = {};
    variables.value.forEach((v) => {
        vars[v.name] = '';
    });
    return JSON.stringify(vars, null, 2);
});

const { runForm, modelOptions, canRunPrompt, syncModelSelection } = usePromptRunForm({
    providerCredentials,
    providerCredentialModels,
    initialVariables: defaultVariables,
});

const runMode = ref<'manual' | 'dataset'>('manual');
const runDatasetForm = useForm({
    dataset_id: (props.datasets[0]?.value as number) ?? null,
    provider_credential_id: null as number | null,
    model_name: '',
});

const { inputFields, manualInputValues, missingManualInputs } = usePromptInputBuilder({
    variables,
    runForm,
});

const inputProxy = computed(() => ({
    input: runForm.variables,
    errors: { input: runForm.errors.variables },
    processing: runForm.processing,
}));

watch(
    missingManualInputs,
    (missing) => {
        if (!missing.length) {
            runForm.clearErrors('variables');
        }
    },
    { immediate: true },
);

watch(
    () => props.open,
    (open) => {
        if (open) {
            syncModelSelection();
        }
    },
);

const submitRun = () => {
    if (runMode.value === 'dataset') {
        if (!runDatasetForm.dataset_id) {
            runDatasetForm.setError('dataset_id', 'Select a dataset');
            return;
        }

        runDatasetForm.provider_credential_id = runForm.provider_credential_id;
        runDatasetForm.model_name = runForm.model_name;
        runDatasetForm.post(
            `/projects/${props.projectUuid}/prompts/${props.promptTemplateId}/run-dataset`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit('update:open', false);
                },
            },
        );
        return;
    }

    if (inputFields.value.length && missingManualInputs.value.length) {
        runForm.setError('variables', 'Fill all required inputs before running.');
        return;
    }

    runForm.post(`/projects/${props.projectUuid}/prompts/${props.promptTemplateId}/run`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    });
};
</script>

<template>
    <RunChainModal
        :open="open"
        :run-mode="runMode"
        :run-form="inputProxy"
        :run-dataset-form="runDatasetForm"
        :datasets="datasets"
        :has-datasets="datasets.length > 0"
        :input-fields="inputFields"
        :input-values="manualInputValues"
        :can-submit="runMode === 'dataset' ? Boolean(runDatasetForm.dataset_id) : canRunPrompt"
        title="Run prompt (latest version)"
        description="Execute the latest prompt version with your variables."
        :show-run-mode-toggle="true"
        :show-dataset="true"
        @update:open="emit('update:open', $event)"
        @update:run-mode="runMode = $event"
        @update:input="runForm.variables = $event"
        @update:input-value="({ path, value }) => { manualInputValues[path] = value; }"
        @update:dataset-id="runDatasetForm.dataset_id = $event"
        @submit="submitRun"
    >
        <template #before-run>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="prompt_run_provider_credential_id">Provider credential</Label>
                    <select
                        id="prompt_run_provider_credential_id"
                        v-model.number="runForm.provider_credential_id"
                        name="provider_credential_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option v-if="!providerCredentials.length" disabled :value="null">No credentials available</option>
                        <option
                            v-for="credential in providerCredentials"
                            :key="credential.value"
                            :value="credential.value"
                        >
                            {{ credential.label }}
                        </option>
                    </select>
                    <InputError :message="runForm.errors.provider_credential_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="prompt_run_model_name">Model</Label>
                    <select
                        id="prompt_run_model_name"
                        v-model="runForm.model_name"
                        name="model_name"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="" disabled>Select model</option>
                        <option v-for="model in modelOptions" :key="model.id" :value="model.id">
                            {{ model.display_name }} ({{ model.name }})
                        </option>
                    </select>
                    <p v-if="runForm.provider_credential_id && !modelOptions.length" class="text-xs text-muted-foreground">
                        No models available for this credential yet.
                    </p>
                    <InputError :message="runForm.errors.model_name" />
                </div>
            </div>
        </template>
    </RunChainModal>
</template>
