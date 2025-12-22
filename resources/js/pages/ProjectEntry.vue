<script setup lang="ts">
import { show as projectShow, index as projectsIndex } from '@/routes/projects';
import type { AppPageProps, ProjectSummary } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const STORAGE_KEY = 'prism:currentProjectUuid';
const page = usePage<AppPageProps>();

const projects = computed<ProjectSummary[]>(() => page.props.projects || []);

const getStoredProjectUuid = (): string | null => {
    if (typeof window === 'undefined') return null;

    return window.localStorage.getItem(STORAGE_KEY);
};

const isValidProjectUuid = (uuid: string | null) =>
    typeof uuid === 'string' && projects.value.some((project) => project.uuid === uuid);

const resolveTargetProject = (): string | null => {
    const stored = getStoredProjectUuid();
    if (isValidProjectUuid(stored)) return stored;

    const first = projects.value[0]?.uuid ?? null;
    return first;
};

const redirect = () => {
    const target = resolveTargetProject();
    if (target) {
        router.visit(projectShow({ project: target }).url);
        return;
    }

    router.visit(projectsIndex().url);
};

onMounted(() => {
    redirect();
});
</script>

<template>
    <Head title="Project" />
    <div class="flex min-h-screen items-center justify-center">
        <div class="text-sm text-muted-foreground">Loading your project…</div>
    </div>
</template>
