<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { destroy, edit, index, update } from '@/routes/provider-credentials';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ProviderOption {
    value: string;
    label: string;
}

interface CredentialPayload {
    id: number;
    name: string;
    provider: string;
    masked_api_key: string;
    metadata?: Record<string, unknown> | null;
}

interface Props {
    credential: CredentialPayload;
    providers: ProviderOption[];
}

const props = defineProps<Props>();

const metadataError = ref<string | null>(null);

const form = useForm({
    provider: props.credential.provider,
    name: props.credential.name,
    api_key: '',
    metadataJson: props.credential.metadata
        ? JSON.stringify(props.credential.metadata, null, 2)
        : '',
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Provider credentials',
        href: index().url,
    },
    {
        title: props.credential.name,
        href: edit({ providerCredential: props.credential.id }).url,
    },
];

const placeholderMetadata = computed(
    () => `{
  "baseUrl": "https://api.${form.provider}.com/v1"
}`,
);

const submit = () => {
    metadataError.value = null;

    let metadata: Record<string, unknown> | null = null;

    if (form.metadataJson.trim().length > 0) {
        try {
            metadata = JSON.parse(form.metadataJson) as Record<string, unknown>;
        } catch {
            metadataError.value = 'Metadata must be valid JSON';

            return;
        }
    }

    form
        .transform((data) => ({
            provider: data.provider,
            name: data.name,
            api_key: data.api_key || undefined,
            metadata,
        }))
        .put(update.url({ providerCredential: props.credential.id }));
};

const destroyCredential = () => {
    form.delete(destroy.url({ providerCredential: props.credential.id }));
};
</script>

<template>
    <Head :title="`Edit ${credential.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Edit credential</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ credential.name }} — {{ credential.masked_api_key }}
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="index().url">Back to list</Link>
                </Button>
            </div>

            <form class="space-y-4 rounded-lg border border-border bg-card p-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="provider">Provider</Label>
                    <select
                        id="provider"
                        name="provider"
                        v-model="form.provider"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option v-for="provider in providers" :key="provider.value" :value="provider.value">
                            {{ provider.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.provider" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        v-model="form.name"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="api_key">API key</Label>
                    <Input
                        id="api_key"
                        name="api_key"
                        v-model="form.api_key"
                        type="password"
                        autocomplete="off"
                        placeholder="Leave blank to keep current key"
                    />
                    <p class="text-xs text-muted-foreground">
                        Stored encrypted. Current key masked as {{ credential.masked_api_key }}.
                    </p>
                    <InputError :message="form.errors.api_key" />
                </div>

                <div class="grid gap-2">
                    <Label for="metadata">Metadata (optional, JSON)</Label>
                    <textarea
                        id="metadata"
                        name="metadata"
                        v-model="form.metadataJson"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        :placeholder="placeholderMetadata"
                    />
                    <InputError :message="metadataError || form.errors.metadata" />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">Save changes</Button>
                    <Button type="button" variant="destructive" @click="destroyCredential" :disabled="form.processing">
                        Delete
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
