<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Icon from '@/components/Icon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, store } from '@/routes/provider-credentials';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import { computed, ref, watch } from 'vue';

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

const props = defineProps<Props>();
const dialogOpen = ref(false);

const openaiLogo = new URL('../../../../assets/providers/OpenAI.svg', import.meta.url).href;
const anthropicLogo = new URL('../../../../assets/providers/anthropic.svg', import.meta.url).href;
const googleLogo = new URL('../../../../assets/providers/google.svg', import.meta.url).href;
const openrouterLogo = new URL('../../../../assets/providers/openrouter.svg', import.meta.url).href;

const form = useForm({
    provider: props.providers[0]?.value ?? 'openai',
    name: '',
    api_key: '',
});

const providerLabelByValue = computed(() =>
    Object.fromEntries(props.providers.map((provider) => [provider.value, provider.label])),
);

const openDialog = () => {
    dialogOpen.value = true;
};

const closeDialog = () => {
    dialogOpen.value = false;
    form.reset();
    form.clearErrors();
};

watch(dialogOpen, (visible) => {
    if (!visible) {
        form.reset();
        form.clearErrors();
    }
});

const submit = () => {
    form.transform((data) => ({
        provider: data.provider,
        name: data.name,
        api_key: data.api_key,
        metadata: null,
    })).post(store().url, {
        onSuccess: () => {
            closeDialog();
        },
    });
};

const handleDelete = (credentialId: number) => {
    if (!confirm('Delete this credential?')) return;
    router.delete(`/providers/credentials/${credentialId}`);
};

const providerBadgeLabel = (provider: string) => providerLabelByValue.value[provider] ?? provider;
const providerLogoSrc = (provider: string) => {
    switch (provider) {
        case 'openai':
            return openaiLogo;
        case 'anthropic':
            return anthropicLogo;
        case 'google':
            return googleLogo;
        case 'openrouter':
            return openrouterLogo;
        default:
            return null;
    }
};

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
                        Manage API keys for your LLM providers.
                    </p>
                </div>
                <Button @click="openDialog">Add credential</Button>
            </div>

            <div
                v-if="credentials.length === 0"
                class="rounded-lg border border-border bg-card p-4 text-muted-foreground"
            >
                No credentials added yet. Create one to start using LLM providers.
            </div>

            <div class="flex flex-col gap-4">
                <div
                    v-for="credential in credentials"
                    :key="credential.id"
                    class="group flex w-full items-center justify-between gap-4 rounded-lg border border-border bg-card px-4 py-3 transition hover:bg-muted/40"
                >
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-md border border-border bg-muted/40">
                            <img
                                v-if="providerLogoSrc(credential.provider)"
                                :src="providerLogoSrc(credential.provider) as string"
                                :alt="providerBadgeLabel(credential.provider)"
                                class="h-7 w-7 object-contain"
                            />
                            <Icon v-else name="server" class="h-6 w-6 text-muted-foreground" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-foreground">{{ credential.name }}</span>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-emerald-700">
                                    Configured
                                </span>
                            </div>
                            <p class="mt-1 text-xs uppercase text-muted-foreground">
                                {{ providerBadgeLabel(credential.provider) }}
                            </p>
                            <p class="mt-2 text-sm font-mono text-muted-foreground">
                                {{ credential.masked_api_key }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 opacity-40 transition group-hover:opacity-100">
                        <Link
                            :href="edit({ providerCredential: credential.id }).url"
                            class="rounded-md p-2 text-muted-foreground transition hover:text-foreground"
                        >
                            <Icon name="pencil" class="h-4 w-4" />
                        </Link>
                        <button
                            type="button"
                            class="rounded-md p-2 text-muted-foreground transition hover:text-destructive"
                            @click="handleDelete(credential.id)"
                        >
                            <Icon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <Dialog v-model:visible="dialogOpen" modal header="Add credential" :style="{ width: '420px' }">
            <div class="space-y-4">
                <div class="grid gap-2">
                    <Label for="provider">Provider</Label>
                    <select
                        id="provider"
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
                    <Label for="credential_name">Friendly name</Label>
                    <Input
                        id="credential_name"
                        v-model="form.name"
                        name="credential_name"
                        placeholder="OpenAI Production"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="credential_key">API key</Label>
                    <Input
                        id="credential_key"
                        v-model="form.api_key"
                        type="password"
                        name="credential_key"
                        placeholder="sk-..."
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.api_key" />
                </div>
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <Button variant="outline" @click="closeDialog">Cancel</Button>
                    <Button :disabled="form.processing" @click="submit">Save</Button>
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
