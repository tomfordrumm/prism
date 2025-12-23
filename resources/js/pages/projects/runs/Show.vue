<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import runsRoutes from '@/routes/projects/runs';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import Icon from '@/components/Icon.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { diffLines } from 'diff';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface ChainInfo {
    id: number;
    name: string;
}

interface RunStepPayload {
    id: number;
    order_index: number;
    status: string;
    chain_node: {
        id: number;
        name: string;
        provider?: string | null;
        provider_name?: string | null;
        model_name?: string | null;
    } | null;
    target_prompt_version_id?: number | null;
    target_prompt_template_id?: number | null;
    target_prompt_content?: string | null;
    request_payload: Record<string, unknown>;
    response_raw: Record<string, unknown>;
    parsed_output: unknown;
    tokens_in?: number | null;
    tokens_out?: number | null;
    duration_ms?: number | null;
    validation_errors?: string[] | null;
    created_at: string;
    feedback: {
        id: number;
        type: string;
        rating?: number | null;
        comment?: string | null;
        suggested_prompt_content?: string | null;
        analysis?: string | null;
    }[];
}

interface RunPayload {
    id: number;
    status: string;
    chain: ChainInfo | null;
    chain_label?: string | null;
    dataset?: { id: number; name: string } | null;
    test_case?: { id: number; name: string } | null;
    input: Record<string, unknown> | null;
    chain_snapshot: Record<string, unknown> | null;
    total_tokens_in?: number | null;
    total_tokens_out?: number | null;
    total_cost?: number | string | null;
    duration_ms?: number | null;
    created_at: string;
    finished_at?: string | null;
}

interface ProviderCredentialOption {
    value: number;
    label: string;
    provider: string;
}

interface ModelOption {
    id: string;
    name: string;
    display_name: string;
}

interface Props {
    project: ProjectPayload;
    run: RunPayload;
    steps: RunStepPayload[];
    providerCredentials: ProviderCredentialOption[];
    providerCredentialModels: Record<number, ModelOption[]>;
}

const props = defineProps<Props>();

const run = ref<RunPayload>({ ...props.run });
const steps = ref<RunStepPayload[]>([...props.steps]);
const eventSource = ref<EventSource | null>(null);

const stepsAscending = computed(() => [...steps.value].sort((a, b) => a.order_index - b.order_index));
const stepsDescending = computed(() => [...stepsAscending.value].reverse());
const finalStep = computed(() => stepsAscending.value[stepsAscending.value.length - 1] ?? null);
const selectedStepId = ref<number | null>(null);
const selectedStep = computed(
    () => stepsAscending.value.find((step) => step.id === selectedStepId.value) ?? null
);
const tokenUsageLabel = computed(() => {
    const tokensIn = run.value.total_tokens_in;
    const tokensOut = run.value.total_tokens_out;

    if (tokensIn == null && tokensOut == null) return '—';

    return `${tokensIn ?? '—'} / ${tokensOut ?? '—'}`;
});

const jsonPretty = (value: unknown) => {
    try {
        return JSON.stringify(value, null, 2);
    } catch (error) {
        return String(value);
    }
};

const isLiveStatus = (status?: string | null) => ['pending', 'running'].includes(status ?? '');

const startStream = () => {
    if (typeof window === 'undefined') return;
    if (!isLiveStatus(run.value.status)) return;

const streamUrl = `/projects/${props.project.uuid}/runs/${run.value.id}/stream`;

    if (eventSource.value) {
        eventSource.value.close();
    }

    const es = new EventSource(streamUrl);
    eventSource.value = es;

    es.onmessage = (event) => {
        try {
            const payload = JSON.parse(event.data || '{}');
            if (payload.run) {
                run.value = payload.run;
            }
            if (payload.steps) {
                steps.value = payload.steps;
            }

            if (!isLiveStatus(payload.run?.status ?? run.value.status)) {
                es.close();
                eventSource.value = null;
            }
        } catch (error) {
            console.error('Failed to parse run stream payload', error);
        }
    };

    es.onerror = () => {
        es.close();
        eventSource.value = null;
    };
};

onMounted(startStream);
onBeforeUnmount(() => {
    eventSource.value?.close();
});

const statusBadgeClass = (status: string) =>
    status === 'success'
        ? 'bg-emerald-100 text-emerald-700'
        : status === 'failed'
          ? 'bg-red-100 text-red-700'
          : 'bg-amber-100 text-amber-800';

