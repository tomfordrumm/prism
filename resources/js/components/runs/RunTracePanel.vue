<script setup lang="ts">
import { computed } from 'vue';
import Button from 'primevue/button';
import Icon from '@/components/Icon.vue';
import { jsonPretty } from '@/composables/useRunFormatters';
import type { RunStepPayload } from '@/types/runs';

interface Props {
    steps: RunStepPayload[];
    selectedStepId: number | null;
    runId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (event: 'update:selectedStepId', value: number | null): void;
    (event: 'request-feedback', payload: { stepId: number; role: 'system' | 'user' }): void;
    (event: 'feedback-added', payload: { stepId: number; feedback: RunStepPayload['feedback'][number] }): void;
}>();

const stepsDescending = computed(() =>
    [...props.steps].sort((a, b) => a.order_index - b.order_index).reverse(),
);

const selectedStep = computed(
    () => stepsDescending.value.find((step) => step.id === props.selectedStepId) ?? null,
);

const statusBadgeClass = (status: string) =>
    status === 'success'
        ? 'bg-emerald-100 text-emerald-700'
        : status === 'failed'
          ? 'bg-red-100 text-red-700'
          : 'bg-amber-100 text-amber-800';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const extractMessages = (step: RunStepPayload) => {
    const payload = step.request_payload;
    const messages = isRecord(payload) ? payload.messages : null;
    if (!Array.isArray(messages))
        return { system: null as string | null, user: null as string | null };

    const system = messages.find(
        (message) => isRecord(message) && message.role === 'system',
    );
    const user = messages.find(
        (message) => isRecord(message) && message.role === 'user',
    );

    const systemContent = isRecord(system) ? system.content : null;
    const userContent = isRecord(user) ? user.content : null;

    return {
        system:
            systemContent == null
                ? null
                : typeof systemContent === 'string'
                  ? systemContent
                  : jsonPretty(systemContent),
        user:
            userContent == null
                ? null
                : typeof userContent === 'string'
                  ? userContent
                  : jsonPretty(userContent),
    };
};

const copyToClipboard = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch (error) {
        console.error('Copy failed', error);
    }
};

const getCookie = (name: string) =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
        ?.split('=')[1] ?? '';

