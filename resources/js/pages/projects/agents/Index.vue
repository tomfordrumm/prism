<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import agentRoutes from '@/routes/projects/agents';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Bot, MessageSquare, Activity } from 'lucide-vue-next';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface AgentListItem {
    id: number;
    name: string;
    description?: string | null;
    model_name: string;
    is_active: boolean;
    last_used_at: string | null;
    conversations_count: number;
    total_conversations: number;
    total_messages: number;
}

interface Props {
    project: ProjectPayload;
    agents: AgentListItem[];
}

defineProps<Props>();

const formatRelativeTime = (dateString: string | null) => {
    if (!dateString) return 'Never';
    
    const date = new Date(dateString);
    const now = new Date();
    const diffSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    
    if (diffSeconds < 60) return 'Just now';
    const minutes = Math.floor(diffSeconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString();
};
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Agents">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-foreground">Agents</h2>
                <p class="text-sm text-muted-foreground">
                    AI assistants with custom system prompts for interactive conversations.
                </p>
            </div>
            <Button as-child>
                <Link :href="agentRoutes.create({ project: project.uuid }).url">New Agent</Link>
            </Button>
        </div>

        <div
            v-if="agents.length === 0"
            class="mt-4 rounded-lg border border-border bg-card p-8 text-center"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                <Bot class="h-6 w-6 text-primary" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-foreground">No agents yet</h3>
            <p class="mt-2 text-sm text-muted-foreground">
                Create your first agent to start having interactive conversations with AI.
            </p>
            <Button class="mt-4" as-child>
                <Link :href="agentRoutes.create({ project: project.uuid }).url">Create Agent</Link>
            </Button>
        </div>

        <div v-else class="mt-4 grid gap-4 lg:grid-cols-2">
            <Link
                v-for="agent in agents"
                :key="agent.id"
                :href="agentRoutes.show({ project: project.uuid, agent: agent.id }).url"
                class="group block rounded-lg border border-border bg-card p-5 transition hover:border-primary hover:shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                            <Bot class="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-foreground group-hover:text-primary transition">
                                {{ agent.name }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{ agent.model_name }}
                            </p>
                        </div>
                    </div>
                    <span
                        v-if="!agent.is_active"
                        class="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground"
                    >
                        Inactive
                    </span>
                </div>
                
                <p class="mt-3 text-sm text-muted-foreground line-clamp-2">
                    {{ agent.description || 'No description provided' }}
                </p>
                
                <div class="mt-4 flex items-center gap-4 text-xs text-muted-foreground">
                    <div class="flex items-center gap-1">
                        <MessageSquare class="h-3.5 w-3.5" />
                        <span>{{ agent.conversations_count }} conversations</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Activity class="h-3.5 w-3.5" />
                        <span>{{ agent.total_messages }} messages</span>
                    </div>
                    <div class="ml-auto">
                        Last used: {{ formatRelativeTime(agent.last_used_at) }}
                    </div>
                </div>
            </Link>
        </div>
    </ProjectLayout>
</template>
