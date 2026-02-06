<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import datasetRoutes from '@/routes/projects/datasets';
import testCasesRoutes from '@/routes/projects/datasets/test-cases';
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import PromptEditor from '@/components/PromptEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface DatasetPayload {
    id: number;
    name: string;
    description?: string | null;
}

interface TestCasePayload {
    id: number;
    name: string;
    input_variables: Record<string, unknown>;
    tags?: string[] | null;
}

interface Props {
    project: ProjectPayload;
    dataset: DatasetPayload;
    testCases: TestCasePayload[];
}

const props = defineProps<Props>();

const datasetForm = useForm({
    name: props.dataset.name,
    description: props.dataset.description ?? '',
});

const newTestCaseForm = useForm({
    name: '',
    inputJson: '{}',
});

type InputPair = { key: string; value: string };
type InputMode = 'builder' | 'manual';
type EditState = {
    form: ReturnType<typeof useForm<{ name: string; inputJson: string }>>;
    mode: InputMode;
    pairs: InputPair[];
};

const newInputMode = ref<InputMode>('builder');
const newInputPairs = ref<InputPair[]>([{ key: '', value: '' }]);

const editStates = reactive<Record<number, EditState>>({});

const toggleButtonClass = (isActive: boolean) =>
    isActive
        ? 'rounded-full border border-foreground bg-foreground px-2.5 py-1 text-xs font-medium text-background'
        : 'rounded-full border border-border bg-background px-2.5 py-1 text-xs font-medium text-muted-foreground hover:text-foreground';

const parseValue = (value: string): unknown => {
    const trimmed = value.trim();
    if (trimmed.length === 0) {
        return '';
    }

    try {
        return JSON.parse(trimmed);
    } catch {
        return value;
    }
};

const buildObjectFromPairs = (pairs: InputPair[]) =>
    pairs.reduce<Record<string, unknown>>((acc, pair) => {
        const key = pair.key.trim();
        if (!key) {
            return acc;
        }
        acc[key] = parseValue(pair.value);
        return acc;
    }, {});

const pairsFromObject = (value: Record<string, unknown>) => {
    const entries = Object.entries(value ?? {});
    if (!entries.length) {
        return [{ key: '', value: '' }];
    }

    return entries.map(([key, entryValue]) => ({
        key,
        value: typeof entryValue === 'string' ? entryValue : JSON.stringify(entryValue, null, 2),
    }));
};

const ensureEditState = (testCase: TestCasePayload) => {
    if (!editStates[testCase.id]) {
        editStates[testCase.id] = {
            form: useForm({
                name: testCase.name,
                inputJson: JSON.stringify(testCase.input_variables ?? {}, null, 2),
            }),
            mode: 'builder',
            pairs: pairsFromObject(testCase.input_variables ?? {}),
        };
    }

    return editStates[testCase.id];
};

const switchNewMode = (mode: InputMode) => {
    if (newInputMode.value === mode) {
        return;
    }

    if (mode === 'manual') {
        newTestCaseForm.inputJson = JSON.stringify(buildObjectFromPairs(newInputPairs.value), null, 2);
        newTestCaseForm.clearErrors('inputJson');
        newInputMode.value = 'manual';
        return;
    }

    try {
        const parsed = JSON.parse(newTestCaseForm.inputJson);
        newTestCaseForm.clearErrors('inputJson');
        newInputPairs.value = pairsFromObject(parsed);
        newInputMode.value = 'builder';
    } catch {
        newTestCaseForm.setError('inputJson', 'Input variables must be valid JSON');
    }
};

const switchEditMode = (testCase: TestCasePayload, mode: InputMode) => {
    const state = ensureEditState(testCase);
    if (state.mode === mode) {
        return;
    }

    if (mode === 'manual') {
        state.form.inputJson = JSON.stringify(buildObjectFromPairs(state.pairs), null, 2);
        state.form.clearErrors('inputJson');
        state.mode = 'manual';
        return;
    }

    try {
        const parsed = JSON.parse(state.form.inputJson);
        state.form.clearErrors('inputJson');
        state.pairs = pairsFromObject(parsed);
        state.mode = 'builder';
    } catch {
        state.form.setError('inputJson', 'Input variables must be valid JSON');
    }
};

const addPair = (pairs: InputPair[]) => {
    pairs.push({ key: '', value: '' });
};