const submitRating = async (targetPromptVersionId: number, rating: -1 | 1) => {
    if (!selectedStep.value) return;

    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ||
        decodeURIComponent(getCookie('XSRF-TOKEN'));

    try {
        const response = await fetch(`/runs/${props.runId}/steps/${selectedStep.value.id}/feedback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                rating,
                comment: '',
                request_suggestion: false,
                target_prompt_version_id: targetPromptVersionId,
            }),
        });
        if (!response.ok) {
            return;
        }
        const payload = await response.json().catch(() => null);
        const feedback = payload?.feedback ?? null;
        emit('feedback-added', {
            stepId: selectedStep.value.id,
            feedback: feedback ?? {
                id: Date.now(),
                type: 'rating',
                rating,
                target_prompt_version_id: targetPromptVersionId,
            },
        });
    } catch (error) {
        console.error('Failed to submit rating', error);
    }
};

const outputTargetPromptVersionId = computed(() => {
    const step = selectedStep.value;
    if (!step) return null;
    return (
        step.target_prompt_version_id ??
        step.prompt_targets?.system?.prompt_version_id ??
        step.prompt_targets?.user?.prompt_version_id ??
        null
    );
});

const canRateOutput = computed(() => Boolean(outputTargetPromptVersionId.value));

const outputRating = computed(() => {
    const step = selectedStep.value;
    const targetPromptVersionId = outputTargetPromptVersionId.value;
    if (!step || !targetPromptVersionId) return null;
    const matching = (step.feedback ?? []).filter(
        (feedback) =>
            feedback.target_prompt_version_id === targetPromptVersionId &&
            typeof feedback.rating === 'number',
    );
    if (!matching.length) return null;
    return matching[matching.length - 1].rating ?? null;
});

const rateOutput = (rating: -1 | 1) => {
    if (!outputTargetPromptVersionId.value) return;
    submitRating(outputTargetPromptVersionId.value, rating);
};

const extractOutput = (step: RunStepPayload) => {
    if (step.response_content) return step.response_content;

    const raw = step.response_raw;
    if (!isRecord(raw)) {
        return jsonPretty(raw ?? {});
    }

    const candidates = raw.candidates;
    if (Array.isArray(candidates)) {
        const firstCandidate = candidates[0];
        if (isRecord(firstCandidate)) {
            const content = firstCandidate.content;
            const parts = isRecord(content) ? content.parts : null;
            if (Array.isArray(parts)) {
                const textParts = parts
                    .map((part) => (isRecord(part) ? part.text : null))
                    .filter((text) => typeof text === 'string' && text.length > 0);
                if (textParts.length) return textParts.join('');
            }
        }
    }

    const choices = raw.choices;
    if (Array.isArray(choices)) {
        const firstChoice = choices[0];
        if (isRecord(firstChoice)) {
            const message = firstChoice.message;
            if (isRecord(message) && message.content != null) {
                return typeof message.content === 'string'
                    ? message.content
                    : jsonPretty(message.content);
            }

            if (firstChoice.content != null) {
                return typeof firstChoice.content === 'string'
                    ? firstChoice.content
                    : jsonPretty(firstChoice.content);
            }
        }
    }

    const message = raw.message;
    if (isRecord(message) && message.content != null) {
        return typeof message.content === 'string'
            ? message.content
            : jsonPretty(message.content);
    }

    if (raw.content != null) {
        return typeof raw.content === 'string' ? raw.content : jsonPretty(raw.content);
    }

    if (step.parsed_output) return jsonPretty(step.parsed_output);

    return jsonPretty(raw ?? {});
};

const canImprove = (role: 'system' | 'user') => {
    const target = selectedStep.value?.prompt_targets?.[role];
    return Boolean(target?.prompt_version_id && target?.prompt_template_id);
};

const requestImprove = (role: 'system' | 'user') => {
    if (!selectedStep.value) return;
    emit('request-feedback', { stepId: selectedStep.value.id, role });
};
</script>

<template>
    <div class="mt-6 border-t border-border/60 pt-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-foreground">Trace</h3>
            <p class="text-xs text-muted-foreground">Select a step to inspect details.</p>
        </div>

        <div class="mt-4 grid gap-0 lg:grid-cols-[3fr_7fr]">
            <div class="border-r border-border/60">
                <div class="divide-y divide-border/60">
                    <button
                        v-for="step in stepsDescending"
                        :key="step.id"
                        type="button"
                        @click="emit('update:selectedStepId', step.id)"
                        :class="[
                            'flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm transition',
                            selectedStepId === step.id
                                ? 'border-l-2 border-primary bg-blue-50'
                                : 'border-l-2 border-transparent hover:bg-muted/40',
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold text-muted-foreground">#{{ step.order_index }}</span>
                            <span class="text-sm font-semibold text-foreground">
                                {{ step.chain_node?.model_name || 'model' }}
                            </span>
                        </div>
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :class="
                                step.status === 'failed'
                                    ? 'bg-red-500'
                                    : step.status === 'running'
                                      ? 'bg-amber-500'
                                      : 'bg-emerald-500'
                            "
                        ></span>
                    </button>
                </div>
            </div>

            <div class="px-6 py-4 min-w-0">
                <div v-if="selectedStep" class="space-y-4 text-sm min-w-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="text-xs text-muted-foreground">
                                Step #{{ selectedStep.order_index }}
                            </div>
                            <div class="text-sm font-semibold text-foreground">
                                {{ selectedStep.chain_node?.name || 'Step' }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ selectedStep.chain_node?.model_name || 'model' }} ·
                                {{ selectedStep.chain_node?.provider_name || selectedStep.chain_node?.provider || 'Provider' }}
                            </div>
                        </div>
                        <span class="rounded-md px-2 py-1 text-[11px] font-semibold" :class="statusBadgeClass(selectedStep.status)">
                            {{ selectedStep.status.toUpperCase() }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase">Output</h4>
                        <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground min-w-0">
                            <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                <span>Response</span>
                                <div class="flex items-center gap-2">
                                    <Button
                                        size="small"
                                        text
                                        :disabled="!canRateOutput"
                                        @click="rateOutput(1)"
                                        aria-label="Like output"
                                    >
                                        <Icon
                                            name="thumbsUp"
                                            class="h-3.5 w-3.5"
                                            :class="outputRating === 1 ? 'text-emerald-500' : 'text-muted-foreground'"
                                        />
                                    </Button>
                                    <Button
                                        size="small"
                                        text
                                        :disabled="!canRateOutput"
                                        @click="rateOutput(-1)"
                                        aria-label="Dislike output"
                                    >
                                        <Icon
                                            name="thumbsDown"
                                            class="h-3.5 w-3.5"
                                            :class="outputRating === -1 ? 'text-red-500' : 'text-muted-foreground'"
                                        />
                                    </Button>
                                    <Button
                                        size="small"
                                        text
                                        @click="copyToClipboard(extractOutput(selectedStep) || '')"
                                        aria-label="Copy output"
                                    >
                                        <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                </div>
                            </div>
                            <pre class="mt-2 max-h-72 max-w-full overflow-auto whitespace-pre-wrap break-all">{{ extractOutput(selectedStep) || '—' }}</pre>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase">Prompt details</h4>
                        <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground min-w-0">
                            <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                <span>System</span>
                                <div class="flex items-center gap-2">
                                    <Button
                                        size="small"
                                        text
                                        :disabled="!canImprove('system')"
                                        @click="requestImprove('system')"
                                        aria-label="Request improvement for system prompt"
                                    >
                                        <Icon name="sparkles" class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                    <Button
                                        size="small"
                                        text
                                        @click="copyToClipboard(extractMessages(selectedStep).system || '')"
                                        aria-label="Copy system prompt"
                                    >
                                        <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                </div>
                            </div>
                            <pre class="mt-2 max-h-64 max-w-full overflow-auto whitespace-pre-wrap break-all">{{ extractMessages(selectedStep).system || '—' }}</pre>
                        </div>
                        <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground min-w-0">
                            <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                <span>User</span>
                                <div class="flex items-center gap-2">
                                    <Button
                                        size="small"
                                        text
                                        :disabled="!canImprove('user')"
                                        @click="requestImprove('user')"
                                        aria-label="Request improvement for user prompt"
                                    >
                                        <Icon name="sparkles" class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                    <Button
                                        size="small"
                                        text
                                        @click="copyToClipboard(extractMessages(selectedStep).user || '')"
                                        aria-label="Copy user prompt"
                                    >
                                        <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                </div>
                            </div>
                            <pre class="mt-2 max-h-64 max-w-full overflow-auto whitespace-pre-wrap break-all">{{ extractMessages(selectedStep).user || '—' }}</pre>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase">Raw response</h4>
                        <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground min-w-0">
                            <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                <span>Response JSON</span>
                                <Button
                                    size="small"
                                    text
                                    @click="copyToClipboard(jsonPretty(selectedStep.response_raw))"
                                    aria-label="Copy response JSON"
                                >
                                    <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                                </Button>
                            </div>
                            <pre class="mt-2 max-h-72 max-w-full overflow-auto whitespace-pre-wrap break-all">{{ jsonPretty(selectedStep.response_raw) }}</pre>
                        </div>
                    </div>
                </div>
                <div v-else class="flex h-full items-center justify-center text-sm text-muted-foreground">
                    Select a step to inspect details.
                </div>
            </div>
        </div>
    </div>
</template>
