<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';

interface ProjectPayload {
    id: number;
    name: string;
    description?: string | null;
}

defineProps<{
    project: ProjectPayload;
}>();
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Overview">
        <div class="rounded-lg border border-border bg-card p-4">
            <h2 class="text-lg font-semibold text-foreground">Details</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ project.description || 'No description provided yet.' }}
            </p>
        </div>
    </ProjectLayout>
</template>
