<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { diffLines } from 'diff';
import Button from 'primevue/button';
import Icon from '@/components/Icon.vue';
import PromptChat from '@/components/prompts/PromptChat.vue';
import type {
    RunStepPayload,
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
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const diffViewMode = ref<'diff' | 'final'>('diff');
const suggestedPromptContent = ref('');
const isSubmitting = ref(false);

const targetPrompt = computed(() => props.targetPrompt);

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

const handleChatSuggestion = (payload: { suggestedPrompt?: string | null; analysis?: string | null }) => {
    suggestedPromptContent.value = payload.suggestedPrompt ?? '';
};

const applySuggestion = () => {
    const targetTemplateId = props.targetPrompt?.prompt_template_id ?? null;
    if (!targetTemplateId || !suggestedPromptContent.value) return;

    router.post(
        `/projects/${props.projectUuid}/prompts/${targetTemplateId}/versions`,
        {
            content: suggestedPromptContent.value,
            changelog: 'Created from feedback chat',
        },
        {
            preserveScroll: true,
            onStart: () => {
                isSubmitting.value = true;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
            onSuccess: () => {
                emit('update:open', false);
            },
        },
    );
};

const saveAndRun = () => {
    const targetTemplateId = props.targetPrompt?.prompt_template_id ?? null;
    if (!targetTemplateId || !suggestedPromptContent.value) return;

    router.post(
        `/projects/${props.projectUuid}/prompts/${targetTemplateId}/versions/run`,
        {
            content: suggestedPromptContent.value,
            changelog: 'Created from feedback chat',
            run_id: props.runId,
        },
        {
            preserveScroll: true,
            onStart: () => {
                isSubmitting.value = true;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
            onSuccess: () => {
                emit('update:open', false);
            },
        },
    );
};

const conversationContextKey = computed(
    () => `${props.step?.id ?? 'none'}-${props.targetPrompt?.prompt_version_id ?? 'none'}`,
);

const resetFeedbackState = () => {
    suggestedPromptContent.value = '';
    diffViewMode.value = 'diff';
};

watch(
    () => [props.open, props.step?.id, props.targetPrompt?.prompt_version_id],
    ([open]) => {
        if (!open) return;
        resetFeedbackState();
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

                <div class="flex-1 min-h-0 p-6">
                    <PromptChat
                        class="h-full"
                        :project-uuid="projectUuid"
                        type="run_feedback"
                        :run-id="runId"
                        :run-step-id="step?.id ?? null"
                        :target-prompt-version-id="targetPrompt?.prompt_version_id ?? null"
                        :active="open"
                        :context-key="conversationContextKey"
                        :show-header="false"
                        :show-welcome="false"
                        placeholder="Describe what should be improved..."
                        max-width-class="max-w-full"
                        @suggestion="handleChatSuggestion"
                    />
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
                            :disabled="!suggestedPromptContent || !targetPrompt?.prompt_template_id || isSubmitting"
                            :class="
                                suggestedPromptContent && targetPrompt?.prompt_template_id
                                    ? 'border-emerald-500 text-emerald-700'
                                    : 'text-muted-foreground'
                            "
                            @click="applySuggestion"
                        >
                            Accept changes and save as new version
                        </Button>
                        <Button
                            outlined
                            :disabled="!suggestedPromptContent || !targetPrompt?.prompt_template_id || isSubmitting"
                            :class="
                                suggestedPromptContent && targetPrompt?.prompt_template_id
                                    ? 'border-blue-500 text-blue-700'
                                    : 'text-muted-foreground'
                            "
                            @click="saveAndRun"
                        >
                            Save and run
                        </Button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto p-6 font-mono text-sm">
                    <p v-if="!targetPrompt?.prompt_template_id" class="mb-3 text-xs text-muted-foreground">
                        This prompt is inline-only and cannot be saved as a version.
                    </p>
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
