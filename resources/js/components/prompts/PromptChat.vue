<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import Icon from '@/components/Icon.vue';

type ChatRole = 'user' | 'assistant' | 'system';

type ChatMessage = {
    id: number | string;
    role: ChatRole;
    content: string;
    meta?: Record<string, unknown> | null;
    created_at?: string | null;
};

interface Props {
    projectUuid: string;
    type: 'idea' | 'run_feedback';
    runId?: number | null;
    runStepId?: number | null;
    targetPromptVersionId?: number | null;
    active?: boolean;
    title?: string;
    welcome?: string;
    placeholder?: string;
    maxWidthClass?: string;
    showHeader?: boolean;
    showWelcome?: boolean;
    contextKey?: string | number | null;
}

const props = withDefaults(defineProps<Props>(), {
    active: true,
    title: 'Improve your thoughts into a prompt',
    welcome: 'Share your raw idea and I’ll convert it into a clean, ready-to-run prompt.',
    placeholder: 'Спросите что-нибудь...',
    maxWidthClass: 'max-w-5xl',
    showHeader: true,
    showWelcome: true,
    contextKey: null,
});

const emit = defineEmits<{
    (event: 'suggestion', payload: { suggestedPrompt?: string | null; analysis?: string | null }): void;
}>();

const conversationId = ref<number | null>(null);
const messages = ref<ChatMessage[]>([]);
const input = ref('');
const isSending = ref(false);
const isBootstrapping = ref(false);
const lastContextKey = ref<string | number | null>(props.contextKey);

const hasConversation = computed(() => messages.value.length > 0 || isSending.value);
const hasInput = computed(() => input.value.trim().length > 0);

