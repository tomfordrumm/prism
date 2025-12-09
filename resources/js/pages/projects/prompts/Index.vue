<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import prompts from '@/routes/projects/prompts';
import promptVersions from '@/routes/projects/prompts/versions';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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

interface ProjectPayload {
    id: number;
    name: string;
    description?: string | null;
}

interface TemplateListItem {
    id: number;
    name: string;
    description?: string | null;
    latest_version?: number | null;
}

interface TemplateVariable {
    name: string;
    type?: string;
    description?: string | null;
}

interface TemplatePayload {
    id: number;
    name: string;
    description?: string | null;
    variables?: TemplateVariable[] | null;
}

interface VersionPayload {
    id: number;
    version: number;
    changelog?: string | null;
    created_at: string;
    content?: string;
}

interface Props {
    project: ProjectPayload;
    templates: TemplateListItem[];
    selectedTemplate: TemplatePayload | null;
    versions: VersionPayload[];
    selectedVersion: VersionPayload | null;
}

const props = defineProps<Props>();

const selectedTemplateId = ref<number | null>(props.selectedTemplate?.id ?? props.templates[0]?.id ?? null);
const selectedVersion = ref<VersionPayload | null>(props.selectedVersion ?? props.versions[0] ?? null);
const editorContent = ref(selectedVersion.value?.content ?? '');
const changelog = ref('');
const showVersions = ref(false);
const showVariables = ref(false);
const showCreateModal = ref(false);
const search = ref('');

const filteredTemplates = computed(() => {
    if (!search.value.trim()) return props.templates;
    const term = search.value.toLowerCase();
    return props.templates.filter(
        (tpl) =>
            tpl.name.toLowerCase().includes(term) ||
            (tpl.description ?? '').toLowerCase().includes(term)
    );
});

const selectedTemplate = computed(() =>
    props.selectedTemplate && props.selectedTemplate.id === selectedTemplateId.value
        ? props.selectedTemplate
        : null
);

const templateVersions = computed(() =>
    selectedTemplate.value ? props.versions : []
);

const hasChanges = computed(
    () => !!selectedVersion.value && editorContent.value !== (selectedVersion.value.content ?? '')
);

const selectTemplate = (templateId: number) => {
    if (templateId === selectedTemplateId.value) return;

    router.get(
        prompts.index(
            { project: props.project.id },
            { preserveScroll: true, replace: true, query: { prompt_id: templateId } }
        ).url,
        {},
        {
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                selectedTemplateId.value = templateId;
            },
        }
    );
};

const loadVersion = (version: VersionPayload) => {
    selectedVersion.value = version;
    editorContent.value = version.content ?? '';
    changelog.value = '';

    if (selectedTemplateId.value) {
        history.replaceState(
            {},
            '',
            prompts
                .index(
                    { project: props.project.id },
                    { query: { prompt_id: selectedTemplateId.value, version: version.version } }
                )
                .url
        );
    }
};

const versionForm = useForm({
    content: '',
    changelog: '',
});

const submitVersion = () => {
    if (!selectedTemplate.value) return;

    versionForm
        .transform(() => ({
            content: editorContent.value,
            changelog: changelog.value || null,
        }))
        .post(
            promptVersions.store({
                project: props.project.id,
                promptTemplate: selectedTemplate.value.id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    changelog.value = '';
                },
            }
        );
};

const createForm = useForm({
    name: '',
    description: '',
    initial_content: '',
    initial_changelog: 'Initial version',
});

const submitCreate = () => {
    createForm
        .transform((data) => ({
            name: data.name,
            description: data.description || null,
            initial_content: data.initial_content,
            initial_changelog: data.initial_changelog || 'Initial version',
        }))
        .post(prompts.store({ project: props.project.id }).url, {
            preserveScroll: true,
            onSuccess: () => {
                showCreateModal.value = false;
                createForm.reset();
            },
        });
};

watch(
    () => props.selectedTemplate,
    (template) => {
        if (template?.id) {
            selectedTemplateId.value = template.id;
            selectedVersion.value = props.selectedVersion ?? props.versions[0] ?? null;
            editorContent.value = selectedVersion.value?.content ?? '';
        }
    }
);

