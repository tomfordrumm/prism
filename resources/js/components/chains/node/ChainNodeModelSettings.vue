<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import InputError from '@/components/InputError.vue';
import Select from 'primevue/select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

interface NodeForm {
    model_name: string;
    temperature: number | null;
    max_tokens: number | null;
    errors: Record<string, string>;
}

const props = defineProps<{
    form: NodeForm;
    providerOptions: ProviderSelectOption[];
    modelOptions: ModelSelectOption[];
    selectedProviderId: number | null;
    selectedModelChoice: string;
    isCustomModel: boolean;
    showAdvanced: boolean;
}>();

const emit = defineEmits<{
    (event: 'update:provider-credential-id', value: number | null): void;
    (event: 'update:model-choice', value: string): void;
    (event: 'update:showAdvanced', value: boolean): void;
    (event: 'update:model-name', value: string): void;
    (event: 'update:temperature', value: number | null): void;
    (event: 'update:max-tokens', value: number | null): void;
}>();

const toggleAdvanced = () => emit('update:showAdvanced', !props.showAdvanced);
</script>

<template>
    <div class="space-y-4 border-b border-border/60 pb-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Step settings</p>
        <div class="grid gap-3 md:grid-cols-2">
            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="provider_selection">Provider</Label>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-6 w-6"
                        @click="toggleAdvanced"
                    >
                        <Icon name="settings" class="h-3.5 w-3.5 text-muted-foreground" />
                    </Button>
                </div>
                <Select
                    :model-value="selectedProviderId"
                    inputId="provider_selection"
                    :options="providerOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Select a provider"
                    filter
                    :filterFields="['label']"
                    size="small"
                    class="w-full"
                    :disabled="!providerOptions.length"
                    @update:model-value="(value) => emit('update:provider-credential-id', value)"
                />
                <p v-if="!providerOptions.length" class="text-xs text-muted-foreground">
                    No provider credentials available yet.
                </p>
                <InputError :message="form.errors.provider_credential_id" />
            </div>

            <div class="grid gap-2">
                <Label for="model_selection">Model</Label>
                <Select
                    :model-value="selectedModelChoice"
                    inputId="model_selection"
                    :options="modelOptions"
                    optionLabel="label"
                    optionValue="value"
                    :placeholder="selectedProviderId ? 'Select a model' : 'Select provider first'"
                    filter
                    :filterFields="['label']"
                    size="small"
                    class="w-full"
                    :disabled="!selectedProviderId"
                    @update:model-value="(value) => emit('update:model-choice', value)"
                />
                <InputError :message="form.errors.model_name" />
            </div>
        </div>

        <div v-if="isCustomModel" class="grid gap-2">
            <Label for="custom_model_name">Custom model name</Label>
            <Input
                id="custom_model_name"
                :model-value="form.model_name"
                name="model_name"
                placeholder="custom-model-1"
                required
                class="py-1.5"
                @update:model-value="(value) => emit('update:model-name', value)"
            />
            <InputError :message="form.errors.model_name" />
        </div>

        <div v-if="showAdvanced" class="grid gap-3 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="temperature">Temperature</Label>
                <Input
                    id="temperature"
                    type="number"
                    step="0.1"
                    min="0"
                    max="2"
                    :model-value="form.temperature"
                    name="temperature"
                    class="py-1.5"
                    @update:model-value="(value) => emit('update:temperature', value === '' ? null : Number(value))"
                />
            </div>

            <div class="grid gap-2">
                <Label for="max_tokens">Max tokens</Label>
                <Input
                    id="max_tokens"
                    type="number"
                    min="1"
                    :model-value="form.max_tokens"
                    name="max_tokens"
                    class="py-1.5"
                    @update:model-value="(value) => emit('update:max-tokens', value === '' ? null : Number(value))"
                />
            </div>
        </div>
    </div>
</template>
