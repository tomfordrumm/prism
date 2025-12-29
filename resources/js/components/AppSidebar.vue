<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { show as projectShow, store as projectsStore } from '@/routes/projects';
import { index as projectPromptsIndex } from '@/routes/projects/prompts';
import { index as projectChainsIndex } from '@/routes/projects/chains';
import { index as projectDatasetsIndex } from '@/routes/projects/datasets';
import { index as projectRunsIndex } from '@/routes/projects/runs';
import { index as providerCredentialsIndex } from '@/routes/provider-credentials';
import type { AppPageProps, NavItem, ProjectSummary } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Database,
    FileText,
    Folder,
    FolderTree,
    History,
    KeyRound,
    LayoutGrid,
    Workflow,
} from 'lucide-vue-next';
import Select from 'primevue/select';
import AppLogo from './AppLogo.vue';
import { computed, onMounted, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

const mainNavItems: NavItem[] = [
    {
        title: 'Provider Credentials',
        href: providerCredentialsIndex(),
        icon: KeyRound,
        routeName: 'provider-credentials.index',
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: '/docs',
        icon: BookOpen,
    },
];

const page = usePage<AppPageProps<{ project?: ProjectSummary }>>();

const STORAGE_KEY = 'prism:currentProjectUuid';
const CREATE_OPTION_VALUE = '__create_project';

const projects = computed<ProjectSummary[]>(() => page.props.projects || []);
const isProjectScoped = computed(() => page.url.startsWith('/projects'));
const projectOptions = computed(() => [
    ...projects.value,
    { uuid: CREATE_OPTION_VALUE, name: '+ Create Project', id: -1 },
]);
const resolvedCurrentProjectUuid = computed<string | null>(() => {
    const pageProject = page.props.project;

    if (
        pageProject &&
        typeof pageProject === 'object' &&
        'uuid' in pageProject
    ) {
        return (pageProject as { uuid?: string | null }).uuid ?? null;
    }

    return page.props.currentProjectUuid ?? null;
});

const selectedProjectUuid = ref<string | null>(
    resolvedCurrentProjectUuid.value,
);

const getStoredProjectUuid = (): string | null => {
    if (typeof window === 'undefined') return null;

    return window.localStorage.getItem(STORAGE_KEY);
};

const setStoredProjectUuid = (projectUuid: string | null) => {
    if (typeof window === 'undefined') return;
    if (!projectUuid) {
        window.localStorage.removeItem(STORAGE_KEY);
        return;
    }

    window.localStorage.setItem(STORAGE_KEY, projectUuid);
};

const isValidProjectUuid = (projectUuid: string | null) =>
    typeof projectUuid === 'string' &&
    projects.value.some((project) => project.uuid === projectUuid);

const navigateToProject = (projectUuid: string) => {
    if (projectUuid === resolvedCurrentProjectUuid.value) {
        return;
    }

    router.visit(projectShow({ project: projectUuid }).url);
};

const setProjectSelection = (
    projectUuid: string | null,
    shouldNavigate = false,
) => {
    if (!projectUuid) return;

    selectedProjectUuid.value = projectUuid;
    setStoredProjectUuid(projectUuid);

    if (shouldNavigate) {
        navigateToProject(projectUuid);
    }
};

const initializeProjectSelection = () => {
    const resolved = isValidProjectUuid(resolvedCurrentProjectUuid.value)
        ? resolvedCurrentProjectUuid.value
        : null;
    const stored = isValidProjectUuid(getStoredProjectUuid())
        ? getStoredProjectUuid()
        : null;
    const first = projects.value.length > 0 ? projects.value[0].uuid : null;

    const target = resolved ?? stored ?? first;

    if (target) {
        const shouldNavigate =
            isProjectScoped.value && (!resolved || resolved !== target);
        setProjectSelection(target, shouldNavigate);
    } else {
        setStoredProjectUuid(null);
    }
};

watch(resolvedCurrentProjectUuid, (projectUuid) => {
    selectedProjectUuid.value = projectUuid ?? null;
});

watch(
    projects,
    () => {
        if (isValidProjectUuid(selectedProjectUuid.value)) return;
        initializeProjectSelection();
    },
    { immediate: false },
);

onMounted(() => {
    initializeProjectSelection();
});

const handleProjectChange = (event: { value: string | null }) => {
    if (event.value === CREATE_OPTION_VALUE) {
        selectedProjectUuid.value =
            resolvedCurrentProjectUuid.value ??
            selectedProjectUuid.value ??
            null;
        createProjectOpen.value = true;
        return;
    }

    if (!event.value || event.value === resolvedCurrentProjectUuid.value) {
        return;
    }

    setProjectSelection(event.value, true);
};

const selectedProjectName = computed(() => {
    const project = projects.value.find(
        (p) => p.uuid === selectedProjectUuid.value,
    );

    return project?.name ?? 'Project';
});

const projectNavItems = computed(() => {
    if (!selectedProjectUuid.value) return [];

    const projectUuid = selectedProjectUuid.value;

    return [
        {
            title: 'Dashboard',
            href: projectShow({ project: projectUuid }).url,
            icon: LayoutGrid,
        },
        {
            title: 'Prompts',
            href: projectPromptsIndex({ project: projectUuid }).url,
            icon: FileText,
        },
        {
            title: 'Chains',
            href: projectChainsIndex({ project: projectUuid }).url,
            icon: Workflow,
        },
        {
            title: 'Datasets',
            href: projectDatasetsIndex({ project: projectUuid }).url,
            icon: Database,
        },
        {
            title: 'Runs',
            href: projectRunsIndex({ project: projectUuid }).url,
            icon: History,
        },
    ];
});

const isActive = (href: string | { url: string }) =>
    urlIsActive(href, page.url);

const createProjectOpen = ref(false);
const createForm = useForm({
    name: '',
    description: '',
});

const resetCreateForm = () => {
    createForm.reset();
    createForm.clearErrors();
};

const submitCreateProject = () => {
    createForm
        .transform((data) => ({
            name: data.name,
            description: data.description || null,
        }))
        .post(projectsStore().url, {
            preserveScroll: true,
            onSuccess: () => {
                createProjectOpen.value = false;
                resetCreateForm();
            },
        });
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/project">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="pb-0">
                <SidebarGroupLabel>Projects</SidebarGroupLabel>
                <div class="px-2">
                    <Select
                        v-model="selectedProjectUuid"
                        :options="projectOptions"
                        optionLabel="name"
                        optionValue="uuid"
                        placeholder="Select project"
                        class="w-full"
                        filter
                        size="small"
                        :disabled="projectOptions.length === 0"
                        @change="handleProjectChange"
                    />
                </div>
            </SidebarGroup>

            <SidebarGroup class="pt-1">
                <div
                    v-if="!selectedProjectUuid"
                    class="px-3 py-2 text-sm text-muted-foreground"
                >
                    Select a project to view resources.
                </div>
                <SidebarMenu v-else class="pt-1">
                    <SidebarMenuItem>
                        <SidebarMenuButton as="div" class="cursor-default">
                            <FolderTree />
                            <span class="truncate">Project Resources</span>
                        </SidebarMenuButton>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="item in projectNavItems"
                                :key="item.title"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isActive(item.href)"
                                >
                                    <Link :href="item.href">
                                        <component :is="item.icon" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />

    <Dialog v-model:open="createProjectOpen">
        <DialogContent class="sm:max-w-[440px]">
            <DialogHeader>
                <DialogTitle>Create Project</DialogTitle>
                <DialogDescription
                    >Add a new project to organize prompts and
                    chains.</DialogDescription
                >
            </DialogHeader>
            <div class="space-y-3 py-2">
                <div class="space-y-1.5">
                    <Label for="project_name">Name</Label>
                    <Input
                        id="project_name"
                        v-model="createForm.name"
                        :disabled="createForm.processing"
                        placeholder="My LLM project"
                    />
                    <InputError :message="createForm.errors.name" />
                </div>
                <div class="space-y-1.5">
                    <Label for="project_description">Description</Label>
                    <textarea
                        id="project_description"
                        v-model="createForm.description"
                        :disabled="createForm.processing"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:ring-1 focus:ring-primary focus:outline-none disabled:cursor-not-allowed"
                        placeholder="What this project is for"
                    ></textarea>
                    <InputError :message="createForm.errors.description" />
                </div>
            </div>
            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    type="button"
                    @click="createProjectOpen = false"
                    >Cancel</Button
                >
                <Button
                    type="button"
                    :disabled="createForm.processing"
                    @click="submitCreateProject"
                >
                    Create
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
