<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { create, edit } from '@/routes/provider-credentials';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';

interface ProviderOption {
    value: string;
    label: string;
}

interface CredentialListItem {
    id: number;
    name: string;
    provider: string;
    masked_api_key: string;
    created_at: string;
}

interface Props {
    credentials: CredentialListItem[];
    providers: ProviderOption[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Provider credentials',
        href: '/providers/credentials',
    },
];
</script>

<template>
    <Head title="Provider credentials" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">
                        Provider credentials
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Manage API keys for OpenAI, Anthropic and Google providers.
                    </p>
                </div>
                <Button as-child>
                    <Link :href="create().url">Add credential</Link>
                </Button>
            </div>

            <div
                v-if="credentials.length === 0"
                class="rounded-lg border border-border bg-card p-4 text-muted-foreground"
            >
                No credentials added yet. Create one to start using LLM providers.
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <Link
                    v-for="credential in credentials"
                    :key="credential.id"
                    :href="edit({ providerCredential: credential.id }).url"
                    class="block rounded-lg border border-border bg-card p-4 transition hover:border-primary"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-sm font-semibold text-foreground">{{ credential.name }}</span>
                            <span class="text-xs uppercase text-muted-foreground">
                                {{ credential.provider }}
                            </span>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Key: {{ credential.masked_api_key }}
                    </p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
