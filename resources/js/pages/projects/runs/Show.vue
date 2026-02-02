<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import RunHeader from '@/components/runs/RunHeader.vue';
import RunHistoryDrawer from '@/components/runs/RunHistoryDrawer.vue';
import RunFeedbackModal from '@/components/runs/RunFeedbackModal.vue';
import RunTracePanel from '@/components/runs/RunTracePanel.vue';
import RunInputCard from '@/components/runs/RunInputCard.vue';
import RunFinalResultCard from '@/components/runs/RunFinalResultCard.vue';
import { jsonPretty } from '@/composables/useRunFormatters';
import { useRunStream } from '@/composables/useRunStream';
import type {
    RunHistoryItem,
    RunPayload,
    RunStepPayload,
} from '@/types/runs';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface Props {
    project: ProjectPayload;
    run: RunPayload;
    steps: RunStepPayload[];
    runHistory: RunHistoryItem[];
}

const props = defineProps<Props>();

const run = ref<RunPayload>({ ...props.run });
const steps = ref<RunStepPayload[]>([...props.steps]);
const historyOpen = ref(false);
const { startStream, isLiveStatus } = useRunStream({
    projectUuid: props.project.uuid,
    run,
    steps,
});

const stepsAscending = computed(() =>
    [...steps.value].sort((a, b) => a.order_index - b.order_index),
);
const finalStep = computed(
    () => stepsAscending.value[stepsAscending.value.length - 1] ?? null,
);
const selectedStepId = ref<number | null>(null);
const tokenUsageLabel = computed(() => {
    const tokensIn = run.value.total_tokens_in;
    const tokensOut = run.value.total_tokens_out;

    if (tokensIn == null && tokensOut == null) return '—';

    return `${tokensIn ?? '—'} / ${tokensOut ?? '—'}`;
});

const historyEntries = computed(() => props.runHistory ?? []);

onMounted(startStream);

const feedbackModal = reactive({
    open: false,
    stepId: null as number | null,
    role: null as 'system' | 'user' | null,
});

const openFeedback = (stepId: number, role: 'system' | 'user') => {
    feedbackModal.open = true;
    feedbackModal.stepId = stepId;
    feedbackModal.role = role;
};

const handlePromptFeedback = (payload: { stepId: number; role: 'system' | 'user' }) => {
    openFeedback(payload.stepId, payload.role);
};

const selectDefaultStep = () => {
    if (!stepsAscending.value.length) {
        selectedStepId.value = null;
        return;
    }

    if (
        selectedStepId.value &&
        stepsAscending.value.some((step) => step.id === selectedStepId.value)
    ) {
        return;
    }

    const failedStep = stepsAscending.value.find(
        (step) => step.status === 'failed',
    );
    selectedStepId.value =
        failedStep?.id ??
        stepsAscending.value[stepsAscending.value.length - 1].id;
};

watch(
    () => stepsAscending.value,
    () => selectDefaultStep(),
    { immediate: true },
);

const activeFeedbackStep = computed(
    () => steps.value.find((step) => step.id === feedbackModal.stepId) ?? null,
);

const activeFeedbackTarget = computed(() => {
    const step = activeFeedbackStep.value;
    if (!step?.prompt_targets) return null;

    if (feedbackModal.role === 'system') {
        const target = step.prompt_targets.system;
        return target ? { ...target, role: 'system' as const } : null;
    }
    if (feedbackModal.role === 'user') {
        const target = step.prompt_targets.user;
        return target ? { ...target, role: 'user' as const } : null;
    }

    const fallback = step.prompt_targets.system ?? step.prompt_targets.user;
    if (!fallback) return null;
    return {
        ...fallback,
        role: step.prompt_targets.system ? ('system' as const) : ('user' as const),
    };
});

</script>

<template>
    <ProjectLayout :project="project" :title-suffix="`Runs • #${run.id}`">
        <RunHeader
            :project-uuid="project.uuid"
            :run="run"
            :token-usage-label="tokenUsageLabel"
            :is-live="isLiveStatus(run.status)"
            @open-history="historyOpen = true"
        />

        <div class="mt-4 rounded-lg border border-border/60 bg-muted/10">
            <div class="grid gap-0 lg:grid-cols-[2fr_3fr]">
                <div class="border-b border-border/60 p-4 lg:border-r lg:border-b-0 lg:border-border/60">
                    <RunInputCard :input="run.input" :dataset="run.dataset" :test-case="run.test_case" />
                </div>
                <div class="p-4">
                    <RunFinalResultCard
                        :final-step="finalStep"
                        :run-status="run.status"
                    />
                </div>
            </div>
        </div>

        <RunTracePanel
            v-model:selected-step-id="selectedStepId"
            :steps="steps"
            :run-id="run.id"
            @request-feedback="handlePromptFeedback"
            @feedback-added="handleFeedbackAdded"
        />

        <div class="mt-6">
            <Collapsible>
                <CollapsibleTrigger as-child>
                    <Button
                        text
                        size="small"
                        class="px-0 text-sm text-muted-foreground"
                    >
                        Run technical details
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div
                        class="mt-2 rounded-md border border-border/70 bg-muted/40 p-3 text-xs text-foreground"
                    >
                        <div class="font-semibold text-foreground">
                            Chain snapshot
                        </div>
                        <pre class="mt-1 whitespace-pre-wrap"
                            >{{ jsonPretty(run.chain_snapshot) }}
                        </pre>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </div>

        <RunFeedbackModal
            v-model:open="feedbackModal.open"
            :run-id="run.id"
            :project-uuid="project.uuid"
            :step="activeFeedbackStep"
            :target-prompt="activeFeedbackTarget"
        />
        <RunHistoryDrawer
            v-model:open="historyOpen"
            :entries="historyEntries"
            :current-run-id="run.id"
            :project-uuid="project.uuid"
        />
    </ProjectLayout>
</template>
