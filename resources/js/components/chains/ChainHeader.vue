<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Ref } from 'vue';

interface ChainForm {
    name: string;
    description: string;
    errors: Record<string, string>;
    processing: boolean;
}

defineProps<{
    backHref: string;
    chainNameEditing: boolean;
    chainForm: ChainForm;
    chainNameInputRef: Ref<HTMLInputElement | null>;
}>();

const emit = defineEmits<{
    (event: 'update:chain-name', value: string): void;
    (event: 'start-name-edit'): void;
    (event: 'commit-name'): void;
    (event: 'open-description'): void;
    (event: 'run'): void;
    (event: 'save'): void;
}>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-transparent bg-transparent">
        <div class="flex flex-wrap items-center gap-3">
            <Button variant="outline" size="sm" :href="backHref" as="a">
                Back to chains
            </Button>
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2">
                    <div v-if="chainNameEditing" class="flex items-center gap-2">
                        <Input
                            :ref="chainNameInputRef"
                            :model-value="chainForm.name"
                            class="h-10 w-72 text-xl font-semibold"
                            @blur="emit('commit-name')"
                            @keyup.enter.prevent="emit('commit-name')"
                            @update:model-value="(value) => emit('update:chain-name', value)"
                        />
                    </div>
                    <div v-else class="flex items-center gap-2">
                        <h1
                            class="cursor-pointer text-2xl font-semibold text-foreground"
                            @click="emit('start-name-edit')"
                        >
                            {{ chainForm.name }}
                        </h1>
                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="emit('start-name-edit')">
                            <Icon name="pencil" class="h-4 w-4 text-muted-foreground" />
                        </Button>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        aria-label="Edit description"
                        @click="emit('open-description')"
                    >
                        <Icon name="info" class="h-4 w-4 text-muted-foreground" />
                    </Button>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="emit('run')">Run</Button>
            <Button size="sm" :disabled="chainForm.processing" @click="emit('save')">Save</Button>
        </div>
    </div>
    <InputError v-if="chainForm.errors.name" :message="chainForm.errors.name" />
</template>
