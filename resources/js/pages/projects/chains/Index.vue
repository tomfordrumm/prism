<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import chainRoutes from '@/routes/projects/chains';
import { Link } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface ChainListItem {
    id: number;
    name: string;
    description?: string | null;
    nodes_count?: number;
}

interface Props {
    project: ProjectPayload;
    chains: ChainListItem[];
}

defineProps<Props>();
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Chains">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-foreground">Chains</h2>
                <p class="text-sm text-muted-foreground">
                    Linear sequences of LLM steps for this project.
                </p>
            </div>
            <Button as-child>
                <Link :href="chainRoutes.create(project.uuid).url">New chain</Link>
            </Button>
        </div>

        <div
            v-if="chains.length === 0"
            class="mt-4 rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground"
        >
            No chains yet. Create your first chain to start defining steps.
        </div>

        <div v-else class="mt-4 grid gap-3 lg:grid-cols-2">
            <Link
                v-for="chain in chains"
                :key="chain.id"
                :href="chainRoutes.show({ project: project.uuid, chain: chain.id }).url"
                class="block rounded-lg border border-border bg-card p-4 transition hover:border-primary"
            >
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-foreground">
                        {{ chain.name }}
                    </h3>
                    <span class="text-xs text-muted-foreground">
                        {{ chain.nodes_count ?? 0 }} steps
                    </span>
                </div>
                <p class="mt-2 text-sm text-muted-foreground line-clamp-2">
                    {{ chain.description || 'No description provided' }}
                </p>
            </Link>
        </div>
    </ProjectLayout>
</template>
