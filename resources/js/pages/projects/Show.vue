<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import chainRoutes from '@/routes/projects/chains';
import datasetRoutes from '@/routes/projects/datasets';
import promptRoutes from '@/routes/projects/prompts';
import runRoutes from '@/routes/projects/runs';
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
    prompt_templates_count?: number;
    chains_count?: number;
    runs_count?: number;
}

defineProps<{
    project: ProjectPayload;
    recentActivity: ActivityItem[];
    lastMonthTokens: number;
}>();

interface ActivityItem {
    id: string;
    type: 'run' | 'prompt' | 'dataset' | 'chain';
    title: string;
    description: string;
    timestamp: string | null;
    href?: string;
    status?: string;
}

const activityTypeLabels: Record<ActivityItem['type'], string> = {
    run: 'Run',
    prompt: 'Prompt',
    dataset: 'Dataset',
    chain: 'Chain',
};

const statusClasses: Record<string, string> = {
    success: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-red-100 text-red-700',
    running: 'bg-blue-100 text-blue-700',
    pending: 'bg-amber-100 text-amber-700',
};

const formatTimestamp = (value: string | null) => {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString();
};

const formatNumber = (value: number) => value.toLocaleString();
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Overview">
        <div class="flex flex-col gap-6">
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-xs uppercase text-muted-foreground">Prompts</p>
                            <p class="text-2xl font-semibold text-foreground">
                                {{ project.prompt_templates_count ?? 0 }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-xs uppercase text-muted-foreground">Chains</p>
                            <p class="text-2xl font-semibold text-foreground">
                                {{ project.chains_count ?? 0 }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-xs uppercase text-muted-foreground">Last 30 days tokens</p>
                            <Badge variant="secondary" class="mt-2">
                                {{ formatNumber(lastMonthTokens) }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-card p-4">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Quick Start</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Create the core building blocks for this project.
                    </p>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <Link
                        :href="promptRoutes.create({ project: project.uuid }).url"
                        class="group rounded-lg border border-border bg-background p-4 transition hover:border-primary"
                    >
                        <p class="text-xs uppercase text-muted-foreground">Prompt</p>
                        <p class="mt-2 text-base font-semibold text-foreground">Create prompt</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Draft a reusable template and version it as you iterate.
                        </p>
                    </Link>
                    <Link
                        :href="datasetRoutes.create({ project: project.uuid }).url"
                        class="group rounded-lg border border-border bg-background p-4 transition hover:border-primary"
                    >
                        <p class="text-xs uppercase text-muted-foreground">Dataset</p>
                        <p class="mt-2 text-base font-semibold text-foreground">Create dataset</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Define test cases to validate chains with real inputs.
                        </p>
                    </Link>
                    <Link
                        :href="chainRoutes.create(project.uuid).url"
                        class="group rounded-lg border border-border bg-background p-4 transition hover:border-primary"
                    >
                        <p class="text-xs uppercase text-muted-foreground">Chain</p>
                        <p class="mt-2 text-base font-semibold text-foreground">Create chain</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Connect prompts into a linear flow of LLM calls.
                        </p>
                    </Link>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Recent Activity</h2>
                        <p class="text-sm text-muted-foreground">
                            Latest runs, prompt updates, and dataset changes.
                        </p>
                    </div>
                    <Link
                        :href="runRoutes.index({ project: project.uuid }).url"
                        class="text-sm font-semibold text-primary"
                    >
                        View all runs
                    </Link>
                </div>

                <div v-if="recentActivity.length === 0" class="p-4 text-sm text-muted-foreground">
                    No activity yet. Create prompts, datasets, or run a chain to see updates here.
                </div>

                <div v-else class="divide-y divide-border">
                    <div
                        v-for="activity in recentActivity"
                        :key="activity.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <span class="rounded-md bg-muted px-2 py-1 text-xs font-semibold text-muted-foreground">
                                {{ activityTypeLabels[activity.type] }}
                            </span>
                            <div class="min-w-0">
                                <Link
                                    v-if="activity.href"
                                    :href="activity.href"
                                    class="block truncate text-sm font-semibold text-foreground hover:text-primary"
                                >
                                    {{ activity.title }}
                                </Link>
                                <p v-else class="text-sm font-semibold text-foreground">
                                    {{ activity.title }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ activity.description }}
                                </p>
                                <span
                                    v-if="activity.type === 'run' && activity.status"
                                    class="mt-2 inline-flex rounded-md px-2 py-1 text-xs font-semibold"
                                    :class="statusClasses[activity.status] || 'bg-muted text-muted-foreground'"
                                >
                                    {{ activity.status.toUpperCase() }}
                                </span>
                            </div>
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ formatTimestamp(activity.timestamp) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ProjectLayout>
</template>
