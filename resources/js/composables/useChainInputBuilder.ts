import { computed, ref, watch, type Ref } from 'vue';
import type { ChainNodePayload } from '@/types/chains';

const normalizeInputPath = (path: string) => {
    const trimmed = path.trim();
    if (!trimmed) return '';
    const normalized = trimmed.replace(/\[(.*?)\]/g, '.$1');
    return normalized.replace(/^input\./, '').replace(/^\.*/, '');
};

const parseInputValue = (raw: string) => {
    const trimmed = raw.trim();
    if (trimmed === '') return '';
    if (trimmed === 'true') return true;
    if (trimmed === 'false') return false;
    if (trimmed === 'null') return null;
    if (/^-?\d+(\.\d+)?$/.test(trimmed)) return Number(trimmed);
    if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
        try {
            return JSON.parse(trimmed) as unknown;
        } catch {
            return raw;
        }
    }
    return raw;
};

const setNestedValue = (target: Record<string, unknown>, path: string, value: unknown) => {
    if (!path) return;
    const parts = path.split('.').filter(Boolean);
    let cursor: Record<string, unknown> = target;

    parts.forEach((part, index) => {
        if (index === parts.length - 1) {
            cursor[part] = value;
            return;
        }

        if (!cursor[part] || typeof cursor[part] !== 'object') {
            cursor[part] = {};
        }

        cursor = cursor[part] as Record<string, unknown>;
    });
};

export const useChainInputBuilder = (options: {
    nodes: Ref<ChainNodePayload[]>;
    runForm: { input: string };
}) => {
    const manualInputValues = ref<Record<string, string>>({});

    const inputFields = computed(() => {
        const fields = new Map<string, string>();

        options.nodes.value.forEach((node) => {
            (node.messages_config || []).forEach((message) => {
                const variables = message.variables;
                if (!variables || Array.isArray(variables) || typeof variables !== 'object') {
                    return;
                }

                Object.entries(variables).forEach(([name, mapping]) => {
                    if (!mapping || mapping.source !== 'input') {
                        return;
                    }

                    const rawPath = mapping.path && mapping.path.length ? mapping.path : name;
                    const path = normalizeInputPath(rawPath);
                    if (!path) return;

                    if (!fields.has(path)) {
                        fields.set(path, name);
                    }
                });
            });
        });

        return Array.from(fields.entries()).map(([path, name]) => ({ path, name }));
    });

    watch(
        inputFields,
        (fields) => {
            const next: Record<string, string> = { ...manualInputValues.value };
            const fieldPaths = new Set(fields.map((field) => field.path));

            fields.forEach((field) => {
                if (!(field.path in next)) {
                    next[field.path] = '';
                }
            });

            Object.keys(next).forEach((key) => {
                if (!fieldPaths.has(key)) {
                    delete next[key];
                }
            });

            manualInputValues.value = next;
        },
        { immediate: true },
    );

    const updateRunInput = () => {
        if (!inputFields.value.length) return;
        const payload: Record<string, unknown> = {};

        inputFields.value.forEach((field) => {
            const value = parseInputValue(manualInputValues.value[field.path] ?? '');
            setNestedValue(payload, field.path, value);
        });

        options.runForm.input = JSON.stringify(payload, null, 2);
    };

    watch(manualInputValues, updateRunInput, { deep: true });
    watch(inputFields, updateRunInput);

    const missingManualInputs = computed(() =>
        inputFields.value.filter((field) => !(manualInputValues.value[field.path] ?? '').trim()),
    );

    return {
        inputFields,
        manualInputValues,
        missingManualInputs,
    };
};
