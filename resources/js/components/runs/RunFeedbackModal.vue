<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { diffLines } from 'diff';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Icon from '@/components/Icon.vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import type {
    RunModelOption,
    RunProviderCredentialOption,
    RunStepPayload,
    RunFeedbackItem,
} from '@/types/runs';

interface Props {
    open: boolean;
    runId: number;
    projectUuid: string;
    step: RunStepPayload | null;
    targetPrompt: {
        role: 'system' | 'user';
        prompt_version_id: number | null;
        prompt_template_id: number | null;
        content: string | null;
    } | null;
    providerCredentials: RunProviderCredentialOption[];
    providerCredentialModels: Record<number, RunModelOption[]>;
    defaultProviderCredentialId?: number | null;
    defaultModelName?: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'feedback-added', payload: { stepId: number; feedback: RunFeedbackItem }): void;
}>();

const feedbackForm = useForm({
    rating: null as number | null,
    comment: '',
    request_suggestion: true,
    provider_credential_id: null as number | null,
    model_name: '',
    target_prompt_version_id: null as number | null,
});

const feedbackInput = ref('');
const feedbackThread = ref<{ id: number; role: 'user' | 'assistant'; text: string; createdAt: string }[]>([]);
const feedbackItems = ref<RunFeedbackItem[]>([]);
const isGenerating = ref(false);
const diffViewMode = ref<'diff' | 'final'>('diff');
const getCookie = (name: string) =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
        ?.split('=')[1] ?? '';

const modelOptions = computed(() => {
    const credentialId = feedbackForm.provider_credential_id;

    if (!credentialId) return [];

    return props.providerCredentialModels[credentialId] ?? [];
});

const targetPrompt = computed(() => props.targetPrompt);

const handleProviderChange = () => {
    if (!feedbackForm.provider_credential_id) {
        feedbackForm.model_name = '';
        return;
    }

    const firstModel = modelOptions.value[0];
    feedbackForm.model_name = firstModel ? firstModel.id : '';
};

watch(
    () => feedbackForm.request_suggestion,
    (enabled) => {
        if (!enabled) {
            feedbackForm.provider_credential_id = null;
            feedbackForm.model_name = '';
            return;
        }

        if (!feedbackForm.provider_credential_id) {
            selectDefaultImprover();
        }
    },
);

const activeStepMeta = computed(() => {
    const step = props.step;
    if (!step) {
        return {
            name: 'Prompt',
            model: '—',
            provider: '—',
        };
    }

    return {
        name: step.chain_node?.name || 'Prompt',
        model: step.chain_node?.model_name || '—',
        provider: step.chain_node?.provider_name || step.chain_node?.provider || '—',
    };
});

const currentPromptContent = computed(() => props.targetPrompt?.content || '');

const latestSuggestion = computed(() => {
    if (!feedbackItems.value.length) return null;
    const targetVersionId = props.targetPrompt?.prompt_version_id ?? null;
    const feedbackWithSuggestion = [...feedbackItems.value]
        .reverse()
        .find((fb) =>
            fb.suggested_prompt_content &&
            (targetVersionId ? fb.target_prompt_version_id === targetVersionId : true),
        );
    return feedbackWithSuggestion ?? null;
});

const suggestedPromptContent = computed(
    () => latestSuggestion.value?.suggested_prompt_content || '',
);

type DiffLine = {
    type: 'add' | 'remove' | 'context';
    oldLine: number | null;
    newLine: number | null;
    text: string;
};

const buildDiffLines = (original: string, updated: string): DiffLine[] => {
    const changes = diffLines(original || '', updated || '');
    let oldLine = 1;
    let newLine = 1;

    return changes.flatMap((part) => {
        const split = part.value.split('\n');

        return split
            .filter((_, idx) => !(idx === split.length - 1 && split[idx] === ''))
            .map((line) => {
                if (part.added) {
                    return {
                        type: 'add',
                        oldLine: null,
                        newLine: newLine++,
                        text: line,
                    };
                }

                if (part.removed) {
                    return {
                        type: 'remove',
                        oldLine: oldLine++,
                        newLine: null,
                        text: line,
                    };
                }

                return {
                    type: 'context',
                    oldLine: oldLine++,
                    newLine: newLine++,
                    text: line,
                };
            });
    });
};

