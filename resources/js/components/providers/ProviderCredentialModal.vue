<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface ProviderOption {
    value: number | string;
    label: string;
}

interface ProviderForm {
    provider: string;
    name: string;
    api_key: string;
    metadataJson: string;
    errors: Record<string, string>;
    processing: boolean;
}

defineProps<{
    open: boolean;
    providerOptions: ProviderOption[];
    form: ProviderForm;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'update:provider', value: string): void;
    (event: 'update:name', value: string): void;
    (event: 'update:api-key', value: string): void;
    (event: 'update:metadata-json', value: string): void;
    (event: 'submit'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Add provider credential</DialogTitle>
                <DialogDescription>Save a new credential to use for chain steps.</DialogDescription>
            </DialogHeader>

            <div class="space-y-3">
                <div class="grid gap-2">
                    <Label for="provider">Provider</Label>
                    <select
                        id="provider"
                        :value="form.provider"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        @change="emit('update:provider', ($event.target as HTMLSelectElement).value)"
                    >
                        <option v-for="provider in providerOptions" :key="provider.value" :value="provider.value">
                            {{ provider.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.provider" />
                </div>

                <div class="grid gap-2">
                    <Label for="provider_name">Name</Label>
                    <Input
                        id="provider_name"
                        :model-value="form.name"
                        name="provider_name"
                        placeholder="OpenAI Sandbox"
                        required
                        @update:model-value="(value) => emit('update:name', value)"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="provider_api_key">API key</Label>
                    <Input
                        id="provider_api_key"
                        type="password"
                        :model-value="form.api_key"
                        name="provider_api_key"
                        autocomplete="off"
                        required
                        @update:model-value="(value) => emit('update:api-key', value)"
                    />
                    <InputError :message="form.errors.api_key" />
                </div>

                <div class="grid gap-2">
                    <Label for="provider_metadata">Metadata (JSON, optional)</Label>
                    <textarea
                        id="provider_metadata"
                        :value="form.metadataJson"
                        rows="3"
                        placeholder='{"baseUrl":"https://api.openai.com/v1"}'
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        @input="emit('update:metadata-json', ($event.target as HTMLTextAreaElement).value)"
                    ></textarea>
                    <InputError :message="form.errors.metadataJson" />
                </div>
            </div>

            <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button :disabled="form.processing" @click="emit('submit')">Save provider</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
