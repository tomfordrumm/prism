<script setup lang="ts">
import { computed } from 'vue';
import Button from 'primevue/button';
import Icon from '@/components/Icon.vue';
import { jsonPretty } from '@/composables/useRunFormatters';
import type { RunStepPayload } from '@/types/runs';

interface Props {
    steps: RunStepPayload[];
    selectedStepId: number | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (event: 'update:selectedStepId', value: number | null): void;
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

            <div class="px-6 py-4">
                <div v-if="selectedStep" class="space-y-4 text-sm">
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
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase">Prompt details</h4>
                        <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground">
                            <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                <span>System</span>
                                <Button
                                    size="small"
                                    text
                                    @click="copyToClipboard(extractMessages(selectedStep).system || '')"
                                    aria-label="Copy system prompt"
                                >
                                    <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                                </Button>
                            </div>
                            <pre class="mt-2 whitespace-pre-wrap">{{ extractMessages(selectedStep).system || '—' }}</pre>
                        </div>
                        <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground">
                            <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                                <span>User</span>
                                <Button
                                    size="small"
                                    text
                                    @click="copyToClipboard(extractMessages(selectedStep).user || '')"
                                    aria-label="Copy user prompt"
                                >
                                    <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                                </Button>
                            </div>
                            <pre class="mt-2 whitespace-pre-wrap">{{ extractMessages(selectedStep).user || '—' }}</pre>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase">Raw response</h4>
                        <div class="rounded-md bg-muted/40 p-3 font-mono text-xs text-foreground">
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
                            <pre class="mt-2 whitespace-pre-wrap">{{ jsonPretty(selectedStep.response_raw) }}</pre>
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