const ideaInputRef = ref<HTMLTextAreaElement | null>(null);
const autoResizeInput = () => {
    const el = ideaInputRef.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 180)}px`;
};

watch(
    () => input.value,
    () => nextTick(autoResizeInput),
    { immediate: true },
);

const getCookie = (name: string) =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
        ?.split('=')[1] ?? '';

const ensureConversation = async () => {
    if (conversationId.value || isBootstrapping.value) return;
    isBootstrapping.value = true;

    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ||
        decodeURIComponent(getCookie('XSRF-TOKEN'));

    const response = await fetch(`/projects/${props.projectUuid}/prompt-conversations`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            type: props.type,
            run_id: props.runId ?? null,
            run_step_id: props.runStepId ?? null,
            target_prompt_version_id: props.targetPromptVersionId ?? null,
        }),
    });

    const payload = await response.json().catch(() => null);
    conversationId.value = payload?.conversation?.id ?? null;
    messages.value = Array.isArray(payload?.messages) ? payload.messages : [];
    isBootstrapping.value = false;
};

const sendMessage = async () => {
    if (!hasInput.value || isSending.value) return;
    await ensureConversation();
    if (!conversationId.value) return;

    const content = input.value.trim();
    input.value = '';
    nextTick(autoResizeInput);

    const tempId = `local-${Date.now()}`;
    messages.value = [
        ...messages.value,
        { id: tempId, role: 'user', content },
    ];

    isSending.value = true;

    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ||
        decodeURIComponent(getCookie('XSRF-TOKEN'));

    try {
        const response = await fetch(
            `/projects/${props.projectUuid}/prompt-conversations/${conversationId.value}/messages`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    content,
                }),
            },
        );

        const payload = await response.json().catch(() => null);
        const userMessage = payload?.user_message;
        const assistantMessage = payload?.assistant_message;

        messages.value = messages.value.filter((message) => message.id !== tempId);
        if (userMessage) {
            messages.value = [...messages.value, userMessage];
        }
        if (assistantMessage) {
            messages.value = [...messages.value, assistantMessage];
        }

        const suggestedPrompt = assistantMessage?.meta?.suggested_prompt;
        const analysis = assistantMessage?.meta?.analysis;
        if (suggestedPrompt || analysis) {
            emit('suggestion', {
                suggestedPrompt: suggestedPrompt ?? null,
                analysis: analysis ?? null,
            });
        }
    } finally {
        isSending.value = false;
    }
};

const resetConversation = () => {
    conversationId.value = null;
    messages.value = [];
    input.value = '';
    nextTick(autoResizeInput);
};

watch(
    () => props.active,
    (active) => {
        if (active) {
            ensureConversation();
        }
    },
    { immediate: true },
);

watch(
    () => props.contextKey,
    (nextKey) => {
        if (nextKey === lastContextKey.value) return;
        lastContextKey.value = nextKey ?? null;
        resetConversation();
        if (props.active) {
            ensureConversation();
        }
    },
);
</script>

<template>
    <div class="flex h-full flex-col gap-6">
        <div
            v-if="showHeader"
            class="space-y-4 transition-all duration-300"
            :class="
                showWelcome && hasConversation
                    ? 'pointer-events-none opacity-0 -translate-y-3 max-h-0 overflow-hidden'
                    : 'opacity-100'
            "
        >
            <div class="flex items-center gap-2">
                <Icon name="sparkles" class="h-5 w-5 text-primary" />
                <h2 class="text-2xl font-semibold text-foreground">{{ title }}</h2>
            </div>
            <div v-if="showWelcome" class="flex items-start gap-3">
                <div class="mt-1 flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Icon name="sparkles" class="h-4 w-4" />
                </div>
                <div class="rounded-2xl rounded-tl-sm bg-slate-50 px-4 py-2 text-sm text-foreground">
                    {{ welcome }}
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto pr-1">
            <div class="mx-auto flex w-full flex-col gap-4" :class="maxWidthClass">
                <div v-for="message in messages" :key="message.id" class="flex items-start gap-3">
                    <div
                        v-if="message.role !== 'user'"
                        class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Icon name="sparkles" class="h-4 w-4" />
                    </div>
                    <div
                        class="rounded-2xl px-4 py-2 text-sm text-foreground"
                        :class="
                            message.role === 'user'
                                ? 'ml-auto rounded-tr-sm bg-primary/10'
                                : 'rounded-tl-sm bg-slate-50'
                        "
                    >
                        <div class="whitespace-pre-wrap">{{ message.content }}</div>
                    </div>
                    <div
                        v-if="message.role === 'user'"
                        class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-muted text-muted-foreground"
                    >
                        <Icon name="user" class="h-4 w-4" />
                    </div>
                </div>

                <div v-if="isSending" class="flex items-start gap-3">
                    <div class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <Icon name="sparkles" class="h-4 w-4" />
                    </div>
                    <div class="rounded-2xl rounded-tl-sm bg-slate-50 px-4 py-2 text-sm text-muted-foreground">
                        Analyzing your idea...
                    </div>
                </div>
            </div>
        </div>

        <div v-if="$slots['after-messages']" class="mx-auto w-full" :class="maxWidthClass">
            <slot name="after-messages"></slot>
        </div>

        <div class="mt-auto pb-2">
            <form
                class="mx-auto w-full rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm focus-within:border-slate-300 focus-within:ring-2 focus-within:ring-primary/10"
                :class="maxWidthClass"
                @submit.prevent="sendMessage"
            >
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-400 transition hover:text-primary"
                        aria-label="Attach file"
                    >
                        <Icon name="plus" class="h-4 w-4" />
                    </button>
                    <textarea
                        id="prompt_idea_input"
                        ref="ideaInputRef"
                        v-model="input"
                        rows="1"
                        class="min-h-[28px] w-full resize-none bg-transparent text-sm text-foreground outline-none placeholder:text-slate-400 focus:outline-none"
                        :placeholder="placeholder"
                        @input="autoResizeInput"
                    ></textarea>
                    <button
                        type="submit"
                        :disabled="isSending"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full transition disabled:opacity-60"
                        :class="hasInput ? 'text-primary' : 'text-slate-400 hover:text-primary'"
                        aria-label="Send message"
                    >
                        <Icon name="send" class="h-4 w-4" />
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
