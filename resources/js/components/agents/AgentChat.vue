<script setup lang="ts">
/**
 * @deprecated This component has been consolidated into ChatUI.vue.
 * Use ChatUI with type="agent_chat" instead.
 * This file is kept for reference but should be removed in a future cleanup.
 */
import { ref, computed, nextTick, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useToast } from 'primevue/usetoast';
import {
    Send,
    User,
    Bot,
    Loader2,
} from 'lucide-vue-next';
import agentRoutes from '@/routes/projects/agents';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
}

interface AgentPayload {
    id: number;
    name: string;
    model_name: string;
}

interface Message {
    id: number;
    role: 'user' | 'assistant' | 'system';
    content: string;
    created_at: string;
    is_streaming?: boolean;
}

interface Conversation {
    id: number;
    messages: Message[];
}

interface Props {
    project: ProjectPayload;
    agent: AgentPayload;
    conversation: Conversation;
    contextWarning?: {
        current_messages: number;
        max_messages: number;
        will_truncate: boolean;
    } | null;
}

const props = defineProps<Props>();
const toast = useToast();

const getCookie = (name: string): string | undefined => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop()?.split(';').shift();
};

const messagesContainer = ref<HTMLDivElement | null>(null);
const messageInput = ref('');
const isSending = ref(false);

const messages = computed(() => {
    return props.conversation.messages;
});

const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

watch(() => props.conversation.messages, () => {
    scrollToBottom();
}, { deep: true, immediate: true });

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString(undefined, { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
};

const emit = defineEmits<{
    (e: 'message-sent', messages: Message[]): void;
}>();

const sendMessage = async () => {
    if (!messageInput.value.trim() || isSending.value) {
        return;
    }

    const content = messageInput.value.trim();
    messageInput.value = '';
    isSending.value = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         getCookie('XSRF-TOKEN');
        
        const response = await fetch(
            agentRoutes.conversations.messages.store({
                project: props.project.uuid,
                agent: props.agent.id,
                conversation: props.conversation.id,
            }).url,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(csrfToken) } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ content }),
            }
        );
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || 'Failed to send message');
        }
        
        const data = await response.json();
        
        // Emit the new messages to parent
        const newMessages: Message[] = [];
        if (data.user_message) {
            newMessages.push({
                id: data.user_message.id,
                role: data.user_message.role,
                content: data.user_message.content,
                created_at: data.user_message.created_at,
            });
        }
        if (data.assistant_message) {
            newMessages.push({
                id: data.assistant_message.id,
                role: data.assistant_message.role,
                content: data.assistant_message.content,
                created_at: data.assistant_message.created_at,
            });
        }
        
        emit('message-sent', newMessages);
        isSending.value = false;
        scrollToBottom();
        
        // Show context limit warning if applicable
        if (data.approaching_context_limit) {
            toast.add({
                severity: 'warn',
                summary: 'Context Limit',
                detail: 'Approaching conversation context limit. Consider starting a new conversation.',
                life: 5000,
            });
        }
    } catch (error) {
        isSending.value = false;
        const errorMessage = error instanceof Error ? error.message : 'Failed to send message. Please try again.';
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: errorMessage,
            life: 4000,
        });
    }
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
};

const getMessageStyles = (role: string) => {
    switch (role) {
        case 'user':
            return 'bg-primary text-primary-foreground ml-auto';
        case 'assistant':
            return 'bg-muted border border-border mr-auto';
        case 'system':
            return 'bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 mx-auto text-amber-800 dark:text-amber-200 text-sm';
        default:
            return 'bg-muted border border-border';
    }
};

const getMessageIcon = (role: string) => {
    switch (role) {
        case 'user':
            return User;
        case 'assistant':
            return Bot;
        default:
            return null;
    }
};
</script>

<template>
    <div class="flex flex-col h-full">
        <div 
            ref="messagesContainer"
            class="flex-1 overflow-y-auto p-4 space-y-4"
        >
            <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-muted-foreground">
                <Bot class="h-12 w-12 mb-4 opacity-50" />
                <p class="text-lg font-medium">Start a conversation</p>
                <p class="text-sm mt-1">Send a message to begin chatting with {{ agent.name }}</p>
            </div>

            <template v-else>
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex gap-3"
                    :class="message.role === 'user' ? 'flex-row-reverse' : 'flex-row'"
                >
                    <div 
                        v-if="getMessageIcon(message.role)"
                        class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                        :class="message.role === 'user' ? 'bg-primary/10' : 'bg-muted'"
                    >
                        <component 
                            :is="getMessageIcon(message.role)" 
                            class="h-4 w-4"
                            :class="message.role === 'user' ? 'text-primary' : 'text-muted-foreground'"
                        />
                    </div>
                    
                    <Card 
                        class="max-w-[80%] shadow-none"
                        :class="getMessageStyles(message.role)"
                    >
                        <CardContent class="p-3">
                            <div class="whitespace-pre-wrap text-sm leading-relaxed">
                                {{ message.content }}
                            </div>
                            <div 
                                class="text-xs mt-2 opacity-70"
                                :class="message.role === 'user' ? 'text-right' : 'text-left'"
                            >
                                {{ formatTime(message.created_at) }}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div v-if="isSending" class="flex gap-3">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-muted flex items-center justify-center">
                        <Bot class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <Card class="max-w-[80%] shadow-none bg-muted border border-border mr-auto">
                        <CardContent class="p-3">
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <Loader2 class="h-4 w-4 animate-spin" />
                                <span>Thinking...</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </div>

        <div class="border-t border-border p-4 bg-background">
            <div class="flex gap-2 max-w-4xl mx-auto">
                <div class="flex-1 relative">
                    <textarea
                        v-model="messageInput"
                        rows="1"
                        placeholder="Type your message..."
                        class="w-full rounded-md border border-input bg-background px-3 py-2 pr-10 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary resize-none"
                        :disabled="isSending"
                        @keydown="handleKeydown"
                        @input="($event.target as HTMLTextAreaElement).style.height = 'auto'; ($event.target as HTMLTextAreaElement).style.height = ($event.target as HTMLTextAreaElement).scrollHeight + 'px'"
                    ></textarea>
                </div>
                <Button 
                    :disabled="!messageInput.trim() || isSending"
                    @click="sendMessage"
                >
                    <Send class="h-4 w-4" />
                </Button>
            </div>
            <p class="text-xs text-muted-foreground text-center mt-2">
                Press Enter to send, Shift+Enter for new line
            </p>
        </div>
    </div>
</template>

<style scoped>
textarea {
    min-height: 40px;
    max-height: 200px;
}

:deep(.p-toast) {
    z-index: 9999;
}
</style>
