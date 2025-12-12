<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import runsRoutes from '@/routes/projects/runs';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { diffLines } from 'diff';

interface ProjectPayload {
    id: number;
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
    dataset?: { id: number; name: string } | null;
    test_case?: { id: number; name: string } | null;
    input: Record<string, unknown> | null;
    chain_snapshot: Record<string, unknown> | null;
    total_tokens_in?: number | null;
    total_tokens_out?: number | null;
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

const sortedSteps = computed(() => [...props.steps].sort((a, b) => a.order_index - b.order_index));

const jsonPretty = (value: unknown) => {
    try {
        return JSON.stringify(value, null, 2);
    } catch (error) {
        return String(value);
    }
};

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

    props.steps.forEach((step) => {
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

    feedbackForm.post(`/runs/${props.run.id}/steps/${feedbackModal.stepId}/feedback`, {
        preserveScroll: true,
        onSuccess: () => {
            feedbackModal.open = false;
        },
    });
};

const createVersionFromSuggestion = (step: RunStepPayload, feedbackId: number) => {
    if (!step.target_prompt_template_id) return;

    router.post(
        `/projects/${props.project.id}/prompts/${step.target_prompt_template_id}/versions/from-feedback`,
        {
            feedback_id: feedbackId,
        },
    );
};
</script>

<template>
    <ProjectLayout :project="project" :title-suffix="`Runs • #${run.id}`">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-foreground">Run #{{ run.id }}</h2>
                <p class="text-sm text-muted-foreground">Chain: {{ run.chain?.name || 'N/A' }}</p>
            </div>
            <Link
                :href="runsRoutes.index(project.id).url"
                class="rounded-md border border-border px-3 py-2 text-sm text-muted-foreground hover:text-foreground"
            >
                Back to runs
            </Link>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs uppercase text-muted-foreground">Status</p>
                <div class="mt-2 flex items-center gap-2">
                    <span class="rounded-md px-2 py-1 text-xs font-semibold" :class="statusBadgeClass(run.status)">
                        {{ run.status.toUpperCase() }}
                    </span>
                </div>
            </div>
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs uppercase text-muted-foreground">Tokens</p>
                <div class="mt-2 text-sm text-foreground">
                    <div>
                        Total:
                        {{
                            run.total_tokens_in != null || run.total_tokens_out != null
                                ? (run.total_tokens_in ?? 0) + (run.total_tokens_out ?? 0)
                                : '—'
                        }}
                    </div>
                    <div class="text-muted-foreground">
                        Prompt: {{ run.total_tokens_in ?? '—' }} • Completion: {{ run.total_tokens_out ?? '—' }}
                    </div>
                </div>
            </div>
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs uppercase text-muted-foreground">Timing</p>
                <div class="mt-2 text-sm text-foreground">
                    {{ durationHuman(run.duration_ms) }}
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[1.2fr_1fr]">
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-foreground">Input</h3>
                    <Button size="xs" variant="outline" @click="copyToClipboard(jsonPretty(run.input ?? {}))">Copy</Button>
                </div>
                <pre class="mt-3 max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-muted px-3 py-2 text-xs text-foreground">
{{ jsonPretty(run.input) }}
                </pre>
            </div>

            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-foreground">Chain overview</h3>
                <p class="mt-1 text-sm font-medium text-foreground">
                    {{ run.chain?.name || 'N/A' }} • {{ sortedSteps.length }} steps
                </p>
                <div class="mt-2 space-y-1 text-sm text-muted-foreground">
                    <div v-for="step in sortedSteps" :key="step.id" class="flex items-center gap-2">
                        <span class="text-[11px] font-semibold text-muted-foreground">#{{ step.order_index }}</span>
                        <span class="text-foreground">{{ step.chain_node?.name || 'Step' }}</span>
                    </div>
                </div>
                <div
                    v-if="run.dataset || run.test_case"
                    class="mt-3 rounded-md border border-dashed border-border/70 p-3 text-xs text-muted-foreground"
                >
                    Dataset: {{ run.dataset?.name || '—' }} • Test case: {{ run.test_case?.name || '—' }}
                </div>
            </div>
        </div>

        <div class="mt-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-foreground">Steps</h3>
                <p class="text-xs text-muted-foreground">Click a step to inspect details.</p>
            </div>

            <div
                v-for="step in sortedSteps"
                :key="step.id"
                class="rounded-lg border border-border bg-card shadow-sm"
            >
                <Collapsible>
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-xs font-semibold text-muted-foreground">#{{ step.order_index }}</span>
                            <span class="font-semibold text-foreground">{{ step.chain_node?.name || 'Step' }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ step.chain_node?.provider_name || step.chain_node?.provider || 'Provider' }} /
                                {{ step.chain_node?.model_name || 'model' }}
                            </span>
                            <span class="text-xs text-muted-foreground">Duration: {{ durationHuman(step.duration_ms) }}</span>
                            <span class="text-xs text-muted-foreground">
                                Tokens: {{ step.tokens_out ?? '—' }} completion
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-md px-2 py-1 text-[11px] font-semibold" :class="statusBadgeClass(step.status)">
                                {{ step.status.toUpperCase() }}
                            </span>
                            <CollapsibleTrigger as-child>
                                <Button variant="outline" size="sm">Toggle</Button>
                            </CollapsibleTrigger>
                        </div>
                    </div>

