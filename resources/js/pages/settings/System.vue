<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Select from 'primevue/select';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { computed } from 'vue';

interface ProviderCredentialOption {
    value: number;
    label: string;
    provider: string;
}

interface Props {
    settings: {
        improvement_provider_credential_id: number | null;
        improvement_model_name: string | null;
    };
    providerCredentials: ProviderCredentialOption[];
    providerCredentialModels: Record<number, { id: string; name: string; display_name: string }[]>;
}

const props = defineProps<Props>();
const toast = useToast();

const form = useForm({
    improvement_provider_credential_id: props.settings.improvement_provider_credential_id,
    improvement_model_name: props.settings.improvement_model_name ?? '',
});

const modelOptions = computed(() => {
    if (!form.improvement_provider_credential_id) return [];
    return props.providerCredentialModels[form.improvement_provider_credential_id] ?? [];
});

const handleProviderChange = () => {
    const firstModel = modelOptions.value[0];
    form.improvement_model_name = firstModel ? firstModel.id : '';
};

const submit = () => {
    form.put('/settings/system', {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Settings saved',
                detail: 'System settings updated.',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Save failed',
                detail: 'Please fix the errors and try again.',
                life: 4000,
            });
        },
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'System settings',
        href: '/settings/system',
    },
];
</script>

<template>
    <Head title="System settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <Toast />
            <div>
                <h1 class="text-xl font-semibold text-foreground">System settings</h1>
                <p class="text-sm text-muted-foreground">
                    Define defaults for improvement and analysis workflows.
                </p>
            </div>

            <div class="rounded-lg border border-border bg-card p-4">
                <div class="space-y-2">
                    <h2 class="text-base font-semibold text-foreground">Improvement model</h2>
                    <p class="text-sm text-muted-foreground">
                        This model will be used by default for prompt improvements and analyses.
                    </p>
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="system_improvement_provider">Provider credential</Label>
                        <Select
                            inputId="system_improvement_provider"
                            :model-value="form.improvement_provider_credential_id"
                            :options="providerCredentials"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select provider"
                            filter
                            :filterFields="['label']"
                            class="w-full"
                            @update:model-value="(value) => { form.improvement_provider_credential_id = value; handleProviderChange(); }"
                        />
                        <InputError :message="form.errors.improvement_provider_credential_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="system_improvement_model">Model</Label>
                        <Select
                            inputId="system_improvement_model"
                            :model-value="form.improvement_model_name"
                            :options="modelOptions"
                            optionLabel="display_name"
                            optionValue="id"
                            placeholder="Select model"
                            filter
                            :filterFields="['display_name', 'name']"
                            class="w-full"
                            :disabled="!form.improvement_provider_credential_id"
                            @update:model-value="(value) => { form.improvement_model_name = value; }"
                        />
                        <p v-if="form.improvement_provider_credential_id && !modelOptions.length" class="text-xs text-muted-foreground">
                            No models available for this credential yet.
                        </p>
                        <InputError :message="form.errors.improvement_model_name" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <Button :disabled="form.processing" @click="submit">Save settings</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
