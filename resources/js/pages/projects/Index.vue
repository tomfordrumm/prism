<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { create as createProject, show as showProject } from '@/routes/projects';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';

interface ProjectListItem {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface Props {
    projects: ProjectListItem[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Projects',
        href: '/projects',
    },
];
</script>

<template>
    <Head title="Projects" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Projects</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage workspaces for prompts, chains, datasets and runs.
                    </p>
                </div>
                <Button as-child>
                    <Link :href="createProject().url">Create project</Link>
                </Button>
            </div>

            <div
                v-if="projects.length === 0"
                class="rounded-lg border border-border bg-card p-4 text-muted-foreground"
            >
                No projects yet. Create one to get started.
            </div>

            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="project in projects"
                    :key="project.uuid"
                    :href="showProject({ project: project.uuid }).url"
                    class="block rounded-lg border border-border bg-card p-4 transition hover:border-primary"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-foreground">
                            {{ project.name }}
                        </h2>
                        <span class="text-xs uppercase text-muted-foreground">Open</span>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground line-clamp-3">
                        {{ project.description || 'No description provided' }}
                    </p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
