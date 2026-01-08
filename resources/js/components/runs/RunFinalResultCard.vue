<script setup lang="ts">
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Icon from '@/components/Icon.vue';
import { jsonPretty } from '@/composables/useRunFormatters';
import { useRunHighlighting } from '@/composables/useRunHighlighting';
import type { RunStepPayload } from '@/types/runs';

interface Props {
    finalStep: RunStepPayload | null;
    runStatus: string;
}

const props = defineProps<Props>();

const { highlightFinalResult } = useRunHighlighting();
const copiedResult = ref(false);

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const coerceContentText = (value: unknown): string | null => {
    if (typeof value === 'string') return value;
    if (Array.isArray(value)) {
        return value
            .map((item) => {
                if (typeof item === 'string') return item;
                if (isRecord(item) && typeof item.text === 'string') return item.text;
                return '';
            })
            .join('\n');
    }
    return null;
};

const finalContent = (step: RunStepPayload) => {
    if (step.response_content) {
        return step.response_content;
    }

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
                const content = coerceContentText(message.content);
                if (content) return content;
            }

            if (firstChoice.content != null) {
                const content = coerceContentText(firstChoice.content);
                if (content) return content;
            }
        }
    }

    const message = raw.message;
    if (isRecord(message) && message.content != null) {
        const content = coerceContentText(message.content);
        if (content) return content;
    }

    if (raw.content != null) {
        return typeof raw.content === 'string' ? raw.content : jsonPretty(raw.content);
    }

    if (step.parsed_output) return jsonPretty(step.parsed_output);

    return jsonPretty(raw ?? {});
};

const finalResultHtml = computed(() => {
    if (!props.finalStep) {
        return '<span class="text-muted-foreground">No result yet.</span>';
    }

    return highlightFinalResult(finalContent(props.finalStep));
});

const copyToClipboard = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch (error) {
        console.error('Copy failed', error);
    }
};

const handleCopy = async (text: string) => {
    await copyToClipboard(text);
    copiedResult.value = true;
    setTimeout(() => {
        copiedResult.value = false;
    }, 1500);
};
</script>

<template>
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-wrap items-start justify-between gap-2 text-xs text-muted-foreground">
            <div>
                <span class="font-semibold tracking-wide uppercase">Final result</span>
                <p v-if="finalStep" class="mt-1 text-[11px] text-muted-foreground">
                    Step #{{ finalStep.order_index }} ·
                    {{ finalStep.chain_node?.name || 'Step' }} ·
                    {{ finalStep.chain_node?.model_name || 'model' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Icon
                    v-if="runStatus === 'success'"
                    name="check"
                    class="h-3.5 w-3.5 text-emerald-600"
                />
                <Button
                    size="small"
                    text
                    :disabled="!finalStep"
                    @click="finalStep && handleCopy(finalContent(finalStep))"
                    aria-label="Copy final result"
                >
                    <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                </Button>
                <span v-if="copiedResult" class="text-emerald-600">Copied!</span>
            </div>
        </div>
        <pre
            class="hljs mt-3 max-h-96 overflow-auto bg-transparent text-sm whitespace-pre-wrap text-foreground"
            style="
                font-family:
                    'JetBrains Mono', 'Fira Code', Menlo,
                    monospace;
            "
        ><code v-html="finalResultHtml"></code></pre>
    </div>
</template>
