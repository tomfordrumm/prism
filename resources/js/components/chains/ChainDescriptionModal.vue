<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface ChainForm {
    description: string;
    errors: Record<string, string>;
    processing: boolean;
}

defineProps<{
    open: boolean;
    form: ChainForm;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'update:description', value: string): void;
    (event: 'save'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Edit description</DialogTitle>
                <DialogDescription>Update the chain description.</DialogDescription>
            </DialogHeader>

            <div class="space-y-3">
                <div class="grid gap-2">
                    <Label for="chain_description_modal">Description</Label>
                    <textarea
                        id="chain_description_modal"
                        :value="form.description"
                        name="description"
                        rows="3"
                        placeholder="Short description of the chain"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                        @input="emit('update:description', ($event.target as HTMLTextAreaElement).value)"
                    ></textarea>
                    <InputError :message="form.errors.description" />
                </div>
            </div>

            <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button :disabled="form.processing" @click="emit('save')">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
