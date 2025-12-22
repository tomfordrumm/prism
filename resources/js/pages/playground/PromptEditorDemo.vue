<script setup lang="ts">
import { ref } from 'vue';
import PromptEditor from '@/components/PromptEditor.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';

const mode = ref<'plain' | 'markdown' | 'xml'>('plain');
const preset = ref<'minimal' | 'ide'>('minimal');
const content = ref(`Hello {{ user.name }}!

Write a short summary for {{ order.total }}.
`);
</script>

<template>
    <AppSidebarLayout :breadcrumbs="[{ label: 'Playground' }, { label: 'Prompt Editor' }]">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-6 py-8">
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold text-foreground">Prompt Editor Demo</h1>
                <p class="text-sm text-muted-foreground">
                    Toggle modes and presets to see syntax and template variable highlighting.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Use the toolbar to switch modes and presets.
                </div>
            </div>

            <PromptEditor
                v-model="content"
                v-model:mode="mode"
                v-model:preset="preset"
                placeholder="Write a prompt with {{variables}}..."
                height="360px"
                show-controls
            />

            <div class="space-y-2">
                <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Output</div>
                <pre class="whitespace-pre-wrap rounded-md border border-border bg-white p-4 text-sm text-foreground">
{{ content }}
                </pre>
            </div>
        </div>
    </AppSidebarLayout>
</template>