const finalContent = (step: RunStepPayload) => {
    const raw = step.response_raw as any;

    const choiceContent =
        raw?.choices?.[0]?.message?.content ??
        raw?.choices?.[0]?.content ??
        raw?.message?.content ??
        null;

    if (choiceContent) {
        return Array.isArray(choiceContent) ? choiceContent.map((c: any) => c?.text ?? '').join('\n') : choiceContent;
    }

    const content = raw?.content ?? null;
    if (content) return typeof content === 'string' ? content : jsonPretty(content);

    if (step.parsed_output) return jsonPretty(step.parsed_output);

    return jsonPretty(raw ?? {});
};

const extractMessages = (step: RunStepPayload) => {
    const messages = (step.request_payload as any)?.messages;
    if (!Array.isArray(messages)) return { system: null as string | null, user: null as string | null };

    const system = messages.find((m) => m.role === 'system')?.content ?? null;
    const user = messages.find((m) => m.role === 'user')?.content ?? null;

    return {
        system: typeof system === 'string' ? system : jsonPretty(system),
        user: typeof user === 'string' ? user : jsonPretty(user),
    };
};

const copyToClipboard = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch (error) {
        console.error('Copy failed', error);
    }
};

const durationHuman = (ms?: number | null) => {
    if (!ms && ms !== 0) return '—';
    if (ms >= 1000) return `${(ms / 1000).toFixed(2)} s`;
    return `${ms} ms`;
};

const formatTimestamp = (value?: string | null) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleString(undefined, {
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatCost = (value?: number | string | null) => {
    if (value == null) return null;
    const num = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(num)) return String(value);
    return `$${num.toFixed(4)}`;
};

const feedbackForm = useForm({
    rating: null as number | null,
    comment: '',
    request_suggestion: false,
    provider_credential_id: null as number | null,
    model_name: '',
});
const feedbackModal = reactive({
    open: false,
    stepId: null as number | null,
});

const modelOptions = computed(() => {
    const credentialId = feedbackForm.provider_credential_id;

    if (!credentialId) return [];

    return props.providerCredentialModels[credentialId] ?? [];
});

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
        }
    },
);

const openFeedback = (stepId: number) => {
    feedbackModal.open = true;
    feedbackModal.stepId = stepId;
    feedbackForm.reset();
};

const openFinalFeedback = () => {
    if (!finalStep.value) return;
    openFeedback(finalStep.value.id);
};

const selectDefaultStep = () => {
    if (!stepsAscending.value.length) {
        selectedStepId.value = null;
        return;
    }

    if (selectedStepId.value && stepsAscending.value.some((step) => step.id === selectedStepId.value)) {
        return;
    }

    const failedStep = stepsAscending.value.find((step) => step.status === 'failed');
    selectedStepId.value = failedStep?.id ?? stepsAscending.value[stepsAscending.value.length - 1].id;
};

watch(() => stepsAscending.value, () => selectDefaultStep(), { immediate: true });

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
                    return { type: 'add', oldLine: null, newLine: newLine++, text: line };
                }

                if (part.removed) {
                    return { type: 'remove', oldLine: oldLine++, newLine: null, text: line };
                }

                return { type: 'context', oldLine: oldLine++, newLine: newLine++, text: line };
            });
    });
};

const diffLineClass = (type: DiffLine['type']) => {
    if (type === 'add') return 'bg-emerald-50/70 text-emerald-900';
    if (type === 'remove') return 'bg-red-50/70 text-red-900';

    return 'text-foreground';
};

const diffLineSymbol = (type: DiffLine['type']) => {
    if (type === 'add') return '+';
    if (type === 'remove') return '-';

    return ' ';
};

const feedbackDiffs = computed(() => {
    const diffMap = new Map<number, DiffLine[]>();

    steps.value.forEach((step) => {
        const current = step.target_prompt_content || '';

        step.feedback?.forEach((fb) => {
            const suggestion = fb.suggested_prompt_content || '';
            diffMap.set(fb.id, buildDiffLines(current, suggestion));
        });
    });

    return diffMap;
});

const submitFeedback = () => {
    if (!feedbackModal.stepId) return;

    feedbackForm.post(`/runs/${run.value.id}/steps/${feedbackModal.stepId}/feedback`, {
        preserveScroll: true,
        onSuccess: () => {
            feedbackModal.open = false;
        },
    });
};

