<script setup lang="ts">
import { computed } from 'vue';

interface InternalSchemaNode {
    type?: string;
    fields?: Record<string, InternalSchemaNode>;
    items?: InternalSchemaNode;
    values?: string[];
    required?: boolean;
}

interface PreviewLine {
    label: string;
    type: string;
    depth: number;
    path: string;
}

const props = defineProps<{
    schema: InternalSchemaNode | null;
    maxDepth?: number;
}>();

const maxDepth = computed(() => props.maxDepth ?? 3);

const describeType = (node: InternalSchemaNode): string => {
    if (node.type === 'array') {
        const inner = node.items ? describeType(node.items) : 'item';
        return `${inner}[]`;
    }

    if (node.type === 'enum') {
        return (node.values ?? []).join(' | ') || 'enum';
    }

    if (node.type === 'object') {
        return 'object';
    }

    return node.type ?? 'unknown';
};

const previewLines = computed<PreviewLine[]>(() => {
    const lines: PreviewLine[] = [];
    const schema = props.schema;

    const walk = (node: InternalSchemaNode, label: string, path: string, depth: number) => {
        const typedLabel = node.required === false ? `${label}?` : label;
        lines.push({
            label: typedLabel,
            type: describeType(node),
            depth,
            path,
        });

        if (depth + 1 >= maxDepth.value) {
            if (
                (node.type === 'object' && node.fields && Object.keys(node.fields).length > 0) ||
                node.type === 'array'
            ) {
                lines.push({
                    label: '...',
                    type: '',
                    depth: depth + 1,
                    path: `${path}.ellipsis`,
                });
            }

            return;
        }

        if (node.type === 'object' && node.fields) {
            Object.entries(node.fields).forEach(([childLabel, childNode]) => {
                walk(childNode ?? {}, childLabel, `${path}.${childLabel}`, depth + 1);
            });
        }

        if (node.type === 'array' && node.items) {
            walk(node.items, `${label}[0]`, `${path}.items`, depth + 1);
        }
    };

    if (schema?.type === 'object' && schema.fields) {
        Object.entries(schema.fields).forEach(([key, child]) => walk(child ?? {}, key, key, 0));
    } else if (schema) {
        walk(schema, 'output', 'output', 0);
    }

    return lines;
});
</script>

<template>
    <div class="rounded-md border border-border/60 bg-background/50 p-3">
        <p v-if="previewLines.length === 0" class="text-xs text-muted-foreground">Schema: No details.</p>
        <div v-else class="space-y-1 text-xs">
            <div
                v-for="line in previewLines"
                :key="line.path"
                class="flex items-center justify-between rounded-sm px-1 py-[2px] hover:bg-muted/60"
            >
                <div
                    class="flex min-w-0 flex-1 items-center gap-2 truncate"
                    :style="{ paddingLeft: `${line.depth * 12}px` }"
                >
                    <span class="truncate font-semibold text-foreground">{{ line.label }}</span>
                </div>
                <span class="text-[11px] uppercase text-muted-foreground">{{ line.type || '...' }}</span>
            </div>
        </div>
    </div>
</template>
