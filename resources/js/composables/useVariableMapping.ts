import { computed, ref, type ComputedRef, type Ref } from 'vue';
import type {
    ContextStepSample,
    PromptTemplateOption,
    VariableMapping,
} from '@/types/chains';

interface MappingRow {
    name: string;
    mapping: VariableMapping;
    mappingText: string;
}

interface MappingStudioRow {
    role: 'system' | 'user';
    name: string;
    mappingText: string;
    snippet: { prefix: string; match: string; suffix: string };
    mapping: VariableMapping;
}

const templateVariableName = (value: unknown): string | null => {
    if (typeof value === 'string') return value;
    if (value && typeof value === 'object' && 'name' in value && typeof value.name === 'string') {
        return value.name;
    }

    return null;
};

const getTemplateVariables = (templates: PromptTemplateOption[], templateId: number | null) => {
    if (!templateId) return [];
    const template = templates.find((t) => t.id === templateId);
    if (!template) return [];

    return (template.variables || [])
        .map((v) => templateVariableName(v))
        .filter((value): value is string => Boolean(value));
};

const extractInlineVariables = (content: string) => {
    const regex = /\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}/g;
    const names: string[] = [];
    const seen = new Set<string>();
    let match: RegExpExecArray | null;
    while ((match = regex.exec(content))) {
        const name = match[1];
        if (!seen.has(name)) {
            names.push(name);
            seen.add(name);
        }
    }
    return names;
};

const toMappingText = (mapping: VariableMapping): string => {
    if (mapping.source === 'input') {
        return mapping.path ? `input.${mapping.path}` : 'input';
    }

    if (mapping.source === 'previous_step' && mapping.step_key) {
        return mapping.path ? `steps.${mapping.step_key}.${mapping.path}` : `steps.${mapping.step_key}`;
    }

    if (mapping.source === 'constant' && mapping.value) {
        return mapping.value;
    }

    return '';
};

const buildSnippetParts = (text: string, variable: string) => {
    if (!text) {
        return { prefix: '', match: `{{ ${variable} }}`, suffix: '' };
    }

    const escaped = variable.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp(`{{\\s*${escaped}\\s*}}`);
    const match = pattern.exec(text);
    if (!match || match.index === undefined) {
        const preview = text.slice(0, 80);
        return { prefix: preview, match: `{{ ${variable} }}`, suffix: '' };
    }

    const start = Math.max(0, match.index - 40);
    const end = Math.min(text.length, match.index + match[0].length + 40);
    return {
        prefix: `${start > 0 ? '...' : ''}${text.slice(start, match.index)}`,
        match: match[0],
        suffix: `${text.slice(match.index + match[0].length, end)}${end < text.length ? '...' : ''}`,
    };
};

const mapRows = (
    role: 'system' | 'user',
    templates: PromptTemplateOption[],
    nodeForm: {
        system_prompt_template_id: number | null;
        user_prompt_template_id: number | null;
        system_mode: 'template' | 'inline';
        user_mode: 'template' | 'inline';
        system_variables: Record<string, VariableMapping>;
        user_variables: Record<string, VariableMapping>;
        system_inline_content: string;
        user_inline_content: string;
    },
): MappingRow[] => {
    const templateId =
        role === 'system' ? nodeForm.system_prompt_template_id : nodeForm.user_prompt_template_id;
    const mode = role === 'system' ? nodeForm.system_mode : nodeForm.user_mode;
    const mappings = role === 'system' ? nodeForm.system_variables : nodeForm.user_variables;
    const inlineContent = role === 'system' ? nodeForm.system_inline_content : nodeForm.user_inline_content;

    const variables =
        mode === 'inline'
            ? extractInlineVariables(inlineContent || '')
            : getTemplateVariables(templates, templateId);

    return variables.map((name) => ({
        name,
        mapping: mappings[name] ?? {},
        mappingText: toMappingText(mappings[name] ?? {}),
    }));
};

