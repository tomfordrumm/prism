<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import runsRoutes from '@/routes/projects/runs';
import { Link } from '@inertiajs/vue3';

interface ProjectPayload {
    id: number;
    name: string;
    description?: string | null;
}

interface RunListItem {
    id: number;
    status: string;
    chain: { id: number; name: string } | null;
    dataset?: { id: number; name: string } | null;
    test_case?: { id: number; name: string } | null;
    total_tokens_in?: number | null;
    total_tokens_out?: number | null;
    duration_ms?: number | null;
    created_at: string;
}

interface Props {
    project: ProjectPayload;
    runs: RunListItem[];
}

defineProps<Props>();

const formatStatus = (status: string) => status.toUpperCase();
</script>

<template>
    <ProjectLayout :project="project" title-suffix="Runs">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-foreground">Runs</h2>
                <p class="text-sm text-muted-foreground">
                    Executions of chains within this project.
                </p>
            </div>
        </div>

        <div
            v-if="runs.length === 0"
            class="mt-4 rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground"
        >
            No runs yet. Trigger a chain to see results here.
        </div>

        <div v-else class="mt-4 overflow-hidden rounded-lg border border-border">
            <table class="min-w-full divide-y divide-border text-sm">
                <thead class="bg-muted">
                    <tr class="text-left text-xs font-semibold uppercase text-muted-foreground">
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">Chain</th>
                        <th class="px-4 py-2">Dataset</th>
                        <th class="px-4 py-2">Test case</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Tokens</th>
                        <th class="px-4 py-2">Duration</th>
                        <th class="px-4 py-2">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border bg-card">
                    <tr v-for="run in runs" :key="run.id" class="hover:bg-muted/60">
                        <td class="px-4 py-2 font-mono">
                            <Link :href="runsRoutes.show({ project: project.id, run: run.id }).url" class="text-primary">
                                #{{ run.id }}
                            </Link>
                        </td>
                        <td class="px-4 py-2">
                            {{ run.chain?.name || 'N/A' }}
                        </td>
                        <td class="px-4 py-2 text-sm text-muted-foreground">
                            {{ run.dataset?.name || '—' }}
                        </td>
                        <td class="px-4 py-2 text-sm text-muted-foreground">
                            {{ run.test_case?.name || '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                class="rounded-md px-2 py-1 text-xs font-semibold"
                                :class="run.status === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ formatStatus(run.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <div class="text-xs text-muted-foreground">
                                in: {{ run.total_tokens_in ?? '—' }}, out: {{ run.total_tokens_out ?? '—' }}
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            {{ run.duration_ms ? `${run.duration_ms} ms` : '—' }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ run.created_at }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ProjectLayout>
</template>
