<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import runsRoutes from '@/routes/projects/runs';
import Icon from '@/components/Icon.vue';
import { Spinner } from '@/components/ui/spinner';
import { durationHuman, formatCost, formatTimestamp } from '@/composables/useRunFormatters';
import type { RunPayload } from '@/types/runs';

interface Props {
    projectUuid: string;
    run: RunPayload;
    tokenUsageLabel: string;
    isLive: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (event: 'open-history'): void;
}>();

const statusBadgeClass = (status: string) =>
    status === 'success'
        ? 'bg-emerald-100 text-emerald-700'
        : status === 'failed'
          ? 'bg-red-100 text-red-700'
          : 'bg-amber-100 text-amber-800';
</script>

<template>
    <div class="flex h-12 items-center gap-4 border-b border-border/60 px-4 text-sm">
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md px-2 py-1 text-base font-semibold text-foreground transition hover:bg-muted/60 hover:text-primary"
                @click="emit('open-history')"
            >
                <Icon name="history" class="h-4 w-4" />
                <span>Run #{{ run.id }}</span>
                <Icon name="chevronDown" class="h-4 w-4 text-muted-foreground" />
            </button>
            <span class="text-sm text-muted-foreground">
                {{ run.chain_label || run.chain?.name || 'Prompt run' }}
            </span>
        </div>
        <span class="h-4 w-px bg-border/70"></span>
        <div class="flex flex-wrap items-center gap-4 text-muted-foreground">
            <div class="flex items-center gap-2">
                <Spinner v-if="isLive" class="text-primary" />
                <span class="rounded-md px-2 py-1 text-[11px] font-semibold" :class="statusBadgeClass(run.status)">
                    {{ run.status.toUpperCase() }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <Icon name="clock" class="h-4 w-4 text-muted-foreground" />
                <span class="font-medium text-foreground">{{ durationHuman(run.duration_ms) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <Icon name="cpu" class="h-4 w-4 text-muted-foreground" />
                <span class="font-medium text-foreground">{{ tokenUsageLabel }}</span>
                <span class="text-xs text-muted-foreground">tokens</span>
            </div>
            <template v-if="formatCost(run.total_cost)">
                <div class="flex items-center gap-2">
                    <Icon name="dollarSign" class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium text-foreground">{{ formatCost(run.total_cost) }}</span>
                </div>
            </template>
            <div class="flex items-center gap-2">
                <Icon name="calendar" class="h-4 w-4 text-muted-foreground" />
                <span class="font-medium text-foreground">{{ formatTimestamp(run.created_at) }}</span>
            </div>
        </div>
        <div class="ml-auto">
            <Link
                :href="runsRoutes.index(projectUuid).url"
                class="rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                Back to runs
            </Link>
        </div>
    </div>
</template>