export function useVariableMapping(options: {
    promptTemplates: Ref<PromptTemplateOption[]>;
    previousSteps: ComputedRef<ContextStepSample[]>;
    nodeForm: {
        system_prompt_template_id: number | null;
        user_prompt_template_id: number | null;
        system_mode: 'template' | 'inline';
        user_mode: 'template' | 'inline';
        system_variables: Record<string, VariableMapping>;
        user_variables: Record<string, VariableMapping>;
        system_inline_content: string;
        user_inline_content: string;
    };
    systemPromptText: ComputedRef<string>;
    userPromptText: ComputedRef<string>;
}) {
    const { promptTemplates, previousSteps, nodeForm, systemPromptText, userPromptText } = options;

    const mappingTarget = ref<{ role: 'system' | 'user'; name: string } | null>(null);

    const updateMapping = (role: 'system' | 'user', name: string, value: VariableMapping) => {
        const target = role === 'system' ? nodeForm.system_variables : nodeForm.user_variables;
        target[name] = { ...(target[name] ?? {}), ...value };
    };

    const clearMapping = (role: 'system' | 'user', name: string) => {
        const target = role === 'system' ? nodeForm.system_variables : nodeForm.user_variables;
        delete target[name];
    };

    const applyMappingText = (role: 'system' | 'user', name: string, rawValue: string) => {
        const value = rawValue.trim();
        if (!value) {
            clearMapping(role, name);
            return;
        }

        if (value === 'input') {
            updateMapping(role, name, {
                source: 'input',
                path: undefined,
                step_key: undefined,
                value: undefined,
            });
            return;
        }

        if (value.startsWith('input.')) {
            updateMapping(role, name, {
                source: 'input',
                path: value.slice('input.'.length),
                step_key: undefined,
                value: undefined,
            });
            return;
        }

        if (value.startsWith('steps.')) {
            const rest = value.slice('steps.'.length);
            const [stepKey, ...pathParts] = rest.split('.');
            if (stepKey) {
                updateMapping(role, name, {
                    source: 'previous_step',
                    step_key: stepKey,
                    path: pathParts.length ? pathParts.join('.') : undefined,
                    value: undefined,
                });
                return;
            }
        }

        const [head, ...rest] = value.split('.');
        const stepKeyMatch = previousSteps.value.find((step) => step.key === head);
        if (stepKeyMatch) {
            updateMapping(role, name, {
                source: 'previous_step',
                step_key: head,
                path: rest.length ? rest.join('.') : undefined,
                value: undefined,
            });
            return;
        }

        updateMapping(role, name, {
            source: 'constant',
            value,
            path: undefined,
            step_key: undefined,
        });
    };

    const variableRowsSystem = computed(() => mapRows('system', promptTemplates.value, nodeForm));
    const variableRowsUser = computed(() => mapRows('user', promptTemplates.value, nodeForm));

    const variablesMissingMapping = computed(() => {
        const collectMissing = (rows: { name: string; mapping: VariableMapping }[]) =>
            rows.filter((row) => !toMappingText(row.mapping)).map((row) => row.name);

        return {
            system: collectMissing(variableRowsSystem.value),
            user: collectMissing(variableRowsUser.value),
        };
    });

    const mappingRowsStudio = computed<MappingStudioRow[]>(() => {
        const systemText = systemPromptText.value || '';
        const userText = userPromptText.value || '';

        const systemRows = variableRowsSystem.value.map((row) => ({
            role: 'system' as const,
            name: row.name,
            mappingText: row.mappingText,
            snippet: buildSnippetParts(systemText, row.name),
            mapping: row.mapping,
        }));

        const userRows = variableRowsUser.value.map((row) => ({
            role: 'user' as const,
            name: row.name,
            mappingText: row.mappingText,
            snippet: buildSnippetParts(userText, row.name),
            mapping: row.mapping,
        }));

        return [...systemRows, ...userRows];
    });

    return {
        mappingTarget,
        variableRowsSystem,
        variableRowsUser,
        variablesMissingMapping,
        mappingRowsStudio,
        applyMappingText,
        updateMapping,
        clearMapping,
        toMappingText,
        buildSnippetParts,
        extractInlineVariables,
    };
}
