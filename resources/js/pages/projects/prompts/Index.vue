<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import prompts from '@/routes/projects/prompts';
import promptVersions from '@/routes/projects/prompts/versions';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import PromptEditor from '@/components/PromptEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface TemplateListItem {
    id: number;
    name: string;
    description?: string | null;
    latest_version?: number | null;
}

interface DraftTemplate {
    id: string;
    name: string;
    description?: string | null;
    latest_version?: number | null;
    variables: TemplateVariable[];
    content: string;
    isDraft: true;
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
    providerCredentials: { value: number; label: string; provider: string }[];
    providerCredentialModels: Record<number, { id: string; name: string; display_name: string }[]>;
}

const props = defineProps<Props>();

const selectedTemplateId = ref<number | string | null>(
    props.selectedTemplate?.id ?? props.templates[0]?.id ?? null,
);
const selectedVersion = ref<VersionPayload | null>(props.selectedVersion ?? props.versions[0] ?? null);
const editorContent = ref(selectedVersion.value?.content ?? '');
const editorMode = ref<'plain' | 'markdown' | 'xml'>('plain');
const editorPreset = ref<'minimal' | 'ide'>('minimal');
const draftCounter = ref(props.templates.length + 1);
const drafts = ref<DraftTemplate[]>([]);
const changelog = ref('');
const showVersions = ref(false);
const showVariables = ref(false);
const showRunModal = ref(false);
const search = ref('');
const showSaveMenu = ref(false);

const allTemplates = computed<(TemplateListItem | DraftTemplate)[]>(() => [
    ...drafts.value,
    ...props.templates,
]);

const filteredTemplates = computed(() => {
    if (!search.value.trim()) return allTemplates.value;
    const term = search.value.toLowerCase();
    return allTemplates.value.filter(
        (tpl) =>
            tpl.name.toLowerCase().includes(term) ||
            (tpl.description ?? '').toLowerCase().includes(term)
    );
});

const activeDraft = computed(
    () => drafts.value.find((draft) => draft.id === selectedTemplateId.value) ?? null,
);

const selectedTemplate = computed(() => {
    if (activeDraft.value) {
        return activeDraft.value;
    }

    return props.selectedTemplate && props.selectedTemplate.id === selectedTemplateId.value
        ? props.selectedTemplate
        : null;
});

const isDraftSelected = computed(() => Boolean(activeDraft.value));

const templateVersions = computed(() =>
    selectedTemplate.value && !isDraftSelected.value ? props.versions : [],
);

const hasChanges = computed(() => {
    if (isDraftSelected.value) {
        return editorContent.value.trim().length > 0;
    }

    return !!selectedVersion.value && editorContent.value !== (selectedVersion.value.content ?? '');
});

const saveLabel = computed(() => (isDraftSelected.value ? 'Create prompt' : 'Save version'));
const saveActionLabel = computed(() => (isDraftSelected.value ? 'Create prompt' : 'Create version'));

