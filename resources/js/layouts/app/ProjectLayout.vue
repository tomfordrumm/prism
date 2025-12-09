<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { index as projectIndex, show as projectShow } from '@/routes/projects';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ProjectPayload {
    id: number;
    name: string;
    description?: string | null;
}

interface Props {
    project: ProjectPayload;
    titleSuffix?: string;
}

const props = defineProps<Props>();
const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Projects',
        href: projectIndex().url,
    },
    {
        title: props.project.name,
        href: projectShow({ project: props.project.id }).url,
    },
];

const tabs = computed(() => [
    {
        title: 'Overview',
        href: projectShow({ project: props.project.id }),
    },
    {
        title: 'Prompts',
        href: `/projects/${props.project.id}/prompts`,
    },
    {
        title: 'Chains',
        href: `/projects/${props.project.id}/chains`,
    },
    {
        title: 'Datasets',
        href: `/projects/${props.project.id}/datasets`,
    },
    {
        title: 'Runs',
        href: `/projects/${props.project.id}/runs`,
    },
]);

const pageTitle = computed(() =>
    props.titleSuffix ? `${props.project.name} • ${props.titleSuffix}` : props.project.name,
);

const normalize = (href: string | { url: string }) =>
    typeof href === 'string' ? href.replace(/\/+$/, '') : href.url.replace(/\/+$/, '');

const isActive = (href: string | { url: string }) => {
    const current = normalize(page.url);
    const target = normalize(href);

    if (target === normalize(projectShow({ project: props.project.id }).url)) {
        return current === target;
    }

    return current === target || current.startsWith(`${target}/`);
};
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold text-foreground">{{ project.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ project.description || 'Project container for prompts, chains and datasets.' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="tab in tabs"
                    :key="tab.title"
                    :href="typeof tab.href === 'string' ? tab.href : tab.href.url"
                    :class="
                        cn(
                            'rounded-md px-3 py-2 text-sm font-medium transition',
                            isActive(typeof tab.href === 'string' ? tab.href : tab.href.url)
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground hover:bg-muted',
                        )
                    "
                >
                    {{ tab.title }}
                </Link>
            </div>

            <slot />
        </div>
    </AppLayout>
</template>
