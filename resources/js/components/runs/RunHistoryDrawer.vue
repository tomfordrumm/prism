<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Drawer from 'primevue/drawer';
import runsRoutes from '@/routes/projects/runs';
import Icon from '@/components/Icon.vue';
import {
    durationHuman,
    formatFullTimestamp,
    relativeTime,
    totalTokens,
} from '@/composables/useRunFormatters';
import type { RunHistoryItem } from '@/types/runs';

interface Props {
    open: boolean;
    entries: RunHistoryItem[];
    currentRunId: number;
    projectUuid: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const hasPreviousRuns = () =>
    props.entries.some((entry) => entry.id !== props.currentRunId);

const statusBadgeClass = (status: string) =>
    status === 'success'
        ? 'bg-emerald-100 text-emerald-700'
        : status === 'failed'
          ? 'bg-red-100 text-red-700'
          : 'bg-amber-100 text-amber-800';
</script>

<template>
    <Drawer
        :visible="open"
        position="left"
        :style="{ width: '380px' }"
        header="Run History"
        :modal="true"
        showCloseIcon
        @update:visible="emit('update:open', $event)"
    >
        <div class="flex flex-col gap-3">
            <div v-if="entries.length === 0" class="text-sm text-muted-foreground">
                No runs yet.
            </div>

            <div v-else class="space-y-3">
                <div
                    v-if="!hasPreviousRuns()"
                    class="rounded-md border border-dashed border-border p-3 text-sm text-muted-foreground"
                >
                    No previous runs found. This is the first run for this flow.
                </div>
                <Link
                    v-for="entry in entries"
                    :key="entry.id"
                    :href="
                        entry.href ||
                        runsRoutes.show({
                            project: projectUuid,
                            run: entry.id,
                        }).url
                    "
                    class="block rounded-lg border border-border bg-card p-3 transition hover:border-primary"
                    :class="entry.id === currentRunId ? 'border-primary bg-primary/5' : ''"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-semibold text-foreground">#{{ entry.id }}</span>
                                <span class="rounded-md px-2 py-1 text-[11px] font-semibold" :class="statusBadgeClass(entry.status)">
                                    {{ entry.status.toUpperCase() }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ relativeTime(entry.created_at) }} · {{ formatFullTimestamp(entry.created_at) }}
                            </p>
                        </div>
                        <span
                            v-if="entry.id === currentRunId"
                            class="rounded-full bg-primary/10 px-2 py-1 text-[10px] font-semibold text-primary"
                        >
                            You are here
                        </span>
                    </div>

                    <p v-if="entry.final_snippet" class="mt-2 line-clamp-2 text-sm text-muted-foreground">
                        {{ entry.final_snippet }}
                    </p>

                    <div class="mt-3 flex items-center gap-4 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1">
                            <Icon name="clock" class="h-3.5 w-3.5" />
                            {{ durationHuman(entry.duration_ms) }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <Icon name="cpu" class="h-3.5 w-3.5" />
                            {{ totalTokens(entry) }} tokens
                        </span>
                    </div>
                </Link>
            </div>
        </div>
    </Drawer>
</template>