const selectTemplate = (templateId: number | string) => {
    if (templateId === selectedTemplateId.value) return;

    if (typeof templateId === 'string') {
        const draft = drafts.value.find((item) => item.id === templateId) ?? null;
        selectedTemplateId.value = templateId;
        selectedVersion.value = null;
        editorContent.value = draft?.content ?? '';
        return;
    }

    router.get(
        prompts.index(
            { project: props.project.uuid },
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

const createDraft = () => {
    const draftName = `Prompt #${draftCounter.value}`;
    draftCounter.value += 1;

    const draft: DraftTemplate = {
        id: `draft-${Date.now()}-${draftCounter.value}`,
        name: draftName,
        description: null,
        latest_version: null,
        variables: [],
        content: '',
        isDraft: true,
    };

    drafts.value = [draft, ...drafts.value];
    selectedTemplateId.value = draft.id;
    selectedVersion.value = null;
    editorContent.value = '';
    runForm.variables = defaultVariables.value;
    showSaveMenu.value = false;
    showVariables.value = false;
    showVersions.value = false;
};

const loadVersion = (version: VersionPayload) => {
    selectedVersion.value = version;
    editorContent.value = version.content ?? '';
    changelog.value = '';

    if (typeof selectedTemplateId.value === 'number') {
        history.replaceState(
            {},
            '',
            prompts
                .index(
                    { project: props.project.uuid },
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

const defaultVariables = computed(() => {
    if (!selectedTemplate.value?.variables || !selectedTemplate.value.variables.length) return '{}';
    const vars: Record<string, unknown> = {};
    selectedTemplate.value.variables.forEach((v) => {
        vars[v.name] = '';
    });

    return JSON.stringify(vars, null, 2);
});

const runForm = useForm({
    provider_credential_id: props.providerCredentials[0]?.value ?? null,
    model_name: '',
    variables: defaultVariables.value,
});

const modelOptions = computed(() => {
    const credentialId = runForm.provider_credential_id;
    if (!credentialId) return [];

    return props.providerCredentialModels[credentialId] ?? [];
});

const handleProviderChange = () => {
    const firstModel = modelOptions.value[0];
    runForm.model_name = firstModel ? firstModel.id : '';
};

const openRunModal = () => {
    if (!selectedTemplate.value || isDraftSelected.value) return;
    handleProviderChange();
    showRunModal.value = true;
};

const submitRun = () => {
    if (!selectedTemplate.value || isDraftSelected.value) return;

    runForm.post(
        `/projects/${props.project.uuid}/prompts/${selectedTemplate.value.id}/run`,
        {
            preserveScroll: true,
            onSuccess: () => {
                showRunModal.value = false;
            },
        }
    );
};

const canRunPrompt = computed(
    () =>
        Boolean(selectedTemplate.value) &&
        !isDraftSelected.value &&
        Boolean(runForm.provider_credential_id) &&
        Boolean(runForm.model_name) &&
        props.providerCredentials.length > 0
);

const submitVersion = () => {
    if (!selectedTemplate.value) return;

    if (isDraftSelected.value) {
        versionForm
            .transform(() => ({
                name: selectedTemplate.value?.name ?? `Prompt #${draftCounter.value}`,
                description: null,
                initial_content: editorContent.value,
                initial_changelog: changelog.value || 'Initial version',
            }))
            .post(prompts.store({ project: props.project.uuid }).url, {
                preserveScroll: true,
                onSuccess: () => {
                    changelog.value = '';
                    showSaveMenu.value = false;
                },
            });
        return;
    }

    versionForm
        .transform(() => ({
            content: editorContent.value,
            changelog: changelog.value || null,
        }))
        .post(
            promptVersions.store({
                project: props.project.uuid,
                promptTemplate: selectedTemplate.value.id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    changelog.value = '';
                    showSaveMenu.value = false;
                },
            }
        );
};

watch(
    () => props.selectedTemplate,
    (template) => {
        if (isDraftSelected.value) {
            return;
        }

        if (template?.id) {
            selectedTemplateId.value = template.id;
            selectedVersion.value = props.selectedVersion ?? props.versions[0] ?? null;
            editorContent.value = selectedVersion.value?.content ?? '';
            runForm.variables = defaultVariables.value;
        }
    }
);

watch(
    () => props.selectedVersion,
    (version) => {
        if (isDraftSelected.value) {
            return;
        }

        selectedVersion.value = version ?? props.versions[0] ?? null;
        editorContent.value = version?.content ?? props.versions[0]?.content ?? '';
        changelog.value = '';
    }
);

watch(
    () => editorContent.value,
    (value) => {
        if (!activeDraft.value) return;
        activeDraft.value.content = value;
    }
);

watch(
    () => runForm.provider_credential_id,
    () => handleProviderChange(),
    { immediate: true }
);
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Prompts">
        <div class="grid min-h-[calc(100vh-8rem)] gap-0 overflow-hidden lg:h-[calc(100vh-8rem)] lg:grid-cols-[320px_1fr]">
            <div class="flex h-full flex-col border-r border-border/70 bg-white">
                <div class="border-b border-border/60 px-4 py-4">
                    <div class="flex items-center gap-2">
                        <Input
                            v-model="search"
                            type="search"
                            placeholder="Search prompts..."
                            class="w-full text-sm"
                        />
                        <Button size="sm" variant="outline" @click="createDraft">New</Button>
                    </div>
                </div>

                <div class="flex-1 space-y-1 overflow-y-auto">
                    <button
                        v-for="template in filteredTemplates"
                        :key="template.id"
                        type="button"
                        @click="selectTemplate(template.id)"
                        :class="[
                            'w-full border-l-2 px-4 py-3 text-left transition',
                            selectedTemplateId === template.id
                                ? 'border-primary bg-white'
                                : 'border-l-transparent hover:bg-white/70',
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
                        class="px-4 py-4 text-center text-sm text-muted-foreground"
                    >
                        No templates found.
                    </div>
                </div>
            </div>

            <div class="flex h-full flex-col bg-white">
                <div v-if="!selectedTemplate" class="flex h-full flex-col items-center justify-center gap-3 text-center">
                    <p class="text-sm text-muted-foreground">No template selected.</p>
                    <Button size="sm" @click="createDraft">Create template</Button>
                </div>
                <div v-else class="flex h-full flex-col">
                    <div class="sticky top-0 z-10 border-b border-border/60 bg-white px-6 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-lg font-semibold text-foreground">{{ selectedTemplate.name }}</h2>
                                <span v-if="selectedVersion" class="rounded-full border border-border/60 bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                    v{{ selectedVersion.version }}
                                </span>
                                <span
                                    v-if="hasChanges"
                                    class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700"
                                >
                                    Modified
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    Last saved: {{ selectedVersion?.created_at || 'Not saved yet' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <DropdownMenu v-if="hasChanges" v-model:open="showSaveMenu">
                                    <DropdownMenuTrigger as-child>
                                        <Button size="sm">{{ saveLabel }}</Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-72 p-3">
                                        <div class="grid gap-2">
                                            <Label for="changelog">Changelog (optional)</Label>
                                            <Input
                                                id="changelog"
                                                v-model="changelog"
                                                name="changelog"
                                                placeholder="What changed?"
                                            />
                                            <InputError
                                                :message="versionForm.errors.changelog || versionForm.errors.initial_changelog"
                                            />
                                        </div>
                                        <InputError
                                            :message="versionForm.errors.content || versionForm.errors.initial_content"
                                            class="mt-2"
                                        />
                                        <div class="mt-3 flex items-center justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="showSaveMenu = false"
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                size="sm"
                                                :disabled="versionForm.processing"
                                                @click="submitVersion"
                                            >
                                                {{ saveActionLabel }}
                                            </Button>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <Button
                                    size="sm"
                                    :disabled="!canRunPrompt"
                                    :variant="hasChanges ? 'outline' : 'default'"
                                    @click="openRunModal"
                                >
                                    <Spinner v-if="runForm.processing" class="mr-2" /> Run prompt
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="isDraftSelected"
                                    @click="showVariables = true"
                                >
                                    Variables
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="isDraftSelected"
                                    @click="showVersions = true"
                                >
                                    Versions
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-hidden">
                        <PromptEditor
                            v-model="editorContent"
                            v-model:mode="editorMode"
                            v-model:preset="editorPreset"
                            placeholder="Write your prompt here..."
                            show-controls
                            height="100%"
                        />
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

        <Dialog :open="showRunModal" @update:open="showRunModal = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Run prompt (latest version)</DialogTitle>
                    <DialogDescription>Execute the latest prompt version with your variables.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="run_provider_credential_id">Provider credential</Label>
                        <select
                            id="run_provider_credential_id"
                            v-model.number="runForm.provider_credential_id"
                            name="provider_credential_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            <option v-if="!providerCredentials.length" disabled :value="null">No credentials available</option>
                            <option
                                v-for="credential in providerCredentials"
                                :key="credential.value"
                                :value="credential.value"
                            >
                                {{ credential.label }}
                            </option>
                        </select>
                        <InputError :message="runForm.errors.provider_credential_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="run_model_name">Model</Label>
                        <select
                            id="run_model_name"
                            v-model="runForm.model_name"
                            name="model_name"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            <option value="" disabled>Select model</option>
                            <option v-for="model in modelOptions" :key="model.id" :value="model.id">
                                {{ model.display_name }} ({{ model.name }})
                            </option>
                        </select>
                        <p v-if="runForm.provider_credential_id && !modelOptions.length" class="text-xs text-muted-foreground">
                            No models available for this credential yet.
                        </p>
                        <InputError :message="runForm.errors.model_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="run_variables">Variables (JSON)</Label>
                        <textarea
                            id="run_variables"
                            v-model="runForm.variables"
                            rows="6"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder='{"topic": "React hooks"}'
                        ></textarea>
                        <InputError :message="runForm.errors.variables" />
                    </div>
                </div>
                <DialogFooter class="mt-4">
                    <Button variant="outline" size="sm" @click="showRunModal = false">Cancel</Button>
                    <Button size="sm" :disabled="runForm.processing || !canRunPrompt" @click="submitRun">
                        <Spinner v-if="runForm.processing" class="mr-2" /> Run prompt
                    </Button>
                </DialogFooter>
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

    </ProjectLayout>
</template>
