<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { show as projectShow } from '@/routes/projects';
import {
    index as projectPromptsIndex,
} from '@/routes/projects/prompts';
import {
    index as projectChainsIndex,
} from '@/routes/projects/chains';
import {
    index as projectDatasetsIndex,
} from '@/routes/projects/datasets';
import {
    index as projectRunsIndex,
} from '@/routes/projects/runs';
import type { BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface Props {
    project: ProjectPayload;
    titleSuffix?: string;
}

const props = defineProps<Props>();
const page = usePage();

const sectionBreadcrumb = computed<BreadcrumbItem | null>(() => {
    const url = page.url.replace(/\/+$/, '');
    const projectKey = props.project.uuid;

    if (url.includes(`/projects/${projectKey}/prompts`)) {
        return {
            title: 'Prompts',
            href: projectPromptsIndex({ project: projectKey }).url,
        };
    }

    if (url.includes(`/projects/${projectKey}/chains`)) {
        return {
            title: 'Chains',
            href: projectChainsIndex({ project: projectKey }).url,
        };
    }

    if (url.includes(`/projects/${projectKey}/datasets`)) {
        return {
            title: 'Datasets',
            href: projectDatasetsIndex({ project: projectKey }).url,
        };
    }

    if (url.includes(`/projects/${projectKey}/runs`)) {
        return {
            title: 'Runs',
            href: projectRunsIndex({ project: projectKey }).url,
        };
    }

    return {
        title: 'Dashboard',
        href: projectShow({ project: projectKey }).url,
    };
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const items: BreadcrumbItem[] = [
        {
            title: props.project.name,
            href: projectShow({ project: props.project.uuid }).url,
        },
    ];

    if (sectionBreadcrumb.value) {
        items.push(sectionBreadcrumb.value);
    }

    return items;
});

const pageTitle = computed(() =>
    props.titleSuffix ? `${props.project.name} • ${props.titleSuffix}` : props.project.name,
);
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <slot />
        </div>
    </AppLayout>
</template>
