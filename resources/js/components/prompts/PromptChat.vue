<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import Icon from '@/components/Icon.vue';
import Button from 'primevue/button';
import { usePromptDiff } from '@/composables/usePromptDiff';

type ChatRole = 'user' | 'assistant' | 'system';

type ChatMessage = {
    id: number | string;
    role: ChatRole;
    content: string;
    meta?: Record<string, unknown> | null;
    created_at?: string | null;
};

type ConversationListItem = {
    id: number;
    type: 'idea' | 'run_feedback';
    status: string;
    created_at: string;
    updated_at: string;
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
    // New props for enhanced functionality
    showHistory?: boolean;
    conversations?: ConversationListItem[];
    originalPromptContent?: string;
}

const props = withDefaults(defineProps<Props>(), {
    active: true,
    title: 'Improve your thoughts into a prompt',
    welcome: 'Share your raw idea and I will convert it into a clean, ready-to-run prompt.',
    placeholder: 'Ask something...',
    maxWidthClass: 'max-w-5xl',
    showHeader: true,
    showWelcome: true,
    contextKey: null,
    showHistory: false,
    conversations: () => [],
    originalPromptContent: '',
});

const emit = defineEmits<{
    (event: 'suggestion', payload: { suggestedPrompt?: string | null; analysis?: string | null }): void;
    (event: 'select-conversation', conversationId: number): void;
    (event: 'new-conversation'): void;
    (event: 'conversation-created', conversation: { id: number; type: 'idea' | 'run_feedback'; status: string; created_at: string; updated_at: string }): void;
}>();

const conversationId = ref<number | null>(null);
const messages = ref<ChatMessage[]>([]);
const input = ref('');
const isSending = ref(false);
const isBootstrapping = ref(false);
const lastContextKey = ref<string | number | null>(props.contextKey);
const isHistoryCollapsed = ref(false);

// Track the original content for diff comparison
const originalContentForDiff = ref(props.originalPromptContent);
const suggestedPromptContent = ref('');

const { viewMode, diffLines, diffLineSymbol, hasSuggestion, setViewMode } = usePromptDiff(
    computed(() => originalContentForDiff.value),
    computed(() => suggestedPromptContent.value)
);

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

const ensureConversation = async (forceNew = false) => {
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
            force_new: forceNew,
        }),
    });

    const payload = await response.json().catch(() => null);
    conversationId.value = payload?.conversation?.id ?? null;
    messages.value = Array.isArray(payload?.messages) ? payload.messages : [];
    
    // Emit event when a new conversation is created so parent can update the list
    if (payload?.conversation) {
        emit('conversation-created', payload.conversation);
    }
    
    isBootstrapping.value = false;
};

const loadConversation = async (id: number) => {
    if (isBootstrapping.value) return;
    isBootstrapping.value = true;

    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ||
        decodeURIComponent(getCookie('XSRF-TOKEN'));

    const response = await fetch(`/projects/${props.projectUuid}/prompt-conversations/${id}`, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
        },
        credentials: 'same-origin',
    });

    const payload = await response.json().catch(() => null);
    conversationId.value = payload?.conversation?.id ?? null;
    messages.value = Array.isArray(payload?.messages) ? payload.messages : [];
    
    // Reset diff state when loading a conversation
    suggestedPromptContent.value = '';
    originalContentForDiff.value = '';
    
    // Find all assistant messages with suggestions
    const assistantMessages = messages.value.filter(m => m.role === 'assistant' && m.meta?.suggested_prompt);
    
    if (assistantMessages.length > 0) {
        // Last suggestion is the current one
        const lastAssistantMessage = assistantMessages[assistantMessages.length - 1];
        suggestedPromptContent.value = lastAssistantMessage.meta?.suggested_prompt as string;
        
        // First suggestion is the baseline for comparison
        const firstAssistantMessage = assistantMessages[0];
        originalContentForDiff.value = firstAssistantMessage.meta?.suggested_prompt as string;
    }
    
    isBootstrapping.value = false;
    emit('select-conversation', id);
};

const isCreatingNewConversation = ref(false);

const createNewConversation = () => {
    isCreatingNewConversation.value = true;
    resetConversation();
    emit('new-conversation');
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
        
        if (suggestedPrompt) {
            // If this is the first suggestion, set it as the original for future diffs
            if (!originalContentForDiff.value) {
                originalContentForDiff.value = suggestedPrompt as string;
            } else {
                // For subsequent suggestions, the previous suggestion becomes the "original"
                originalContentForDiff.value = suggestedPromptContent.value || suggestedPrompt as string;
            }
            suggestedPromptContent.value = suggestedPrompt as string;
        }
        
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
    suggestedPromptContent.value = '';
    originalContentForDiff.value = props.originalPromptContent;
    nextTick(autoResizeInput);
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const isActiveConversation = (id: number) => {
    return conversationId.value === id;
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
            // If we're creating a new conversation, force create it
            ensureConversation(isCreatingNewConversation.value);
            // Reset the flag after creating
            isCreatingNewConversation.value = false;
        }
    },
);

