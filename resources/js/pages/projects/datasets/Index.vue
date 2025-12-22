<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import datasetRoutes from '@/routes/projects/datasets';
import { Link } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface DatasetListItem {
    id: number;
    name: string;
    description?: string | null;
    test_cases_count?: number;
}

interface Props {
    project: ProjectPayload;
    datasets: DatasetListItem[];
}

defineProps<Props>();
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Datasets">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-foreground">Datasets</h2>
                <p class="text-sm text-muted-foreground">
                    Collections of test inputs for chains.
                </p>
            </div>
            <Button as-child>
                <Link :href="datasetRoutes.create({ project: project.uuid }).url">New dataset</Link>
            </Button>
        </div>

        <div
            v-if="datasets.length === 0"
            class="mt-4 rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground"
        >
            No datasets yet. Create one to start organizing test cases.
        </div>

        <div v-else class="mt-4 overflow-hidden rounded-lg border border-border">
            <table class="min-w-full divide-y divide-border text-sm">
                <thead class="bg-muted text-xs font-semibold uppercase text-muted-foreground">
                    <tr class="text-left">
                        <th class="px-4 py-2">Name</th>
                        <th class="px-4 py-2">Description</th>
                        <th class="px-4 py-2">Test cases</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border bg-card">
                    <tr v-for="dataset in datasets" :key="dataset.id" class="hover:bg-muted/60">
                        <td class="px-4 py-2 font-semibold text-foreground">
                            <Link :href="datasetRoutes.show({ project: project.uuid, dataset: dataset.id }).url" class="text-primary">
                                {{ dataset.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-sm text-muted-foreground">
                            {{ dataset.description || '—' }}
                        </td>
                        <td class="px-4 py-2 text-sm text-muted-foreground">
                            {{ dataset.test_cases_count ?? 0 }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ProjectLayout>
</template>
