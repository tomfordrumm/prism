<script setup lang="ts">
import { computed, ref, toRefs, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import RunChainModal from '@/components/chains/RunChainModal.vue';
import { useChainInputBuilder } from '@/composables/useChainInputBuilder';
import type { ChainNodePayload } from '@/types/chains';

interface Option {
    value: number | string;
    label: string;
}

interface Props {
    open: boolean;
    projectUuid: string;
    chainId: number;
    nodes: ChainNodePayload[];
    datasets: Option[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const { nodes, datasets } = toRefs(props);

const runMode = ref<'manual' | 'dataset'>('manual');
const runForm = useForm({
    input: '{}',
});
const runDatasetForm = useForm({
    dataset_id: (props.datasets[0]?.value as number) ?? null,
});

const { inputFields, manualInputValues, missingManualInputs } = useChainInputBuilder({
    nodes,
    runForm,
});

const hasDatasets = computed(() => props.datasets.length > 0);

watch(
    missingManualInputs,
    (missing) => {
        if (!missing.length) {
            runForm.clearErrors('input');
        }
    },
    { immediate: true },
);

const submitRun = () => {
    if (runMode.value === 'dataset') {
        if (!runDatasetForm.dataset_id) {
            runDatasetForm.setError('dataset_id', 'Select a dataset');
            return;
        }

        runDatasetForm.post(`/projects/${props.projectUuid}/chains/${props.chainId}/run-dataset`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
            },
        });
        return;
    }

    if (inputFields.value.length && missingManualInputs.value.length) {
        runForm.setError('input', 'Fill all required inputs before running.');
        return;
    }

    runForm.post(`/projects/${props.projectUuid}/chains/${props.chainId}/run`, {
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
        :run-form="runForm"
        :run-dataset-form="runDatasetForm"
        :datasets="datasets"
        :has-datasets="hasDatasets"
        :input-fields="inputFields"
        :input-values="manualInputValues"
        @update:open="emit('update:open', $event)"
        @update:run-mode="runMode = $event"
        @update:input="runForm.input = $event"
        @update:input-value="({ path, value }) => { manualInputValues[path] = value; }"
        @update:dataset-id="runDatasetForm.dataset_id = $event"
        @submit="submitRun"
    />
</template>
