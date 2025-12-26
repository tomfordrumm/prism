<script setup lang="ts">
import { ref } from 'vue';
import Button from 'primevue/button';
import Icon from '@/components/Icon.vue';
import { jsonPretty } from '@/composables/useRunFormatters';
import { useRunHighlighting } from '@/composables/useRunHighlighting';
import type { RunDatasetInfo } from '@/types/runs';

interface Props {
    input: Record<string, unknown> | null;
    dataset?: RunDatasetInfo | null;
    testCase?: RunDatasetInfo | null;
}

const props = defineProps<Props>();

const { highlightJson } = useRunHighlighting();
const copiedInput = ref(false);

const copyToClipboard = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch (error) {
        console.error('Copy failed', error);
    }
};

const handleCopy = async (text: string) => {
    await copyToClipboard(text);
    copiedInput.value = true;
    setTimeout(() => {
        copiedInput.value = false;
    }, 1500);
};
</script>

<template>
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-center justify-between text-xs text-muted-foreground">
            <span class="font-semibold tracking-wide uppercase">Input</span>
            <div class="flex items-center gap-2">
                <Button
                    size="small"
                    text
                    @click="handleCopy(jsonPretty(input ?? {}))"
                    aria-label="Copy input"
                >
                    <Icon name="copy" class="h-3.5 w-3.5 text-muted-foreground" />
                </Button>
                <span v-if="copiedInput" class="text-emerald-600">Copied!</span>
            </div>
        </div>
        <pre
            class="hljs mt-3 max-h-96 overflow-auto bg-transparent text-xs whitespace-pre-wrap text-foreground"
            style="
                font-family:
                    'JetBrains Mono', 'Fira Code', Menlo,
                    monospace;
            "
        ><code v-html="highlightJson(input ?? {})"></code></pre>
        <div
            v-if="dataset || testCase"
            class="mt-3 text-[11px] text-muted-foreground"
            style="
                font-family:
                    'JetBrains Mono', 'Fira Code', Menlo,
                    monospace;
            "
        >
            // Dataset: {{ dataset?.name || '—' }} • Test case: {{ testCase?.name || '—' }}
        </div>
    </div>
</template>