watch(
    () => props.selectedVersion,
    (version) => {
        selectedVersion.value = version ?? props.versions[0] ?? null;
        editorContent.value = version?.content ?? props.versions[0]?.content ?? '';
        changelog.value = '';
    }
);
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Prompts">
        <div class="grid gap-4 lg:grid-cols-[320px_1fr] lg:gap-6">
            <div class="flex h-full flex-col rounded-lg border border-border bg-card">
                <div class="flex items-center justify-between border-b border-border/80 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Prompt templates</h2>
                        <p class="text-xs text-muted-foreground">Project-wide prompt building blocks.</p>
                    </div>
                    <Button size="sm" variant="outline" @click="showCreateModal = true">New</Button>
                </div>

                <div class="p-3">
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Search prompts..."
                        class="w-full text-sm"
                    />
                </div>

                <div class="flex-1 space-y-2 overflow-y-auto px-3 pb-3">
                    <button
                        v-for="template in filteredTemplates"
                        :key="template.id"
                        type="button"
                        @click="selectTemplate(template.id)"
                        :class="[
                            'w-full rounded-lg border px-3 py-2 text-left transition',
                            selectedTemplateId === template.id
                                ? 'border-primary bg-primary/10'
                                : 'border-border/70 hover:border-primary/70',
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-foreground">{{ template.name }}</span>
                            <span class="text-[11px] font-medium text-muted-foreground">
                                v{{ template.latest_version ?? 0 }}
                            </span>
                        </div>
                        <p class="mt-1 line-clamp-1 text-xs text-muted-foreground">
                            {{ template.description || 'No description' }}
                        </p>
                    </button>

                    <div
                        v-if="filteredTemplates.length === 0"
                        class="rounded-md border border-dashed border-border/70 px-3 py-4 text-center text-sm text-muted-foreground"
                    >
                        No templates found.
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-card p-4 lg:p-6">
                <div v-if="!selectedTemplate" class="flex h-full flex-col items-center justify-center gap-3 text-center">
                    <p class="text-sm text-muted-foreground">No template selected.</p>
                    <Button size="sm" @click="showCreateModal = true">Create template</Button>
                </div>
                <div v-else class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-foreground">{{ selectedTemplate.name }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ selectedTemplate.description || 'Prompt template' }}
                            </p>
                            <p v-if="selectedVersion" class="mt-1 text-xs text-muted-foreground">
                                Showing v{{ selectedVersion.version }} •
                                {{ selectedVersion.changelog || 'Initial version' }} •
                                {{ selectedVersion.created_at }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" size="sm" @click="showVariables = true">Variables</Button>
                            <Button variant="outline" size="sm" @click="showVersions = true">Versions</Button>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border bg-background p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-foreground">Prompt content</span>
                            <span v-if="selectedVersion" class="text-xs text-muted-foreground">
                                v{{ selectedVersion.version }}
                            </span>
                        </div>
                        <textarea
                            v-model="editorContent"
                            rows="16"
                            class="mt-3 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="Write your prompt here..."
                        ></textarea>
                    </div>

                    <div
                        v-if="hasChanges"
                        class="flex flex-col gap-3 rounded-lg border border-primary/40 bg-primary/5 p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-foreground">Unsaved changes</p>
                                <p class="text-xs text-muted-foreground">
                                    Provide a short changelog and create a new version.
                                </p>
                            </div>
                            <Button variant="ghost" size="sm" @click="loadVersion(selectedVersion!)">Discard</Button>
                        </div>
                        <div class="grid gap-2">
                            <Label for="changelog">Changelog (optional)</Label>
                            <Input
                                id="changelog"
                                v-model="changelog"
                                name="changelog"
                                placeholder="What changed?"
                            />
                            <InputError :message="versionForm.errors.changelog" />
                        </div>
                        <div class="flex items-center gap-3">
                            <Button type="button" :disabled="versionForm.processing" @click="submitVersion">
                                Create new version
                            </Button>
                        </div>
                        <InputError :message="versionForm.errors.content" />
                    </div>
                </div>
            </div>
        </div>

        <Dialog :open="showVersions" @update:open="showVersions = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Versions</DialogTitle>
                    <DialogDescription>Select a version to view its content.</DialogDescription>
                </DialogHeader>
                <div class="max-h-96 space-y-2 overflow-y-auto">
                    <button
                        v-for="version in templateVersions"
                        :key="version.id"
                        type="button"
                        @click="
                            loadVersion(version);
                            showVersions = false;
                        "
                        :class="[
                            'w-full rounded-md border px-3 py-2 text-left text-sm transition',
                            selectedVersion?.id === version.id
                                ? 'border-primary bg-primary/10 text-foreground'
                                : 'border-border/60 hover:border-primary/70',
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-foreground">v{{ version.version }}</span>
                            <span class="text-[11px] text-muted-foreground">{{ version.created_at }}</span>
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ version.changelog || 'Initial version' }}
                        </div>
                    </button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog :open="showVariables" @update:open="showVariables = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Variables</DialogTitle>
                    <DialogDescription>
                        Extracted automatically from <code v-pre>{{ variable }}</code> placeholders.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="selectedTemplate?.variables?.length" class="mt-3 space-y-2">
                    <div
                        v-for="variable in selectedTemplate.variables"
                        :key="variable.name"
                        class="rounded-md border border-border/60 px-3 py-2 text-sm"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-foreground">{{ variable.name }}</span>
                            <span class="text-xs uppercase text-muted-foreground">{{ variable.type ?? 'string' }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ variable.description || 'No description' }}
                        </p>
                    </div>
                </div>
                <p v-else class="mt-3 text-sm text-muted-foreground">No variables detected.</p>
                <DialogFooter class="mt-4">
                    <Button variant="outline" size="sm" @click="showVariables = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New prompt template</DialogTitle>
                    <DialogDescription>Create a reusable prompt for this project.</DialogDescription>
                </DialogHeader>
                <div class="space-y-3">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="createForm.name" name="name" placeholder="quiz_expand_topic_system" />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="createForm.description"
                            name="description"
                            rows="2"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="Purpose of this template"
                        ></textarea>
                        <InputError :message="createForm.errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="initial_content">Initial content</Label>
                        <textarea
                            id="initial_content"
                            v-model="createForm.initial_content"
                            name="initial_content"
                            rows="6"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="Enter first prompt content"
                        ></textarea>
                        <InputError :message="createForm.errors.initial_content" />
                        <p class="text-xs text-muted-foreground">
                            Variables are extracted automatically from <code v-pre>{{ '{{ variable }}' }}</code> placeholders after saving.
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="initial_changelog">Initial changelog</Label>
                        <textarea
                            id="initial_changelog"
                            v-model="createForm.initial_changelog"
                            name="initial_changelog"
                            rows="2"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="Initial version"
                        ></textarea>
                        <InputError :message="createForm.errors.initial_changelog" />
                    </div>
                </div>
                <DialogFooter class="mt-4 flex items-center justify-end gap-2">
                    <Button variant="outline" size="sm" @click="showCreateModal = false">Cancel</Button>
                    <Button :disabled="createForm.processing" @click="submitCreate">Create template</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </ProjectLayout>
</template>
