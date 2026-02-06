<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import agentRoutes from '@/routes/projects/agents';
import { Link } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import ChatUI from '@/components/chat/ChatUI.vue';
import {
    Bot,
    Settings,
    ChevronRight,
    Edit3,
} from 'lucide-vue-next';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface Conversation {
    id: number;
    type: 'idea' | 'run_feedback' | 'agent_chat';
    status: string;
    title: string | null;
    message_count: number;
    created_at: string;
    updated_at: string;
}

interface AgentPayload {
    id: number;
    name: string;
    description: string | null;
    system_prompt_content: string;
    model_name: string;
    temperature: number | null;
    max_tokens: number | null;
    max_context_messages: number;
    is_active: boolean;
    total_messages: number;
}

interface Props {
    project: ProjectPayload;
    agent: AgentPayload;
    conversations: Conversation[];
}

const props = defineProps<Props>();
const isInfoPanelVisible = ref(true);
const agentStats = reactive({
    total_messages: props.agent.total_messages,
});

const conversationsList = ref<Conversation[]>([...props.conversations]);

watch(
    () => props.conversations,
    (newConversations) => {
        conversationsList.value = [...newConversations];
    },
    { deep: true },
);

const handleConversationCreated = (conversation: {
    id: number;
    type: 'idea' | 'run_feedback' | 'agent_chat';
    status: string;
    created_at: string;
    updated_at: string;
}) => {
    conversationsList.value = [
        {
            ...conversation,
            title: null,
            message_count: 0,
        },
        ...conversationsList.value,
    ];
};

const handleConversationDeleted = (conversationId: number) => {
    const conversation = conversationsList.value.find((item) => item.id === conversationId);
    const messageCount = conversation?.message_count ?? 0;

    conversationsList.value = conversationsList.value.filter(
        (item) => item.id !== conversationId,
    );

    if (messageCount > 0) {
        agentStats.total_messages = Math.max(0, agentStats.total_messages - messageCount);
    }
};
</script>

<template>
    <ProjectLayout :project="project" :title-suffix="`Agent - ${agent.name}`">
        <div class="flex flex-col h-[calc(100vh-6rem)] -mx-4 -my-4">
            <div class="flex flex-1 overflow-hidden">
                <div class="flex-1 min-w-0">
                    <ChatUI
                        :project-uuid="project.uuid"
                        type="agent_chat"
                        :agent-id="agent.id"
                        :active="true"
                        :title="agent.name"
                        :welcome="`Chat with ${agent.name}. ${agent.description || ''}`"
                        placeholder="Type your message..."
                        :show-header="true"
                        :show-welcome="true"
                        :show-history="true"
                        :conversations="conversationsList"
                        :agent-name="agent.name"
                        :agent-model="agent.model_name"
                        :agent-description="agent.description"
                        :agent-temperature="agent.temperature"
                        :agent-max-tokens="agent.max_tokens"
                        :agent-max-context-messages="agent.max_context_messages"
                        :agent-message-count="agentStats.total_messages"
                        :agent-system-prompt="agent.system_prompt_content"
                        @conversation-created="handleConversationCreated"
                        @conversation-deleted="handleConversationDeleted"
                    />
                </div>

                <div
                    class="border-l border-border bg-muted/20 transition-all duration-300"
                    :class="isInfoPanelVisible ? 'w-72' : 'w-14'"
                >
                    <button
                        type="button"
                        class="w-full border-b border-border p-3 transition-colors hover:bg-muted/50"
                        :aria-expanded="isInfoPanelVisible"
                        aria-controls="agent-info-panel"
                        aria-label="Toggle agent info panel"
                        @click="isInfoPanelVisible = !isInfoPanelVisible"
                    >
                        <div
                            class="flex items-center justify-between"
                            :class="isInfoPanelVisible ? '' : 'justify-center'"
                        >
                            <div v-if="isInfoPanelVisible" class="flex items-center gap-2">
                                <Settings class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm font-medium">Agent Info</span>
                            </div>
                            <ChevronRight
                                class="h-4 w-4 text-muted-foreground transition-transform duration-300"
                                :class="isInfoPanelVisible ? 'rotate-180' : ''"
                            />
                        </div>
                    </button>

                    <div v-if="isInfoPanelVisible" id="agent-info-panel">
                        <div class="p-4 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                    <Bot class="h-5 w-5 text-primary" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-foreground truncate">{{ agent.name }}</h3>
                                    <p class="text-xs text-muted-foreground">{{ agent.model_name }}</p>
                                </div>
                            </div>

                            <div v-if="agent.description" class="text-sm text-muted-foreground">
                                {{ agent.description }}
                            </div>

                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Temperature</span>
                                    <span class="font-medium">{{ agent.temperature ?? 'Default' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Max Tokens</span>
                                    <span class="font-medium">{{ agent.max_tokens || 'Default' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Context Limit</span>
                                    <span class="font-medium">{{ agent.max_context_messages }} msgs</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Total Messages</span>
                                    <span class="font-medium">{{ agentStats.total_messages }}</span>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-border">
                                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                                    System Prompt
                                </p>
                                <div class="max-h-32 overflow-y-auto rounded-md bg-muted p-2 text-xs text-muted-foreground font-mono">
                                    {{ agent.system_prompt_content }}
                                </div>
                            </div>

                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="w-full"
                                as-child
                            >
                                <Link :href="agentRoutes.edit({ project: project.uuid, agent: agent.id }).url">
                                    <Edit3 class="h-4 w-4 mr-2" />
                                    Edit Agent
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ProjectLayout>
</template>
