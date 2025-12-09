<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import prompts from '@/routes/projects/prompts';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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
    template: TemplatePayload;
    versions: VersionPayload[];
    selectedVersion: VersionPayload | null;
}

const props = defineProps<Props>();
const selectedVersion = ref<VersionPayload | null>(props.selectedVersion ?? props.versions[0] ?? null);
const editorContent = ref(selectedVersion.value?.content ?? '');
const changelog = ref('');
const showVersions = ref(false);
const showVariables = ref(false);

const hasChanges = computed(
    () => !!selectedVersion.value && editorContent.value !== (selectedVersion.value.content ?? '')
);

const form = useForm({
    content: '',
    changelog: '',
});

const loadVersion = (version: VersionPayload) => {
    selectedVersion.value = version;
    editorContent.value = version.content ?? '';
    changelog.value = '';
    history.replaceState(
        {},
        '',
        prompts
            .show({
                project: props.project.id,
                promptTemplate: props.template.id,
                query: { version: version.version },
            })
            .url
    );
};

const submit = () => {
    form.transform(() => ({
        content: editorContent.value,
        changelog: changelog.value || null,
    })).post(
        prompts.versions.store({
            project: props.project.id,
            promptTemplate: props.template.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                changelog.value = '';
            },
        }
    );
};

watch(
    () => props.selectedVersion,
    (newVersion) => {
        if (!newVersion) {
            return;
        }
        selectedVersion.value = newVersion;
        editorContent.value = newVersion.content ?? '';
        changelog.value = '';
    }
);
</script>

<template>
    <ProjectLayout :project="project" :title-suffix="`Prompts • ${template.name}`">
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-foreground">{{ template.name }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ template.description || 'Prompt template' }}
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

            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-foreground">Prompt content</span>
                    <span v-if="selectedVersion" class="text-xs text-muted-foreground">
                        v{{ selectedVersion.version }}
                    </span>
                </div>
                <textarea
                    v-model="editorContent"
                    rows="18"
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
                    <InputError :message="form.errors.changelog" />
                </div>
                <div class="flex items-center gap-3">
                    <Button type="button" :disabled="form.processing" @click="submit">
                        Create new version
                    </Button>
                </div>
                <InputError :message="form.errors.content" />
            </div>
        </div>

        <Dialog :open="showVersions" @update:open="showVersions = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Versions</DialogTitle>
                </DialogHeader>
                <div class="max-h-96 space-y-2 overflow-y-auto">
                    <button
                        v-for="version in versions"
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
                </DialogHeader>
                <p class="text-xs text-muted-foreground">
                    Extracted automatically from <code v-pre>{{ variable }}</code> placeholders.
                </p>
                <div v-if="template.variables && template.variables.length" class="mt-3 space-y-2">
                    <div
                        v-for="variable in template.variables"
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