const removePair = (pairs: InputPair[], index: number) => {
    pairs.splice(index, 1);
    if (!pairs.length) {
        pairs.push({ key: '', value: '' });
    }
};

const addNewPair = () => addPair(newInputPairs.value);
const removeNewPair = (index: number) => removePair(newInputPairs.value, index);

const addEditPair = (testCase: TestCasePayload) => addPair(ensureEditState(testCase).pairs);
const removeEditPair = (testCase: TestCasePayload, index: number) => removePair(ensureEditState(testCase).pairs, index);

const ensureEditForm = (testCase: TestCasePayload) => ensureEditState(testCase).form;

const inputPairsFor = (testCase: TestCasePayload) => ensureEditState(testCase).pairs;

const inputModeFor = (testCase: TestCasePayload) => ensureEditState(testCase).mode;

const ensureEditPairsJson = (testCase: TestCasePayload) => {
    const state = ensureEditState(testCase);
    if (state.mode === 'builder') {
        state.form.inputJson = JSON.stringify(buildObjectFromPairs(state.pairs), null, 2);
    }
};

const updateDataset = () => {
    datasetForm
        .transform((data) => ({
            name: data.name,
            description: data.description || null,
        }))
        .put(datasetRoutes.update({ project: props.project.uuid, dataset: props.dataset.id }).url);
};

const createTestCase = () => {
    try {
        if (newInputMode.value === 'builder') {
            newTestCaseForm.inputJson = JSON.stringify(buildObjectFromPairs(newInputPairs.value), null, 2);
        }
        const parsed = JSON.parse(newTestCaseForm.inputJson);
        newTestCaseForm.clearErrors('inputJson');
        newTestCaseForm
            .transform((data) => ({
                name: data.name,
                input_variables: parsed,
            }))
            .post(
                testCasesRoutes.store({
                    project: props.project.uuid,
                    dataset: props.dataset.id,
                }).url,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        newTestCaseForm.reset();
                        newTestCaseForm.inputJson = '{}';
                        newInputPairs.value = [{ key: '', value: '' }];
                        newInputMode.value = 'builder';
                    },
                },
            );
    } catch {
        newTestCaseForm.setError('inputJson', 'Input variables must be valid JSON');
    }
};

const updateTestCase = (testCase: TestCasePayload) => {
    ensureEditPairsJson(testCase);
    const form = ensureEditForm(testCase);

    try {
        const parsed = JSON.parse(form.inputJson);
        form.clearErrors('inputJson');

        form
            .transform((data) => ({
                name: data.name,
                input_variables: parsed,
            }))
            .put(
                testCasesRoutes.update({
                    project: props.project.uuid,
                    dataset: props.dataset.id,
                    testCase: testCase.id,
                }).url,
                { preserveScroll: true },
            );
    } catch {
        form.setError('inputJson', 'Input variables must be valid JSON');
    }
};

const deleteTestCase = (testCase: TestCasePayload) => {
    router.delete(
        testCasesRoutes.destroy({
            project: props.project.uuid,
            dataset: props.dataset.id,
            testCase: testCase.id,
        }).url,
        { preserveScroll: true },
    );
};

const pretty = (value: Record<string, unknown>) => JSON.stringify(value, null, 2);

const hasTestCases = computed(() => props.testCases.length > 0);
</script>

