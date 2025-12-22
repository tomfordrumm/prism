<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';

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
}>();
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Overview">
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
                        <p class="text-xs uppercase text-muted-foreground">Runs</p>
                        <p class="text-2xl font-semibold text-foreground">
                            {{ project.runs_count ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-border bg-card p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Details</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ project.description || 'No description provided yet.' }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="`/projects/${project.uuid}/prompts/create`"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:shadow"
                    >
                        Create prompt
                    </Link>
                    <Link
                        :href="`/projects/${project.uuid}/chains/create`"
                        class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground hover:bg-muted"
                    >
                        Create chain
                    </Link>
                </div>
            </div>
        </div>
    </ProjectLayout>
</template>
