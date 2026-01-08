<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { VersionPayload } from '@/types/prompts';

interface Props {
    templateName: string;
    templateNameEditing: boolean;
    selectedVersion: VersionPayload | null;
    rating?: { up: number; down: number; score: number } | null;
    hasChanges: boolean;
    isDraftSelected: boolean;
    saveLabel: string;
    saveActionLabel: string;
    changelog: string;
    versionErrors: Record<string, string>;
    versionProcessing: boolean;
    canOpenRunModal: boolean;
    showSaveMenu: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (event: 'update:templateName', value: string): void;
    (event: 'update:changelog', value: string): void;
    (event: 'update:showSaveMenu', value: boolean): void;
    (event: 'start-name-edit'): void;
    (event: 'commit-name'): void;
    (event: 'cancel-name'): void;
    (event: 'submit-version'): void;
    (event: 'open-run'): void;
    (event: 'open-variables'): void;
    (event: 'open-versions'): void;
}>();

const templateNameInputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.templateNameEditing,
    (editing) => {
        if (!editing) return;
        nextTick(() => {
            templateNameInputRef.value?.focus();
            templateNameInputRef.value?.select();
        });
    },
);

const templateNameValue = computed({
    get: () => props.templateName,
    set: (value) => emit('update:templateName', value),
});

const changelogValue = computed({
    get: () => props.changelog,
    set: (value) => emit('update:changelog', value),
});

const saveMenuOpen = computed({
    get: () => props.showSaveMenu,
    set: (value) => emit('update:showSaveMenu', value),
});

const changelogError = computed(
    () => props.versionErrors.changelog || props.versionErrors.initial_changelog,
);
const contentError = computed(
    () => props.versionErrors.content || props.versionErrors.initial_content,
);
</script>

<template>
    <div class="sticky top-0 z-10 border-b border-border/60 bg-white px-6 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <button
                        v-if="!templateNameEditing"
                        type="button"
                        class="text-left"
                        @click="$emit('start-name-edit')"
                    >
                        <h2 class="text-lg font-semibold text-foreground">{{ templateName }}</h2>
                    </button>
                    <input
                        v-else
                        ref="templateNameInputRef"
                        v-model="templateNameValue"
                        type="text"
                        class="h-9 w-[240px] rounded-md border border-input bg-background px-3 py-1 text-base text-foreground shadow-sm transition focus:outline-none focus:ring-1 focus:ring-primary md:text-sm"
                        @blur="$emit('commit-name')"
                        @keydown.enter.prevent="$emit('commit-name')"
                        @keydown.esc.prevent="$emit('cancel-name')"
                    />
                </div>
                <span
                    v-if="selectedVersion"
                    class="rounded-full border border-border/60 bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                >
                    v{{ selectedVersion.version }}
                </span>
                <span
                    v-if="rating"
                    class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <Icon name="thumbsUp" class="h-3 w-3" />
                        {{ rating.up }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <Icon name="thumbsDown" class="h-3 w-3" />
                        {{ rating.down }}
                    </span>
                </span>
                <span
                    v-if="hasChanges"
                    class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700"
                >
                    Modified
                </span>
                <span class="text-xs text-muted-foreground">
                    Last saved: {{ selectedVersion?.created_at || 'Not saved yet' }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    v-if="hasChanges && isDraftSelected"
                    size="sm"
                    :disabled="versionProcessing"
                    @click="$emit('submit-version')"
                >
                    {{ saveLabel }}
                </Button>
                <DropdownMenu v-else-if="hasChanges" v-model:open="saveMenuOpen">
                    <DropdownMenuTrigger as-child>
                        <Button size="sm">{{ saveLabel }}</Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-72 p-3">
                        <div class="grid gap-2">
                            <Label for="changelog">Changelog (optional)</Label>
                            <Input
                                id="changelog"
                                v-model="changelogValue"
                                name="changelog"
                                placeholder="What changed?"
                            />
                            <InputError :message="changelogError" />
                        </div>
                        <InputError :message="contentError" class="mt-2" />
                        <div class="mt-3 flex items-center justify-end gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="saveMenuOpen = false"
                            >
                                Cancel
                            </Button>
                            <Button
                                size="sm"
                                :disabled="versionProcessing"
                                @click="$emit('submit-version')"
                            >
                                {{ saveActionLabel }}
                            </Button>
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button
                    size="sm"
                    :disabled="!canOpenRunModal"
                    :variant="hasChanges ? 'outline' : 'default'"
                    @click="$emit('open-run')"
                >
                    Run prompt
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isDraftSelected"
                    @click="$emit('open-variables')"
                >
                    Variables
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isDraftSelected"
                    @click="$emit('open-versions')"
                >
                    Versions
                </Button>
            </div>
        </div>
    </div>
</template>