<template>
    <ProjectLayout :project="project" :title-suffix="`Datasets • ${dataset.name}`">
        <div class="flex flex-col gap-4">
            <div class="rounded-lg border border-border bg-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-foreground">{{ dataset.name }}</h2>
                        <p class="text-sm text-muted-foreground">Dataset details</p>
                    </div>
                    <Button size="sm" :disabled="datasetForm.processing" @click="updateDataset">Save</Button>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="dataset_name">Name</Label>
                        <Input id="dataset_name" v-model="datasetForm.name" />
                        <InputError :message="datasetForm.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dataset_description">Description</Label>
                        <textarea
                            id="dataset_description"
                            v-model="datasetForm.description"
                            rows="2"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        ></textarea>
                        <InputError :message="datasetForm.errors.description" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-foreground">Test cases</h3>
                        <p class="text-sm text-muted-foreground">Define inputs for batch runs.</p>
                    </div>
                </div>

                <div v-if="!hasTestCases" class="mt-3 rounded-md border border-dashed border-border p-3 text-sm text-muted-foreground">
                    No test cases yet.
                </div>

                <div v-else class="mt-3 space-y-3">
                    <div
                        v-for="testCase in testCases"
                        :key="testCase.id"
                        class="rounded-md border border-border/70 bg-muted/40 p-3"
                    >
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-foreground">{{ testCase.name }}</div>
                            <div class="flex items-center gap-2">
                                <Button size="sm" variant="destructive" @click="deleteTestCase(testCase)">Delete</Button>
                                <Button size="sm" :disabled="ensureEditForm(testCase).processing" @click="updateTestCase(testCase)">
                                    Save
                                </Button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <Label>Input variables</Label>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        :class="toggleButtonClass(inputModeFor(testCase) === 'builder')"
                                        @click="switchEditMode(testCase, 'builder')"
                                    >
                                        Builder
                                    </button>
                                    <button
                                        type="button"
                                        :class="toggleButtonClass(inputModeFor(testCase) === 'manual')"
                                        @click="switchEditMode(testCase, 'manual')"
                                    >
                                        Manual input
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div v-if="inputModeFor(testCase) === 'builder'" class="space-y-2">
                                    <div
                                        v-for="(pair, index) in inputPairsFor(testCase)"
                                        :key="`${testCase.id}-${index}`"
                                        class="flex flex-col gap-2 md:flex-row md:items-center"
                                    >
                                        <Input v-model="pair.key" placeholder="key" class="md:w-1/3" />
                                        <Input v-model="pair.value" placeholder="value or JSON" class="flex-1" />
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="justify-center md:w-auto"
                                            @click="removeEditPair(testCase, index)"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                    <Button size="sm" variant="outline" @click="addEditPair(testCase)">
                                        + Add field
                                    </Button>
                                </div>
                                <div v-else class="grid gap-2">
                                    <PromptEditor v-model="ensureEditForm(testCase).inputJson" height="180px" placeholder="{ }" />
                                </div>
                                <InputError :message="ensureEditForm(testCase).errors.inputJson" />
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-muted-foreground">
                            Preview:
                            <pre class="mt-1 whitespace-pre-wrap rounded-md bg-background p-2">{{ pretty(testCase.input_variables) }}</pre>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-md border border-border bg-background p-3">
                    <h4 class="text-sm font-semibold text-foreground">Add test case</h4>
                    <div class="mt-2 grid gap-2 md:w-1/2">
                        <Label for="tc_name">Name</Label>
                        <Input id="tc_name" v-model="newTestCaseForm.name" placeholder="Case name" />
                        <InputError :message="newTestCaseForm.errors.name" />
                    </div>

                    <div class="mt-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Label>Input variables</Label>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    :class="toggleButtonClass(newInputMode === 'builder')"
                                    @click="switchNewMode('builder')"
                                >
                                    Builder
                                </button>
                                <button
                                    type="button"
                                    :class="toggleButtonClass(newInputMode === 'manual')"
                                    @click="switchNewMode('manual')"
                                >
                                    Manual input
                                </button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div v-if="newInputMode === 'builder'" class="space-y-2">
                                <div
                                    v-for="(pair, index) in newInputPairs"
                                    :key="`new-${index}`"
                                    class="flex flex-col gap-2 md:flex-row md:items-center"
                                >
                                    <Input v-model="pair.key" placeholder="key" class="md:w-1/3" />
                                    <Input v-model="pair.value" placeholder="value or JSON" class="flex-1" />
                                    <Button size="sm" variant="ghost" class="justify-center md:w-auto" @click="removeNewPair(index)">
                                        Remove
                                    </Button>
                                </div>
                                <Button size="sm" variant="outline" @click="addNewPair">+ Add field</Button>
                            </div>
                            <div v-else class="grid gap-2">
                                <PromptEditor v-model="newTestCaseForm.inputJson" height="180px" placeholder="{ }" />
                            </div>
                            <InputError :message="newTestCaseForm.errors.inputJson" />
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <Button size="sm" :disabled="newTestCaseForm.processing" @click="createTestCase">Add</Button>
                        <InputError :message="newTestCaseForm.errors.input_variables" />
                    </div>
                </div>
            </div>
        </div>
    </ProjectLayout>
</template>