                    <CollapsibleContent>
                        <div class="space-y-3 border-t border-border px-4 py-3">
                            <div class="rounded-md border border-border/70 bg-background p-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-foreground">Final response</h4>
                                    <Button size="xs" variant="outline" @click="copyToClipboard(finalContent(step))">Copy</Button>
                                </div>
                                <pre class="mt-2 whitespace-pre-wrap rounded-md bg-card px-3 py-2 text-sm text-foreground">
{{ finalContent(step) }}
                                </pre>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-md border border-border/70 bg-background p-3">
                                    <h4 class="text-sm font-semibold text-foreground">System prompt</h4>
                                    <pre class="mt-2 whitespace-pre-wrap text-xs text-foreground">
{{ extractMessages(step).system || '—' }}
                                    </pre>
                                </div>
                                <div class="rounded-md border border-border/70 bg-background p-3">
                                    <h4 class="text-sm font-semibold text-foreground">User prompt</h4>
                                    <pre class="mt-2 whitespace-pre-wrap text-xs text-foreground">
{{ extractMessages(step).user || '—' }}
                                    </pre>
                                </div>
                            </div>

                            <div v-if="step.parsed_output" class="rounded-md border border-border/70 bg-background p-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-foreground">Parsed output</h4>
                                    <Button size="xs" variant="outline" @click="copyToClipboard(jsonPretty(step.parsed_output))">
                                        Copy
                                    </Button>
                                </div>
                                <pre class="mt-2 whitespace-pre-wrap text-xs text-foreground">
{{ jsonPretty(step.parsed_output) }}
                                </pre>
                            </div>

                            <div v-if="step.validation_errors && step.validation_errors.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                                <div class="font-semibold">Validation errors</div>
                                <ul class="list-disc pl-4">
                                    <li v-for="err in step.validation_errors" :key="err">{{ err }}</li>
                                </ul>
                            </div>

                            <div v-if="step.feedback && step.feedback.length" class="space-y-2">
                                <div class="text-sm font-semibold text-foreground">Feedback</div>
                                <div
                                    v-for="fb in step.feedback"
                                    :key="fb.id"
                                    class="rounded-md border border-border/70 bg-background p-3 text-sm"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="font-semibold text-foreground">
                                            {{ fb.type === 'llm_suggestion' ? 'LLM suggestion' : 'User feedback' }}
                                        </div>
                                        <div class="text-xs text-muted-foreground">Rating: {{ fb.rating ?? '—' }}</div>
                                    </div>
                                    <p class="mt-1 text-muted-foreground text-sm">
                                        {{ fb.comment || 'No comment' }}
                                    </p>
                                    <div v-if="fb.analysis" class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                        <div class="font-semibold">Analysis</div>
                                        <p class="mt-1 whitespace-pre-wrap">{{ fb.analysis }}</p>
                                    </div>
                                    <Collapsible v-if="fb.suggested_prompt_content">
                                        <CollapsibleTrigger as-child>
                                            <Button size="xs" variant="ghost" class="mt-2 px-0 text-xs">View suggestion</Button>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <div class="mt-2 space-y-3 rounded-md border border-border bg-card p-3">
                                                <div class="flex items-center justify-between">
                                                    <div class="text-sm font-semibold text-foreground">Prompt comparison</div>
                                                    <div class="text-[11px] text-muted-foreground">Current vs suggested</div>
                                                </div>
                                                <div class="grid gap-3 lg:grid-cols-[1fr_1.1fr_1fr]">
                                                    <div class="rounded-md border border-border/70 bg-muted/40">
                                                        <div class="flex items-center justify-between border-b border-border/60 px-3 py-2">
                                                            <div class="text-xs font-semibold text-foreground">Current prompt</div>
                                                            <Button
                                                                size="xs"
                                                                variant="ghost"
                                                                class="px-2 text-[11px]"
                                                                @click="copyToClipboard(step.target_prompt_content || '')"
                                                            >
                                                                Copy
                                                            </Button>
                                                        </div>
                                                        <pre class="max-h-64 overflow-auto whitespace-pre-wrap px-3 py-2 text-[11px] leading-relaxed text-foreground">
{{ step.target_prompt_content || '—' }}
                                                        </pre>
                                                    </div>

