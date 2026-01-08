<script setup lang="ts">
import { toRefs } from 'vue';
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import PromptEditor from '@/components/PromptEditor.vue';
import PromptHeader from '@/components/prompts/PromptHeader.vue';
import PromptRunPanel from '@/components/prompts/PromptRunPanel.vue';
import PromptTemplateList from '@/components/prompts/PromptTemplateList.vue';
import PromptVariablesDialog from '@/components/prompts/PromptVariablesDialog.vue';
import PromptVersionsDialog from '@/components/prompts/PromptVersionsDialog.vue';
import { usePromptWorkspace } from '@/composables/usePromptWorkspace';
import type {
    ProjectPayload,
    TemplateListItem,
    TemplatePayload,
    VersionPayload,
} from '@/types/prompts';
import { Button } from '@/components/ui/button';

interface Props {
    project: ProjectPayload;
    templates: TemplateListItem[];
    selectedTemplate: TemplatePayload | null;
    versions: VersionPayload[];
    selectedVersion: VersionPayload | null;
    datasets: { value: number; label: string }[];
    providerCredentials: { value: number; label: string; provider: string }[];
    providerCredentialModels: Record<number, { id: string; name: string; display_name: string }[]>;
}

const props = defineProps<Props>();
const {
    project,
    templates,
    selectedTemplate: selectedTemplateProp,
    versions,
    selectedVersion: selectedVersionProp,
    datasets,
    providerCredentials,
    providerCredentialModels,
} = toRefs(props);

const {
    selectedTemplateId,
    selectedVersion,
    editorContent,
    editorMode,
    editorPreset,
    changelog,
    showVersions,
    showVariables,
    showRunModal,
    search,
    showSaveMenu,
    templateNameEditing,
    templateForm,
    versionForm,
    templateVersions,
    filteredTemplates,
    selectedTemplate,
    isDraftSelected,
    hasChanges,
    saveLabel,
    saveActionLabel,
    canOpenRunModal,
    selectTemplate,
    createDraft,
    loadVersion,
    openRunModal,
    submitVersion,
    startTemplateNameEdit,
    cancelTemplateNameEdit,
    commitTemplateName,
} = usePromptWorkspace({
    project,
    templates,
    selectedTemplate: selectedTemplateProp,
    versions,
    selectedVersion: selectedVersionProp,
});
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Prompts">
        <div class="grid min-h-[calc(100vh-8rem)] gap-0 overflow-hidden lg:h-[calc(100vh-8rem)] lg:grid-cols-[320px_1fr]">
            <PromptTemplateList
                :items="filteredTemplates"
                :selected-template-id="selectedTemplateId"
                v-model:search="search"
                @select="selectTemplate"
                @create-draft="createDraft"
            />

            <div class="flex h-full flex-col bg-white min-h-0">
                <div v-if="!selectedTemplate" class="flex h-full flex-col items-center justify-center gap-3 text-center">
                    <p class="text-sm text-muted-foreground">No template selected.</p>
                    <Button size="sm" @click="createDraft">Create template</Button>
                </div>
                <div v-else class="flex h-full flex-col">
                    <PromptHeader
                        :template-name="templateForm.name"
                        :template-name-editing="templateNameEditing"
                        :selected-version="selectedVersion"
                        :rating="selectedVersion?.rating ?? null"
                        :has-changes="hasChanges"
                        :is-draft-selected="isDraftSelected"
                        :save-label="saveLabel"
                        :save-action-label="saveActionLabel"
                        :changelog="changelog"
                        :version-errors="versionForm.errors"
                        :version-processing="versionForm.processing"
                        :can-open-run-modal="canOpenRunModal"
                        :show-save-menu="showSaveMenu"
                        @update:template-name="templateForm.name = $event"
                        @update:changelog="changelog = $event"
                        @update:show-save-menu="showSaveMenu = $event"
                        @start-name-edit="startTemplateNameEdit"
                        @commit-name="commitTemplateName"
                        @cancel-name="cancelTemplateNameEdit"
                        @submit-version="submitVersion"
                        @open-run="openRunModal"
                        @open-variables="showVariables = true"
                        @open-versions="showVersions = true"
                    />

                    <div class="flex-1 overflow-auto min-h-0">
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

        <PromptVersionsDialog
            :open="showVersions"
            :versions="templateVersions"
            :selected-version-id="selectedVersion?.id ?? null"
            @update:open="showVersions = $event"
            @select="(version) => { loadVersion(version); showVersions = false; }"
        />

        <PromptRunPanel
            v-if="selectedTemplate && !isDraftSelected"
            v-model:open="showRunModal"
            :project-uuid="props.project.uuid"
            :prompt-template-id="selectedTemplate.id"
            :variables="selectedTemplate.variables ?? []"
            :datasets="datasets"
            :provider-credentials="providerCredentials"
            :provider-credential-models="providerCredentialModels"
        />

        <PromptVariablesDialog
            :open="showVariables"
            :variables="selectedTemplate?.variables ?? []"
            @update:open="showVariables = $event"
        />

    </ProjectLayout>
</template>