const createVersionFromSuggestion = (step: RunStepPayload, feedbackId: number) => {
    if (!step.target_prompt_template_id) return;

    router.post(
        `/projects/${props.project.uuid}/prompts/${step.target_prompt_template_id}/versions/from-feedback`,
        {
            feedback_id: feedbackId,
        },
    );
};
</script>

<template>
    <ProjectLayout :project="project" :title-suffix="`Runs • #${run.id}`">
        <div class="flex h-12 items-center gap-4 border-b border-border/60 px-4 text-sm">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold text-foreground">Run #{{ run.id }}</h2>
                <span class="text-sm text-muted-foreground">
                    {{ run.chain_label || run.chain?.name || 'Prompt run' }}
                </span>
            </div>
            <span class="h-4 w-px bg-border/70"></span>
            <div class="flex flex-wrap items-center gap-4 text-muted-foreground">
                <div class="flex items-center gap-2">
                    <Spinner v-if="isLiveStatus(run.status)" class="text-primary" />
                    <span class="rounded-md px-2 py-1 text-[11px] font-semibold" :class="statusBadgeClass(run.status)">
                        {{ run.status.toUpperCase() }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Icon name="clock" class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium text-foreground">{{ durationHuman(run.duration_ms) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <Icon name="cpu" class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium text-foreground">{{ tokenUsageLabel }}</span>
                    <span class="text-xs text-muted-foreground">tokens</span>
                </div>
                <template v-if="formatCost(run.total_cost)">
                    <div class="flex items-center gap-2">
                        <Icon name="dollarSign" class="h-4 w-4 text-muted-foreground" />
                        <span class="font-medium text-foreground">{{ formatCost(run.total_cost) }}</span>
                    </div>
                </template>
                <div class="flex items-center gap-2">
                    <Icon name="calendar" class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium text-foreground">{{ formatTimestamp(run.created_at) }}</span>
                </div>
            </div>
            <div class="ml-auto">
                <Link
                    :href="runsRoutes.index(project.uuid).url"
                    class="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    Back to runs
                </Link>
            </div>
        </div>

        <div class="mt-4 rounded-lg border border-border/60 bg-muted/10">
            <div class="grid gap-0 lg:grid-cols-[2fr_3fr]">
                <div class="border-b border-border/60 p-4 lg:border-b-0 lg:border-r lg:border-border/60">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-foreground">Input</h3>
                        <Button
                            size="xs"
                            variant="ghost"
                            @click="copyToClipboard(jsonPretty(run.input ?? {}))"
                        >
                            <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                        </Button>
                    </div>
                    <pre class="mt-3 max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-white px-3 py-2 text-xs font-mono text-foreground">
{{ jsonPretty(run.input) }}
                    </pre>
                    <div
                        v-if="run.dataset || run.test_case"
                        class="mt-3 rounded-md border border-dashed border-border/70 p-3 text-xs text-muted-foreground"
                    >
                        Dataset: {{ run.dataset?.name || '—' }} • Test case: {{ run.test_case?.name || '—' }}
                    </div>
                </div>

                <div class="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">Final result</h3>
                            <p v-if="finalStep" class="text-xs text-muted-foreground">
                                Step #{{ finalStep.order_index }} · {{ finalStep.chain_node?.name || 'Step' }} ·
                                {{ finalStep.chain_node?.model_name || 'model' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                size="xs"
                                variant="outline"
                                :disabled="!finalStep"
                                @click="finalStep && copyToClipboard(finalContent(finalStep))"
                            >
                                Copy
                            </Button>
                            <Button size="xs" :disabled="!finalStep" @click="openFinalFeedback">Improve</Button>
                        </div>
                    </div>
                    <div class="mt-3 max-h-96 overflow-auto rounded-md bg-white px-4 py-3 text-sm leading-relaxed text-foreground">
                        <pre class="whitespace-pre-wrap font-sans">
{{ finalStep ? finalContent(finalStep) : 'No result yet.' }}
                        </pre>
                    </div>
                </div>
            </div>
        </div>

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
                            @click="selectedStepId = step.id"
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
                                :class="step.status === 'failed' ? 'bg-red-500' : step.status === 'running' ? 'bg-amber-500' : 'bg-emerald-500'"
                            ></span>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-4">
                    <div v-if="selectedStep" class="space-y-4 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="text-xs text-muted-foreground">Step #{{ selectedStep.order_index }}</div>
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
                            <h4 class="text-xs font-semibold uppercase text-muted-foreground">Prompt details</h4>
                            <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground">
                                <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>System</span>
                                    <Button size="xs" variant="ghost" @click="copyToClipboard(extractMessages(selectedStep).system || '')">
                                        <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                </div>
                                <pre class="mt-2 whitespace-pre-wrap">
{{ extractMessages(selectedStep).system || '—' }}
                                </pre>
                            </div>
                            <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground">
                                <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>User</span>
                                    <Button size="xs" variant="ghost" @click="copyToClipboard(extractMessages(selectedStep).user || '')">
                                        <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                </div>
                                <pre class="mt-2 whitespace-pre-wrap">
{{ extractMessages(selectedStep).user || '—' }}
                                </pre>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <h4 class="text-xs font-semibold uppercase text-muted-foreground">Raw response</h4>
                            <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground">
                                <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>Response JSON</span>
                                    <Button size="xs" variant="ghost" @click="copyToClipboard(jsonPretty(selectedStep.response_raw))">
                                        <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                                    </Button>
                                </div>
                                <pre class="mt-2 whitespace-pre-wrap">
{{ jsonPretty(selectedStep.response_raw) }}
                                </pre>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex h-full items-center justify-center text-sm text-muted-foreground">
                        Select a step to inspect details.
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <Collapsible>
                <CollapsibleTrigger as-child>
                    <Button variant="ghost" size="sm" class="px-0 text-sm text-muted-foreground">
                        Run technical details
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div class="mt-2 rounded-md border border-border/70 bg-muted/40 p-3 text-xs text-foreground">
                        <div class="font-semibold text-foreground">Chain snapshot</div>
                        <pre class="mt-1 whitespace-pre-wrap">
{{ jsonPretty(run.chain_snapshot) }}
                        </pre>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </div>

        <Dialog :open="feedbackModal.open" @update:open="feedbackModal.open = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Feedback</DialogTitle>
                </DialogHeader>
                <div class="space-y-3">
                    <div class="grid gap-2">
                        <Label for="fb_rating">Rating (optional)</Label>
                        <Input
                            id="fb_rating"
                            type="number"
                            v-model.number="feedbackForm.rating"
                            min="1"
                            max="5"
                        />
                        <InputError :message="feedbackForm.errors.rating" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fb_comment">Comment</Label>
                        <textarea
                            id="fb_comment"
                            v-model="feedbackForm.comment"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        ></textarea>
                        <InputError :message="feedbackForm.errors.comment" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            id="fb_request_suggestion"
                            v-model="feedbackForm.request_suggestion"
                            type="checkbox"
                            class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                        />
                        <Label for="fb_request_suggestion">Request suggestion from LLM</Label>
                    </div>
                    <InputError :message="feedbackForm.errors.suggestion" />
                    <div v-if="feedbackForm.request_suggestion" class="grid gap-2">
                        <Label for="fb_provider_credential_id">Provider credential</Label>
                        <select
                            id="fb_provider_credential_id"
                            v-model.number="feedbackForm.provider_credential_id"
                            name="provider_credential_id"
                            @change="handleProviderChange"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            <option :value="null">Select provider credential</option>
                            <option
                                v-for="credential in providerCredentials"
                                :key="credential.value"
                                :value="credential.value"
                            >
                                {{ credential.label }}
                            </option>
                        </select>
                        <InputError :message="feedbackForm.errors.provider_credential_id" />
                    </div>
                    <div v-if="feedbackForm.request_suggestion" class="grid gap-2">
                        <Label for="fb_model_name">Model</Label>
                        <select
                            id="fb_model_name"
                            v-model="feedbackForm.model_name"
                            name="model_name"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            <option value="" disabled>Select model</option>
                            <option v-for="model in modelOptions" :key="model.id" :value="model.id">
                                {{ model.display_name }} ({{ model.name }})
                            </option>
                        </select>
                        <p v-if="feedbackForm.provider_credential_id && !modelOptions.length" class="text-xs text-muted-foreground">
                            No models available for this credential yet.
                        </p>
                        <InputError :message="feedbackForm.errors.model_name" />
                    </div>
                </div>
                <DialogFooter class="flex justify-end gap-2">
                    <Button variant="outline" @click="feedbackModal.open = false">Cancel</Button>
                    <Button :disabled="feedbackForm.processing" @click="submitFeedback">Submit</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </ProjectLayout>
</template>