                                                    <div class="rounded-md border border-border/70 bg-muted/20">
                                                        <div class="flex items-center justify-between border-b border-border/60 px-3 py-2">
                                                            <div class="text-xs font-semibold text-foreground">Diff</div>
                                                            <div class="text-[11px] text-muted-foreground">- / +</div>
                                                        </div>
                                                        <div class="max-h-64 overflow-auto text-[11px] font-mono leading-relaxed">
                                                            <div
                                                                v-for="(line, idx) in feedbackDiffs.get(fb.id) || []"
                                                                :key="idx"
                                                                class="flex gap-2 border-b border-border/50 px-3 py-1 last:border-0"
                                                                :class="diffLineClass(line.type)"
                                                            >
                                                                <span class="w-10 shrink-0 text-right text-[10px] text-muted-foreground">
                                                                    {{ line.oldLine ?? '' }}
                                                                </span>
                                                                <span class="w-10 shrink-0 text-right text-[10px] text-muted-foreground">
                                                                    {{ line.newLine ?? '' }}
                                                                </span>
                                                                <span class="w-4 shrink-0 text-center font-semibold">
                                                                    {{ diffLineSymbol(line.type) }}
                                                                </span>
                                                                <span class="whitespace-pre-wrap text-foreground/90">
                                                                    {{ line.text || ' ' }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                v-if="!(feedbackDiffs.get(fb.id) || []).length"
                                                                class="px-3 py-2 text-muted-foreground"
                                                            >
                                                                No differences detected.
                                                            </div>
                                                        </div>
                                                        <div class="border-t border-border/60 px-3 py-2 text-[11px] text-muted-foreground">
                                                            Green = added, red = removed.
                                                        </div>
                                                    </div>

                                                    <div class="rounded-md border border-border/70 bg-muted/40">
                                                        <div class="flex items-center justify-between border-b border-border/60 px-3 py-2">
                                                            <div class="text-xs font-semibold text-foreground">Suggested prompt</div>
                                                            <Button
                                                                size="xs"
                                                                variant="ghost"
                                                                class="px-2 text-[11px]"
                                                                @click="copyToClipboard(fb.suggested_prompt_content || '')"
                                                            >
                                                                Copy
                                                            </Button>
                                                        </div>
                                                        <pre class="max-h-64 overflow-auto whitespace-pre-wrap px-3 py-2 text-[11px] leading-relaxed text-foreground">
{{ fb.suggested_prompt_content || '—' }}
                                                        </pre>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-end">
                                                    <Button
                                                        size="xs"
                                                        class="mt-1"
                                                        :disabled="!step.target_prompt_template_id"
                                                        @click="createVersionFromSuggestion(step, fb.id)"
                                                    >
                                                        Create new version
                                                    </Button>
                                                </div>
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button size="xs" variant="outline" @click="openFeedback(step.id)">Feedback / Improve</Button>
                            </div>

                            <Collapsible>
                                <CollapsibleTrigger as-child>
                                    <Button variant="ghost" size="sm" class="px-0 text-sm text-muted-foreground">
                                        Technical details
                                    </Button>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div class="mt-2 space-y-2 rounded-md border border-border/70 bg-muted/40 p-3 text-xs text-foreground">
                                        <div>
                                            <div class="font-semibold text-foreground">Request payload</div>
                                            <pre class="mt-1 whitespace-pre-wrap">
{{ jsonPretty(step.request_payload) }}
                                            </pre>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-foreground">Response raw</div>
                                            <pre class="mt-1 whitespace-pre-wrap">
{{ jsonPretty(step.response_raw) }}
                                            </pre>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-foreground">Parsed output</div>
                                            <pre class="mt-1 whitespace-pre-wrap">
{{ jsonPretty(step.parsed_output) }}
                                            </pre>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            Tokens in: {{ step.tokens_in ?? '—' }} • out: {{ step.tokens_out ?? '—' }}
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
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
