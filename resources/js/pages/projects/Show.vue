<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import runRoutes from '@/routes/projects/runs';
import { Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Icon from '@/components/Icon.vue';
import Chart from 'primevue/chart';
import ChatUI from '@/components/chat/ChatUI.vue';
import { computed, onMounted, ref, watch } from 'vue';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
    prompt_templates_count?: number;
    chains_count?: number;
    runs_count?: number;
}

interface ChartSeriesPayload {
    labels: string[];
    values: number[];
}

interface ConversationListItem {
    id: number;
    type: 'idea' | 'run_feedback';
    status: string;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    project: ProjectPayload;
    recentActivity: ActivityItem[];
    lastMonthTokens: number;
    promptChart?: ChartSeriesPayload | null;
    tokenChart?: ChartSeriesPayload | null;
}>();

interface ActivityItem {
    id: string;
    type: 'run' | 'prompt' | 'dataset' | 'chain';
    title: string;
    description: string;
    timestamp: string | null;
    href?: string;
    status?: string;
}

const statusClasses: Record<string, string> = {
    success: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-red-100 text-red-700',
    running: 'bg-blue-100 text-blue-700',
    pending: 'bg-amber-100 text-amber-700',
};

const formatRelativeTimestamp = (value: string | null) => {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    const diffSeconds = Math.floor((Date.now() - parsed.getTime()) / 1000);
    if (diffSeconds < 60) return 'just now';
    const minutes = Math.floor(diffSeconds / 60);
    if (minutes < 60) return `${minutes} min ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    const weeks = Math.floor(days / 7);
    if (weeks < 5) return `${weeks}w ago`;
    const months = Math.floor(days / 30);
    if (months < 12) return `${months}mo ago`;
    const years = Math.floor(days / 365);
    return `${years}y ago`;
};

const formatNumber = (value: number) => value.toLocaleString();

const recentActivityPreview = computed(() => props.recentActivity.slice(0, 4));

const activityIcon = (type: ActivityItem['type']) => {
    switch (type) {
        case 'run':
            return 'check';
        case 'prompt':
            return 'pencil';
        case 'dataset':
            return 'database';
        case 'chain':
            return 'git-branch';
        default:
            return 'activity';
    }
};

const fallbackLabels = Array.from({ length: 30 }, (_, index) => `${index + 1}`);

const promptChartLabels = computed(() =>
    props.promptChart?.labels?.length ? props.promptChart.labels : fallbackLabels,
);
const promptChartValues = computed(() =>
    props.promptChart?.values?.length
        ? props.promptChart.values
        : Array.from({ length: promptChartLabels.value.length }, () => 0),
);

const tokenChartLabels = computed(() =>
    props.tokenChart?.labels?.length ? props.tokenChart.labels : fallbackLabels,
);
const tokenChartValues = computed(() =>
    props.tokenChart?.values?.length
        ? props.tokenChart.values
        : Array.from({ length: tokenChartLabels.value.length }, () => 0),
);

const promptChartData = computed(() => ({
    labels: promptChartLabels.value,
    datasets: [
        {
            data: promptChartValues.value,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.18)',
            fill: true,
            tension: 0.45,
            borderWidth: 2,
            pointRadius: 0,
        },
    ],
}));

const promptChartOptions = {
    maintainAspectRatio: false,
    animation: false,
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false },
    },
    scales: {
        x: { display: false },
        y: { display: false },
    },
};

const tokenChartData = computed(() => ({
    labels: tokenChartLabels.value,
    datasets: [
        {
            data: tokenChartValues.value,
            backgroundColor: 'rgba(16, 185, 129, 0.6)',
            borderRadius: 4,
            barThickness: 4,
        },
    ],
}));

const tokenChartOptions = {
    maintainAspectRatio: false,
    animation: false,
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false },
    },
    scales: {
        x: { display: false },
        y: { display: false },
    },
};

const promptIdeaSuggestion = ref<{ suggestion?: string | null; analysis?: string | null } | null>(null);
const conversations = ref<ConversationListItem[]>([]);
const chatContextKey = ref(Date.now());

const savePromptForm = useForm({
    name: '',
    description: '',
    initial_content: '',
    initial_changelog: 'Created from idea',
});

const getCookie = (name: string) =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
        ?.split('=')[1] ?? '';

const fetchConversations = async () => {
    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ||
        decodeURIComponent(getCookie('XSRF-TOKEN'));

    const response = await fetch(
        `/projects/${props.project.uuid}/prompt-conversations?type=idea`,
        {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
        }
    );

    const payload = await response.json().catch(() => null);
    if (payload?.conversations) {
        conversations.value = payload.conversations;
    }
};

const handleChatSuggestion = (payload: { suggestedPrompt?: string | null; analysis?: string | null }) => {
    promptIdeaSuggestion.value = {
        suggestion: payload.suggestedPrompt ?? null,
        analysis: payload.analysis ?? null,
    };
};

const handleNewConversation = () => {
    promptIdeaSuggestion.value = null;
    chatContextKey.value = Date.now();
    // Refresh conversations list to include the new one
    fetchConversations();
};

const handleSelectConversation = () => {
    // Conversation loaded in ChatUI component
    // Reset the suggestion state since we're loading an existing conversation
    promptIdeaSuggestion.value = null;
};

const handleConversationCreated = (conversation: ConversationListItem) => {
    // Add the new conversation to the beginning of the list
    // Check if it already exists to avoid duplicates
    const exists = conversations.value.some(c => c.id === conversation.id);
    if (!exists) {
        conversations.value.unshift(conversation);
    }
};

const submitSavePrompt = () => {
    if (!savePromptForm.initial_content) return;
    savePromptForm.post(`/projects/${props.project.uuid}/prompts`, {
        onSuccess: () => {
            // Refresh conversations after saving
            fetchConversations();
        },
    });
};

watch(
    () => promptIdeaSuggestion.value?.suggestion,
    (suggestion) => {
        if (!suggestion) return;
        savePromptForm.initial_content = suggestion;
        if (!savePromptForm.name) {
            savePromptForm.name = 'Prompt from idea';
        }
    },
    { immediate: true },
);

onMounted(() => {
    fetchConversations();
});
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Overview">
        <div class="flex min-h-[calc(100vh-160px)] flex-col gap-6">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <div class="flex flex-col gap-4">
                    <div class="rounded-2xl border border-border/60 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Total Prompts
                                </p>
                                <p class="mt-2 text-3xl font-semibold text-foreground">
                                    {{ project.prompt_templates_count ?? 0 }}
                                </p>
                            </div>
                            <div class="h-12 w-28">
                                <Chart
                                    type="line"
                                    :data="promptChartData"
                                    :options="promptChartOptions"
                                    class="h-full w-full"
                                />
                            </div>
                        </div>
                        <div class="mt-4 h-px bg-border/60"></div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Trends across all prompt templates.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-border/60 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Token Usage (30d)
                                </p>
                                <p class="mt-2 text-3xl font-semibold text-foreground">
                                    {{ formatNumber(lastMonthTokens) }}
                                </p>
                            </div>
                            <div class="h-12 w-28">
                                <Chart
                                    type="bar"
                                    :data="tokenChartData"
                                    :options="tokenChartOptions"
                                    class="h-full w-full"
                                />
                            </div>
                        </div>
                        <div class="mt-4 h-px bg-border/60"></div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Daily token consumption for the last 30 days.
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-border/60 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-border/60 px-5 py-4">
                        <div>
                            <h2 class="text-sm font-semibold text-foreground">Recent Activity</h2>
                            <p class="text-xs text-muted-foreground">
                                Latest runs, prompt updates, and dataset changes.
                            </p>
                        </div>
                        <Link
                            :href="runRoutes.index({ project: project.uuid }).url"
                            class="text-xs font-semibold text-primary"
                        >
                            View all runs
                        </Link>
                    </div>

                    <div v-if="recentActivityPreview.length === 0" class="p-5 text-sm text-muted-foreground">
                        No activity yet. Create prompts, datasets, or run a chain to see updates here.
                    </div>

                    <div v-else class="divide-y divide-border/60">
                        <div
                            v-for="activity in recentActivityPreview"
                            :key="activity.id"
                            class="flex flex-wrap items-center justify-between gap-4 px-5 py-4"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-4">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full border border-border/60 bg-muted/20 text-muted-foreground">
                                    <Icon :name="activityIcon(activity.type)" class="h-4 w-4" />
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        v-if="activity.href"
                                        :href="activity.href"
                                        class="block truncate text-sm font-semibold text-foreground hover:text-primary"
                                    >
                                        {{ activity.title }}
                                    </Link>
                                    <p v-else class="text-sm font-semibold text-foreground">
                                        {{ activity.title }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ activity.description }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                <span
                                    v-if="activity.type === 'run' && activity.status"
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold"
                                    :class="statusClasses[activity.status] || 'bg-muted text-muted-foreground'"
                                >
                                    <Icon
                                        v-if="activity.status === 'success'"
                                        name="check"
                                        class="h-3 w-3 text-emerald-600"
                                    />
                                    {{ activity.status.toUpperCase() }}
                                </span>
                                <span>{{ formatRelativeTimestamp(activity.timestamp) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-1 flex-col gap-8 min-h-[600px]">
                <ChatUI
                    class="flex-1 rounded-2xl border border-border/60 bg-white shadow-sm overflow-hidden"
                    :project-uuid="project.uuid"
                    type="idea"
                    :title="'Improve your thoughts into a prompt'"
                    :welcome="'Share your raw idea and I will convert it into a clean, ready-to-run prompt.'"
                    placeholder="Ask something..."
                    max-width-class="max-w-full"
                    :show-history="true"
                    :conversations="conversations"
                    :context-key="chatContextKey"
                    @suggestion="handleChatSuggestion"
                    @new-conversation="handleNewConversation"
                    @select-conversation="handleSelectConversation"
                    @conversation-created="handleConversationCreated"
                >
                    <template #save-actions="{ suggestedPrompt }">
                        <div class="grid gap-3">
                            <div class="grid gap-2">
                                <label
                                    for="prompt_idea_name"
                                    class="text-xs font-semibold uppercase text-muted-foreground"
                                >
                                    Prompt name
                                </label>
                                <Input
                                    id="prompt_idea_name"
                                    v-model="savePromptForm.name"
                                    placeholder="e.g. Idea prompt"
                                />
                                <p v-if="savePromptForm.errors.name" class="text-xs text-red-600">
                                    {{ savePromptForm.errors.name }}
                                </p>
                            </div>
                            <div class="flex justify-end">
                                <Button :disabled="savePromptForm.processing" @click="submitSavePrompt">
                                    Save as prompt
                                </Button>
                            </div>
                        </div>
                    </template>
                </ChatUI>
            </div>

        </div>
    </ProjectLayout>
</template>
