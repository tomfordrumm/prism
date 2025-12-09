<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import chainRoutes from '@/routes/projects/chains';
import { useForm } from '@inertiajs/vue3';

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
});

const submit = () => {
    form.transform((data) => ({
        name: data.name,
        description: data.description || null,
    })).post(chainRoutes.store(props.project.id).url);
};
</script>

<template>
    <ProjectLayout :project="project" title-suffix="New Chain">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-foreground">New chain</h2>
                <p class="text-sm text-muted-foreground">
                    Define a new linear flow of LLM steps for this project.
                </p>
            </div>
        </div>

        <form class="mt-4 space-y-4 rounded-lg border border-border bg-card p-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    name="name"
                    placeholder="quiz_generation_chain"
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
                    placeholder="What this chain should do"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                ></textarea>
                <InputError :message="form.errors.description" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Create chain</Button>
                <Button variant="outline" :href="chainRoutes.index(project.id).url" as="a">Cancel</Button>
            </div>
        </form>
    </ProjectLayout>
</template>
