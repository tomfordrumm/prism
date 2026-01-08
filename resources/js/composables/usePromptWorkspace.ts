import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch, type Ref } from 'vue';
import prompts from '@/routes/projects/prompts';
import promptVersions from '@/routes/projects/prompts/versions';
import type {
    DraftTemplate,
    ProjectPayload,
    TemplateListItem,
    TemplatePayload,
    VersionPayload,
} from '@/types/prompts';

interface UsePromptWorkspaceParams {
    project: Ref<ProjectPayload>;
    templates: Ref<TemplateListItem[]>;
    selectedTemplate: Ref<TemplatePayload | null>;
    versions: Ref<VersionPayload[]>;
    selectedVersion: Ref<VersionPayload | null>;
}

export const usePromptWorkspace = ({
    project,
    templates,
    selectedTemplate: selectedTemplateProp,
    versions,
    selectedVersion: selectedVersionProp,
}: UsePromptWorkspaceParams) => {
    const selectedTemplateId = ref<number | string | null>(
        selectedTemplateProp.value?.id ?? templates.value[0]?.id ?? null,
    );
    const selectedVersion = ref<VersionPayload | null>(
        selectedVersionProp.value ?? versions.value[0] ?? null,
    );
    const editorContent = ref(selectedVersion.value?.content ?? '');
    const editorMode = ref<'plain' | 'markdown' | 'xml'>('plain');
    const editorPreset = ref<'minimal' | 'ide'>('minimal');
    const draftCounter = ref(templates.value.length + 1);
    const drafts = ref<DraftTemplate[]>([]);
    const changelog = ref('');
    const showVersions = ref(false);
    const showVariables = ref(false);
    const showRunModal = ref(false);
    const search = ref('');
    const showSaveMenu = ref(false);

    const allTemplates = computed<(TemplateListItem | DraftTemplate)[]>(() => [
        ...drafts.value,
        ...templates.value,
    ]);

    const filteredTemplates = computed(() => {
        if (!search.value.trim()) return allTemplates.value;
        const term = search.value.toLowerCase();
        return allTemplates.value.filter(
            (tpl) =>
                tpl.name.toLowerCase().includes(term) ||
                (tpl.description ?? '').toLowerCase().includes(term),
        );
    });

    const activeDraft = computed(
        () => drafts.value.find((draft) => draft.id === selectedTemplateId.value) ?? null,
    );

    const selectedTemplate = computed<TemplatePayload | DraftTemplate | null>(() => {
        if (activeDraft.value) {
            return activeDraft.value;
        }

        return selectedTemplateProp.value && selectedTemplateProp.value.id === selectedTemplateId.value
            ? selectedTemplateProp.value
            : null;
    });

    const isDraftSelected = computed(() => Boolean(activeDraft.value));
    const templateNameEditing = ref(false);
    const templateNameBeforeEdit = ref('');

    const templateForm = useForm({
        name: '',
        description: '',
    });

    const templateVersions = computed(() =>
        selectedTemplate.value && !isDraftSelected.value ? versions.value : [],
    );

    const hasChanges = computed(() => {
        if (isDraftSelected.value) {
            return editorContent.value.trim().length > 0;
        }

        return (
            !!selectedVersion.value &&
            editorContent.value !== (selectedVersion.value.content ?? '')
        );
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
                { project: project.value.uuid },
                { preserveScroll: true, replace: true, query: { prompt_id: templateId } },
            ).url,
            {},
            {
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    selectedTemplateId.value = templateId;
                },
            },
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
                        { project: project.value.uuid },
                        { query: { prompt_id: selectedTemplateId.value, version: version.version } },
                    )
                    .url,
            );
        }
    };

    const versionForm = useForm({
        content: '',
        changelog: '',
    });

    const openRunModal = () => {
        if (!selectedTemplate.value || isDraftSelected.value) return;
        showRunModal.value = true;
    };

    const canOpenRunModal = computed(
        () => Boolean(selectedTemplate.value) && !isDraftSelected.value,
    );

    const submitVersion = () => {
        if (!selectedTemplate.value) return;

        if (isDraftSelected.value) {
            const draftId = selectedTemplateId.value;
            versionForm
                .transform(() => ({
                    name: selectedTemplate.value?.name ?? `Prompt #${draftCounter.value}`,
                    description: null,
                    initial_content: editorContent.value,
                    initial_changelog: 'Initial',
                }))
                .post(prompts.store({ project: project.value.uuid }).url, {
                    preserveScroll: true,
                    preserveState: false,
                    onSuccess: () => {
                        if (typeof draftId === 'string') {
                            drafts.value = drafts.value.filter((draft) => draft.id !== draftId);
                        }
                        changelog.value = '';
                        showSaveMenu.value = false;
                        selectedTemplateId.value = null;
                    },
                });
            return;
        }

        const templateId = selectedTemplate.value.id;
        if (typeof templateId !== 'number') return;

        versionForm
            .transform(() => ({
                content: editorContent.value,
                changelog: changelog.value || null,
            }))
            .post(
                promptVersions.store({
                    project: project.value.uuid,
                    promptTemplate: templateId,
                }).url,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        changelog.value = '';
                        showSaveMenu.value = false;
                    },
                },
            );
    };

    const startTemplateNameEdit = () => {
        if (!selectedTemplate.value) return;
        templateNameBeforeEdit.value = templateForm.name;
        templateNameEditing.value = true;
    };

    const cancelTemplateNameEdit = () => {
        templateForm.name = templateNameBeforeEdit.value;
        templateNameEditing.value = false;
    };

    const commitTemplateName = () => {
        if (!selectedTemplate.value) return;
        const trimmed = templateForm.name.trim();
        if (!trimmed) {
            templateForm.name = templateNameBeforeEdit.value;
            templateNameEditing.value = false;
            return;
        }

        if (isDraftSelected.value) {
            if (activeDraft.value) {
                activeDraft.value.name = trimmed;
            }
            templateNameEditing.value = false;
            return;
        }

        if (trimmed === templateNameBeforeEdit.value) {
            templateNameEditing.value = false;
            return;
        }

        templateForm.name = trimmed;
        const templateId = selectedTemplate.value.id;
        if (typeof templateId !== 'number') return;
        templateForm.put(`/projects/${project.value.uuid}/prompts/${templateId}`, {
            preserveScroll: true,
            onSuccess: () => {
                templateNameEditing.value = false;
            },
        });
    };

    watch(
        () => selectedTemplate.value,
        (template) => {
            if (!template) return;
            templateForm.name = template.name ?? '';
            templateForm.description = template.description ?? '';
            templateNameEditing.value = false;
        },
        { immediate: true },
    );

    watch(
        () => selectedTemplateProp.value,
        (template) => {
            if (isDraftSelected.value) {
                return;
            }

            if (template?.id) {
                selectedTemplateId.value = template.id;
                selectedVersion.value = selectedVersionProp.value ?? versions.value[0] ?? null;
                editorContent.value = selectedVersion.value?.content ?? '';
            }
        },
    );

    watch(
        () => selectedVersionProp.value,
        (version) => {
            if (isDraftSelected.value) {
                return;
            }

            selectedVersion.value = version ?? versions.value[0] ?? null;
            editorContent.value = version?.content ?? versions.value[0]?.content ?? '';
            changelog.value = '';
        },
    );

    watch(
        () => editorContent.value,
        (value) => {
            if (!activeDraft.value) return;
            activeDraft.value.content = value;
        },
    );

    watch(
        () => templates.value,
        (updatedTemplates) => {
            if (!updatedTemplates.length) return;
            if (typeof selectedTemplateId.value === 'string' && !activeDraft.value) {
                selectedTemplateId.value = updatedTemplates[0]?.id ?? null;
                return;
            }
            if (typeof selectedTemplateId.value === 'number') {
                const exists = updatedTemplates.some(
                    (template) => template.id === selectedTemplateId.value,
                );
                if (!exists) {
                    selectedTemplateId.value = updatedTemplates[0]?.id ?? null;
                }
            }
        },
        { immediate: true },
    );

    return {
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
    };
};
