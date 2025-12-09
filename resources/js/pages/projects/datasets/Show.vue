<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import datasetRoutes from '@/routes/projects/datasets';
import testCasesRoutes from '@/routes/projects/datasets/test-cases';
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ProjectPayload {
    id: number;
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

const editForms = reactive<Record<number, ReturnType<typeof useForm<{ name: string; inputJson: string }>>>>({});

const ensureEditForm = (testCase: TestCasePayload) => {
    if (!editForms[testCase.id]) {
        editForms[testCase.id] = useForm({
            name: testCase.name,
            inputJson: JSON.stringify(testCase.input_variables ?? {}, null, 2),
        });
    }

    return editForms[testCase.id];
};

const updateDataset = () => {
    datasetForm
        .transform((data) => ({
            name: data.name,
            description: data.description || null,
        }))
        .put(datasetRoutes.update({ project: props.project.id, dataset: props.dataset.id }).url);
};

const createTestCase = () => {
    try {
        const parsed = JSON.parse(newTestCaseForm.inputJson);
        newTestCaseForm.clearErrors('inputJson');
        newTestCaseForm
            .transform((data) => ({
                name: data.name,
                input_variables: parsed,
            }))
            .post(
                testCasesRoutes.store({
                    project: props.project.id,
                    dataset: props.dataset.id,
                }).url,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        newTestCaseForm.reset();
                        newTestCaseForm.inputJson = '{}';
                    },
                },
            );
    } catch (error) {
        newTestCaseForm.setError('inputJson', 'Input variables must be valid JSON');
    }
};

const updateTestCase = (testCase: TestCasePayload) => {
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
                    project: props.project.id,
                    dataset: props.dataset.id,
                    testCase: testCase.id,
                }).url,
                { preserveScroll: true },
            );
    } catch (error) {
        form.setError('inputJson', 'Input variables must be valid JSON');
    }
};

const deleteTestCase = (testCase: TestCasePayload) => {
    router.delete(
        testCasesRoutes.destroy({
            project: props.project.id,
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
                        <div class="mt-2 grid gap-2">
                            <Label>Input variables (JSON)</Label>
                            <textarea
                                v-model="ensureEditForm(testCase).inputJson"
                                rows="4"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                            ></textarea>
                            <InputError :message="ensureEditForm(testCase).errors.inputJson" />
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

                    <div class="mt-2 grid gap-2">
                        <Label for="tc_input">Input variables (JSON)</Label>
                        <textarea
                            id="tc_input"
                            v-model="newTestCaseForm.inputJson"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        ></textarea>
                        <InputError :message="newTestCaseForm.errors.inputJson" />
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
