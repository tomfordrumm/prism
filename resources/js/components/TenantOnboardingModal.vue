<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';

interface Props {
    open: boolean;
}

defineProps<Props>();

const form = useForm({
    name: '',
});

const submit = () => {
    form.post('/tenants', { preserveScroll: true });
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm"
    >
        <div class="w-full max-w-lg rounded-lg border border-border bg-card p-6 shadow-xl">
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-foreground">Create your workspace</h2>
                <p class="text-sm text-muted-foreground">
                    You need a tenant to start working. This step is required to continue.
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="tenant_name">Workspace name</Label>
                    <Input
                        id="tenant_name"
                        v-model="form.name"
                        name="name"
                        placeholder="Acme Labs"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">Create workspace</Button>
                </div>
            </form>
        </div>
    </div>
</template>
