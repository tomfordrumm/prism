<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface Option {
    value: number | string;
    label: string;
}

interface RunForm {
    input: string;
    errors: Record<string, string>;
    processing: boolean;
}

interface RunDatasetForm {
    dataset_id: number | null;
    errors: Record<string, string>;
    processing: boolean;
}

interface InputField {
    path: string;
    name: string;
}

defineProps<{
    open: boolean;
    runMode: 'manual' | 'dataset';
    runForm: RunForm;
    runDatasetForm: RunDatasetForm;
    datasets: Option[];
    hasDatasets: boolean;
    inputFields: InputField[];
    inputValues: Record<string, string>;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'update:runMode', value: 'manual' | 'dataset'): void;
    (event: 'update:input', value: string): void;
    (event: 'update:dataset-id', value: number | null): void;
    (event: 'update:input-value', payload: { path: string; value: string }): void;
    (event: 'submit'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Run chain</DialogTitle>
                <DialogDescription>Provide input manually or run against a dataset.</DialogDescription>
            </DialogHeader>

            <div class="flex items-center gap-2 rounded-md border border-border p-1 text-sm">
                <Button
                    variant="ghost"
                    size="sm"
                    :class="runMode === 'manual' ? 'bg-primary/10 text-primary' : ''"
                    @click="emit('update:runMode', 'manual')"
                >
                    Manual input
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    :class="runMode === 'dataset' ? 'bg-primary/10 text-primary' : ''"
                    @click="emit('update:runMode', 'dataset')"
                >
                    Dataset
                </Button>
            </div>

            <div v-if="runMode === 'manual'" class="space-y-3">
                <div v-if="inputFields.length" class="grid gap-3">
                    <div v-for="field in inputFields" :key="field.path" class="grid gap-2">
                        <Label :for="`run_input_${field.path}`">{{ field.path }}</Label>
                        <input
                            :id="`run_input_${field.path}`"
                            type="text"
                            :value="inputValues[field.path] ?? ''"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                            @input="emit('update:input-value', { path: field.path, value: ($event.target as HTMLInputElement).value })"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="run_input_preview">Input (JSON preview)</Label>
                        <textarea
                            id="run_input_preview"
                            :value="runForm.input"
                            name="input_preview"
                            rows="5"
                            readonly
                            class="w-full rounded-md border border-input bg-muted px-3 py-2 text-xs text-muted-foreground shadow-sm"
                        ></textarea>
                    </div>
                    <InputError :message="runForm.errors.input" />
                </div>
                <div v-else class="grid gap-2">
                    <Label for="run_input">Input (JSON)</Label>
                    <textarea
                        id="run_input"
                        :value="runForm.input"
                        name="input"
                        rows="6"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        @input="emit('update:input', ($event.target as HTMLTextAreaElement).value)"
                    ></textarea>
                    <InputError :message="runForm.errors.input" />
                </div>
            </div>

            <div v-else class="space-y-3">
                <div class="grid gap-2">
                    <Label for="dataset_id">Dataset</Label>
                    <select
                        id="dataset_id"
                        :value="runDatasetForm.dataset_id ?? ''"
                        name="dataset_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        @change="emit('update:dataset-id', ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
                    >
                        <option value="">Select dataset</option>
                        <option v-for="dataset in datasets" :key="dataset.value" :value="dataset.value">
                            {{ dataset.label }}
                        </option>
                    </select>
                    <InputError :message="runDatasetForm.errors.dataset_id" />
                    <p v-if="!hasDatasets" class="text-xs text-muted-foreground">No datasets available.</p>
                </div>
            </div>

            <DialogFooter class="flex items-center justify-end gap-2">
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button
                    :disabled="runMode === 'dataset' ? runDatasetForm.processing : runForm.processing"
                    @click="emit('submit')"
                >
                    {{ runMode === 'dataset' ? 'Run on dataset' : 'Run' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
