<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { create, index, store } from '@/routes/projects';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm({
    name: '',
    description: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Projects',
        href: index().url,
    },
    {
        title: 'Create',
        href: create().url,
    },
];

const submit = () => {
    form.post(store().url);
};
</script>

<template>
    <Head title="Create project" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Create project</h1>
                    <p class="text-sm text-muted-foreground">
                        Define a new space for prompts, chains and datasets.
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="index().url">Back to list</Link>
                </Button>
            </div>

            <form class="space-y-4 rounded-lg border border-border bg-card p-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        v-model="form.name"
                        autocomplete="off"
                        placeholder="Quiz bot"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        name="description"
                        v-model="form.description"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        placeholder="Optional context about the project"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">Create</Button>
                    <p v-if="form.recentlySuccessful" class="text-sm text-muted-foreground">
                        Saved
                    </p>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