watch(
    () => props.originalPromptContent,
    (newContent) => {
        originalContentForDiff.value = newContent;
    },
);
</script>

<template>
    <div class="flex h-full">
        <!-- History Sidebar -->
        <div
            v-if="showHistory && props.type === 'idea'"
            class="flex flex-col border-r bg-gray-50 transition-all duration-300"
            :class="isHistoryCollapsed ? 'w-12' : 'w-64'"
        >
            <!-- Toggle Button -->
            <button
                type="button"
                class="flex items-center justify-center p-3 hover:bg-gray-100 transition"
                @click="isHistoryCollapsed = !isHistoryCollapsed"
            >
                <Icon 
                    :name="isHistoryCollapsed ? 'panel-right-open' : 'panel-left-close'" 
                    class="h-4 w-4 text-muted-foreground" 
                />
            </button>

            <!-- Sidebar Content -->
            <div v-if="!isHistoryCollapsed" class="flex flex-col flex-1 min-h-0">
                <!-- New Chat Button -->
                <div class="p-3 border-b">
                    <Button
                        size="small"
                        class="w-full"
                        @click="createNewConversation"
                    >
                        <Icon name="plus" class="h-4 w-4 mr-2" />
                        New Chat
                    </Button>
                </div>

                <!-- Conversations List -->
                <div class="flex-1 overflow-y-auto p-2 space-y-1">
                    <div
                        v-for="conversation in props.conversations"
                        :key="conversation.id"
                        class="cursor-pointer rounded-lg px-3 py-2 text-sm transition"
                        :class="isActiveConversation(conversation.id) 
                            ? 'bg-primary/10 text-primary font-medium' 
                            : 'hover:bg-gray-100 text-muted-foreground'"
                        @click="loadConversation(conversation.id)"
                    >
                        <div class="truncate">
                            {{ formatDate(conversation.updated_at) }}
                        </div>
                    </div>
                    <div v-if="props.conversations.length === 0" class="px-3 py-4 text-xs text-muted-foreground text-center">
                        No previous conversations
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex flex-col flex-1 min-w-0">
            <div class="flex h-full">
                <!-- Chat Section -->
                <div 
                    class="flex flex-col gap-6 transition-all duration-300"
                    :class="hasSuggestion ? 'w-[60%]' : 'w-full'"
                >
                    <div
                        v-if="showHeader"
                        class="space-y-4 transition-all duration-300 px-6 pt-6"
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

                    <div class="flex-1 overflow-y-auto pr-1 px-6">
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

                    <div v-if="$slots['after-messages']" class="mx-auto w-full px-6" :class="maxWidthClass">
                        <slot name="after-messages"></slot>
                    </div>

                    <div class="mt-auto pb-6 px-6">
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

                <!-- Diff Panel (Right Side) -->
                <div
                    v-if="hasSuggestion"
                    class="flex w-[40%] flex-col border-l bg-white"
                >
                    <div class="flex flex-none items-center justify-between border-b px-4 py-3">
                        <div class="text-sm font-semibold text-foreground">Generated Prompt</div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="small"
                                :class="viewMode === 'diff' ? 'border-primary text-primary' : ''"
                                @click="setViewMode('diff')"
                            >
                                Diff
                            </Button>
                            <Button
                                variant="outline"
                                size="small"
                                :class="viewMode === 'final' ? 'border-primary text-primary' : ''"
                                @click="setViewMode('final')"
                            >
                                Final
                            </Button>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto p-4 font-mono text-sm">
                        <div v-if="viewMode === 'final'">
                            <pre class="whitespace-pre-wrap">{{ suggestedPromptContent }}</pre>
                        </div>
                        <div v-else class="space-y-1">
                            <div
                                v-for="(line, idx) in diffLines"
                                :key="`diff-${idx}`"
                                class="px-2 py-1"
                                :class="line.type === 'add' ? 'bg-emerald-50 text-emerald-800' : line.type === 'remove' ? 'bg-red-50 text-red-800' : 'text-foreground'"
                            >
                                <span class="text-muted-foreground">{{ diffLineSymbol(line.type) }}</span>
                                <span class="ml-2">{{ line.text }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Save Actions Slot -->
                    <div v-if="$slots['save-actions']" class="border-t p-4">
                        <slot name="save-actions" :suggested-prompt="suggestedPromptContent"></slot>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