const diffLineSymbol = (type: DiffLine['type']) => {
    if (type === 'add') return '+';
    if (type === 'remove') return '-';

    return ' ';
};

const diffPreviewLines = computed(() => {
    if (!currentPromptContent.value || !suggestedPromptContent.value) {
        return [];
    }

    return buildDiffLines(currentPromptContent.value, suggestedPromptContent.value);
});

const submitFeedback = async () => {
    if (!props.step) return;
    if (!feedbackForm.comment.trim()) {
        feedbackForm.setError('comment', 'Provide feedback before generating.');
        return;
    }

    feedbackForm.target_prompt_version_id = props.targetPrompt?.prompt_version_id ?? null;

    isGenerating.value = true;
    try {
        const csrfToken =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ||
            decodeURIComponent(getCookie('XSRF-TOKEN'));
        const response = await fetch(`/runs/${props.runId}/steps/${props.step.id}/feedback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                rating: feedbackForm.rating,
                comment: feedbackForm.comment,
                request_suggestion: feedbackForm.request_suggestion,
                provider_credential_id: feedbackForm.provider_credential_id,
                model_name: feedbackForm.model_name,
                target_prompt_version_id: feedbackForm.target_prompt_version_id,
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            const errors = payload?.errors;
            if (errors) {
                Object.entries(errors).forEach(([key, value]) => {
                    const message = Array.isArray(value) ? value[0] : value;
                    feedbackForm.setError(key, message as string);
                });
            } else if (payload?.message) {
                feedbackForm.setError('comment', payload.message);
            } else {
                feedbackForm.setError('comment', 'Failed to submit feedback.');
            }
            return;
        }

        const payload = await response.json().catch(() => null);
        const feedback = payload?.feedback as RunFeedbackItem | undefined;
        if (feedback) {
            feedbackItems.value = [...feedbackItems.value, feedback];
            emit('feedback-added', { stepId: props.step.id, feedback });
            const assistantText =
                feedback.analysis ||
                (feedback.suggested_prompt_content ? 'Suggestion ready.' : '');
            if (assistantText) {
                feedbackThread.value = [
                    ...feedbackThread.value,
                    {
                        id: Date.now(),
                        role: 'assistant',
                        text: assistantText,
                        createdAt: new Date().toLocaleTimeString(),
                    },
                ];
            }
        }
        feedbackInput.value = '';
        feedbackForm.clearErrors();
    } finally {
        isGenerating.value = false;
    }
};

const handleGenerateSuggestion = () => {
    if (!props.targetPrompt?.prompt_version_id) {
        feedbackForm.setError(
            'comment',
            'Selected prompt is not available for improvements.',
        );
        return;
    }

    if (!feedbackInput.value.trim()) {
        feedbackForm.setError('comment', 'Provide feedback before generating.');
        return;
    }

    feedbackForm.clearErrors('comment');
    feedbackForm.comment = feedbackInput.value;
    feedbackThread.value = [
        ...feedbackThread.value,
        {
            id: Date.now(),
            role: 'user',
            text: feedbackInput.value,
            createdAt: new Date().toLocaleTimeString(),
        },
    ];
    submitFeedback();
};

const applySuggestion = () => {
    const step = props.step;
    const feedback = latestSuggestion.value;
    const targetTemplateId = props.targetPrompt?.prompt_template_id ?? null;
    if (!step || !feedback || !targetTemplateId) return;

    router.post(
        `/projects/${props.projectUuid}/prompts/${targetTemplateId}/versions/from-feedback`,
        { feedback_id: feedback.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
            },
        },
    );
};

const selectDefaultImprover = () => {
    const defaultCredentialId = props.defaultProviderCredentialId;
    const defaultModelName = props.defaultModelName;

    if (defaultCredentialId && defaultModelName) {
        const availableModels = props.providerCredentialModels[defaultCredentialId] ?? [];
        const hasModel = availableModels.some((model) => model.id === defaultModelName);
        feedbackForm.provider_credential_id = defaultCredentialId;
        feedbackForm.model_name = hasModel ? defaultModelName : availableModels[0]?.id ?? '';
        return;
    }

    if (defaultCredentialId) {
        const defaultModels = props.providerCredentialModels[defaultCredentialId] ?? [];
        feedbackForm.provider_credential_id = defaultCredentialId;
        feedbackForm.model_name = defaultModels[0]?.id ?? '';
        return;
    }

    if (!props.providerCredentials.length) {
        feedbackForm.provider_credential_id = null;
        feedbackForm.model_name = '';
        return;
    }

    const preferredTokens = ['gpt-4o', 'claude-3.5', 'claude-3-5', 'sonnet', 'opus'];

    for (const credential of props.providerCredentials) {
        const models = props.providerCredentialModels[credential.value] ?? [];
        const preferred = models.find((model) =>
            preferredTokens.some((token) => model.id.toLowerCase().includes(token)),
        );
        if (preferred) {
            feedbackForm.provider_credential_id = credential.value;
            feedbackForm.model_name = preferred.id;
            return;
        }
    }

    const fallbackCredential = props.providerCredentials[0];
    const fallbackModels = props.providerCredentialModels[fallbackCredential.value] ?? [];
    feedbackForm.provider_credential_id = fallbackCredential.value;
    feedbackForm.model_name = fallbackModels[0]?.id ?? '';
};

const resetFeedbackState = () => {
    feedbackForm.reset();
    feedbackForm.request_suggestion = true;
    feedbackInput.value = '';
    feedbackThread.value = [];
    feedbackItems.value = props.step?.feedback ? [...props.step.feedback] : [];
    diffViewMode.value = 'diff';
    feedbackForm.target_prompt_version_id = props.targetPrompt?.prompt_version_id ?? null;
    selectDefaultImprover();
};

watch(
    () => [props.open, props.step?.id, props.targetPrompt?.prompt_version_id],
    ([open]) => {
        if (!open) return;
        resetFeedbackState();
    },
);

watch(
    () => props.step?.feedback,
    (feedback) => {
        if (!feedback) return;
        feedbackItems.value = [...feedback];
    },
);
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        @click.self="emit('update:open', false)"
    >
        <div class="relative flex h-full w-full max-h-[95vh] max-w-[95vw] overflow-hidden rounded-2xl bg-white shadow-2xl">
            <button
                type="button"
                class="absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted/60"
                @click="emit('update:open', false)"
            >
                <Icon name="x" class="h-4 w-4" />
            </button>
            <div class="flex w-[35%] flex-col border-r bg-gray-50">
                <div class="flex flex-none items-start justify-between border-b p-6">
                    <div>
                        <div class="flex items-center gap-2 text-lg font-semibold text-foreground">
                            <Icon name="sparkles" class="h-5 w-5 text-primary" />
                            <span>Improve Prompt Strategy</span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Describe what you didn't like about this response. PRISM will analyze the failed run and
                            suggest a refined version of your prompt.
                        </p>
                        <div class="mt-4 text-xs text-muted-foreground">
                            <div>Step: {{ activeStepMeta.name }}</div>
                            <div>Prompt: {{ targetPrompt?.role ?? '—' }}</div>
                            <div>Model: {{ activeStepMeta.model }}</div>
                            <div>Provider: {{ activeStepMeta.provider }}</div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto p-6">
                    <div class="space-y-4">
                        <div
                            v-for="message in feedbackThread"
                            :key="message.id"
                            class="rounded-lg px-3 py-2 text-sm shadow-sm"
                            :class="message.role === 'assistant' ? 'bg-white text-foreground' : 'bg-primary/10 text-foreground'"
                        >
                            <div class="text-xs text-muted-foreground">
                                {{ message.role === 'assistant' ? 'Model' : 'You' }} · {{ message.createdAt }}
                            </div>
                            <div class="mt-1 whitespace-pre-wrap">{{ message.text }}</div>
                        </div>
                        <div v-if="isGenerating" class="flex items-center gap-2 text-xs text-muted-foreground">
                            <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                            Analyzing...
                        </div>
                    </div>
                </div>

                <div class="flex flex-none flex-col gap-3 border-t bg-gray-50 p-6">
                    <Label for="fb_comment">Describe the issue</Label>
                    <textarea
                        id="fb_comment"
                        v-model="feedbackInput"
                        rows="3"
                        class="max-h-32 w-full resize-none rounded-md border border-input bg-white px-4 py-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                        placeholder="Too generic, didn't mention the user's context, missed key requirements..."
                    ></textarea>
                    <InputError :message="feedbackForm.errors.comment" />
                    <div class="grid gap-3">
                        <div class="grid gap-2 lg:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="fb_provider_credential_id">Provider</Label>
                                <Select
                                    inputId="fb_provider_credential_id"
                                    :model-value="feedbackForm.provider_credential_id"
                                    :options="providerCredentials"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Select provider"
                                    filter
                                    :filterFields="['label']"
                                    class="w-full"
                                    @update:model-value="(value) => { feedbackForm.provider_credential_id = value; handleProviderChange(); }"
                                />
                                <InputError :message="feedbackForm.errors.provider_credential_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="fb_model_name">Model</Label>
                                <Select
                                    inputId="fb_model_name"
                                    :model-value="feedbackForm.model_name"
                                    :options="modelOptions"
                                    optionLabel="display_name"
                                    optionValue="id"
                                    placeholder="Select model"
                                    filter
                                    :filterFields="['display_name', 'name']"
                                    class="w-full"
                                    :disabled="!feedbackForm.provider_credential_id"
                                    @update:model-value="(value) => { feedbackForm.model_name = value; }"
                                />
                                <p
                                    v-if="feedbackForm.provider_credential_id && !modelOptions.length"
                                    class="text-xs text-muted-foreground"
                                >
                                    No models available for this credential yet.
                                </p>
                                <InputError :message="feedbackForm.errors.model_name" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <Button
                                :disabled="isGenerating || !targetPrompt?.prompt_version_id"
                                @click="handleGenerateSuggestion"
                            >
                                Generate
                            </Button>
                        </div>
                        <p v-if="!targetPrompt?.prompt_version_id" class="text-xs text-muted-foreground">
                            This prompt is inline-only and cannot be improved as a version.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex w-[65%] flex-col bg-white">
                <div class="flex flex-none items-center justify-between border-b px-6 py-4 pr-16">
                    <div class="text-sm font-semibold text-foreground">Suggested Changes</div>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="small"
                            :class="diffViewMode === 'diff' ? 'border-primary text-primary' : ''"
                            @click="diffViewMode = 'diff'"
                        >
                            Diff View
                        </Button>
                        <Button
                            variant="outline"
                            size="small"
                            :class="diffViewMode === 'final' ? 'border-primary text-primary' : ''"
                            @click="diffViewMode = 'final'"
                        >
                            Final View
                        </Button>
                        <Button
                            outlined
                            :disabled="!suggestedPromptContent"
                            :class="suggestedPromptContent ? 'border-emerald-500 text-emerald-700' : 'text-muted-foreground'"
                            @click="applySuggestion"
                        >
                            Accept changes and save as new version
                        </Button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto p-6 font-mono text-sm">
                    <div v-if="diffViewMode === 'final'">
                        <pre class="whitespace-pre-wrap">
{{ suggestedPromptContent || 'Waiting for your feedback to analyze...' }}
                        </pre>
                    </div>
                    <div v-else class="space-y-1">
                        <div
                            v-if="!suggestedPromptContent"
                            class="flex flex-col items-center justify-center gap-2 py-16 text-muted-foreground"
                        >
                            <Icon name="sparkles" class="h-6 w-6 text-muted-foreground/70" />
                            <span>Your AI-powered improvements will appear here...</span>
                        </div>
                        <div v-else>
                            <div
                                v-for="(line, idx) in diffPreviewLines"
                                :key="`diff-${idx}`"
                                class="px-2 py-1"
                                :class="line.type === 'add' ? 'bg-emerald-50 text-emerald-800' : line.type === 'remove' ? 'bg-red-50 text-red-800' : 'text-foreground'"
                            >
                                <span class="text-muted-foreground">{{ diffLineSymbol(line.type) }}</span>
                                <span class="ml-2">{{ line.text }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
