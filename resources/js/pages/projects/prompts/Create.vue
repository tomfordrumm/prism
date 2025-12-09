<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import prompts from '@/routes/projects/prompts';
import { Head, useForm } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ProjectPayload {
    id: number;
    name: string;
    description?: string | null;
}

interface Props {
    project: ProjectPayload;
}

const props = defineProps<Props>();

const form = useForm({
    name: '',
    description: '',
    initial_content: '',
    initial_changelog: 'Initial version',
});

const submit = () => {
    form.transform((data) => ({
        name: data.name,
        description: data.description || null,
        initial_content: data.initial_content,
        initial_changelog: data.initial_changelog || 'Initial version',
    })).post(prompts.store({ project: props.project.id }).url);
};
</script>

<template>
    <Head title="New prompt template" />
    <ProjectLayout :project="project" title-suffix="New Prompt">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-foreground">New prompt template</h2>
                <p class="text-sm text-muted-foreground">Create a reusable prompt for this project.</p>
            </div>
        </div>

        <form class="mt-4 space-y-4 rounded-lg border border-border bg-card p-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    name="name"
                    placeholder="quiz_expand_topic_system"
                    required
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    name="description"
                    rows="3"
                    placeholder="Purpose of this template"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                ></textarea>
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-2">
                <Label for="initial_content">Initial content</Label>
                <textarea
                    id="initial_content"
                    v-model="form.initial_content"
                    name="initial_content"
                    rows="6"
                    required
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    placeholder="Enter first prompt content"
                ></textarea>
                <InputError :message="form.errors.initial_content" />
                <p class="text-xs text-muted-foreground">
                    Variables are extracted automatically from <code v-pre>{{ variable }}</code> placeholders after saving.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="initial_changelog">Initial changelog</Label>
                <textarea
                    id="initial_changelog"
                    v-model="form.initial_changelog"
                    name="initial_changelog"
                    rows="2"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    placeholder="Initial version"
                ></textarea>
                <InputError :message="form.errors.initial_changelog" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Create template</Button>
                <Button variant="outline" :href="prompts.index({ project: project.id }).url" as="a">Cancel</Button>
            </div>
        </form>
    </ProjectLayout>
</template>
