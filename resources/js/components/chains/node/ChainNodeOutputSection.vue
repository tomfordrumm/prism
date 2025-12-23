<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

interface NodeForm {
    output_schema_definition: string;
    stop_on_validation_error: boolean;
    errors: Record<string, string>;
}

defineProps<{
    form: NodeForm;
}>();

const emit = defineEmits<{
    (event: 'update:output-schema-definition', value: string): void;
    (event: 'update:stop-on-validation-error', value: boolean): void;
}>();
</script>

<template>
    <div class="space-y-4 border-b border-border/60 pb-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Output + behavior</p>
        <div class="grid gap-2">
            <Label for="output_schema">Output schema (TS-like, optional)</Label>
            <textarea
                id="output_schema"
                :value="form.output_schema_definition"
                name="output_schema_definition"
                rows="6"
                placeholder='{
  question: string;
  answers: string[];
  explanation?: string;
  difficulty: "easy" | "medium" | "hard";
}'
                class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                @input="emit('update:output-schema-definition', ($event.target as HTMLTextAreaElement).value)"
            ></textarea>
            <InputError :message="form.errors.output_schema_definition" />
        </div>

        <div class="flex items-center gap-2">
            <Checkbox
                id="stop_on_validation_error"
                :checked="form.stop_on_validation_error"
                @update:checked="(value) => emit('update:stop-on-validation-error', value)"
            />
            <Label for="stop_on_validation_error">Stop on validation error</Label>
        </div>
    </div>
</template>
