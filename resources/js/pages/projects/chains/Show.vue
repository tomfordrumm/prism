<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import chainRoutes from '@/routes/projects/chains';
import providerCredentialRoutes from '@/routes/provider-credentials';
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

import Icon from '@/components/Icon.vue';
import InputError from '@/components/InputError.vue';
import PromptEditor from '@/components/PromptEditor.vue';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Timeline from 'primevue/timeline';
import Toast from 'primevue/toast';
import Tree from 'primevue/tree';
import { useToast } from 'primevue/usetoast';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

interface ChainPayload {
    id: number;
    name: string;
    description?: string | null;
}

interface ChainNodeMessage {
    role: string;
    mode?: 'template' | 'inline';
    prompt_template_id?: number | null;
    prompt_version_id: number | null;
    variables?: Record<string, VariableMapping>;
    inline_content?: string | null;
}

interface PromptDetails {
    mode: 'template' | 'inline';
    template_id: number | null;
    template_name: string | null;
    prompt_version_id: number | null;
    prompt_version: number | null;
    content: string | null;
    variables: string[];
}

interface InternalSchemaNode {
    type?: string;
    fields?: Record<string, InternalSchemaNode>;
    items?: InternalSchemaNode;
    values?: string[];
    required?: boolean;
}

interface ChainNodePayload {
    id: number;
    name: string;
    order_index: number;
    provider_credential_id: number | null;
    provider_credential: { id: number; name: string; provider: string } | null;
    model_name: string;
    model_params: Record<string, unknown> | null;
    messages_config: ChainNodeMessage[];
    output_schema: InternalSchemaNode | null;
    output_schema_definition?: string | null;
    stop_on_validation_error: boolean;
    prompt_details?: {
        system: PromptDetails | null;
        user: PromptDetails | null;
    };
    variables_used?: string[];
}

interface ModelOption {
    id: string;
    name: string;
    display_name: string;
}

interface ModelSelectOption {
    label: string;
    value: string;
    credentialId: number;
    modelId: string;
}

interface ModelSelectGroup {
    label: string;
    items: ModelSelectOption[];
}

interface ContextStepSample {
    key: string;
    name: string;
    order_index: number;
    sample: unknown;
}

interface ContextSample {
    input: Record<string, unknown> | null;
    steps: ContextStepSample[];
}

type VariableSource = 'input' | 'previous_step' | 'constant' | '';

interface VariableMapping {
    source?: VariableSource;
    path?: string;
    step_key?: string;
    value?: string;
}

interface Option {
    value: number | string;
    label: string;
    provider?: string;
}

interface PromptTemplateVersion {
    id: number;
    version: number;
    created_at: string | null;
    content: string;
}

interface PromptTemplateOption {
    id: number;
    name: string;
    latest_version_id: number | null;
    versions: PromptTemplateVersion[];
}

interface Props {
    project: ProjectPayload;
    chain: ChainPayload;
    nodes: ChainNodePayload[];
    providerCredentials: Option[];
    providerCredentialModels: Record<number, ModelOption[]>;
    providerOptions: Option[];
    promptTemplates: PromptTemplateOption[];
    datasets: Option[];
    contextSample: ContextSample;
}

const props = defineProps<Props>();

const templateOptions = computed(() =>
    props.promptTemplates.map((template) => ({
        value: template.id,
        label: template.name,
    })),
);

const userTemplateOptions = computed(() => [
    { value: null, label: 'No user prompt' },
    ...templateOptions.value,
]);

const versionLookup = computed(() => {
    const map = new Map<number, { templateId: number; templateName: string; version: number; created_at: string | null }>();

    props.promptTemplates.forEach((template) => {
        template.versions.forEach((version) => {
            map.set(version.id, {
                templateId: template.id,
                templateName: template.name,
                version: version.version,
                created_at: version.created_at ?? null,
            });
        });
    });

    return map;
});

const chainForm = useForm({
    name: props.chain.name,
    description: props.chain.description ?? '',
});

const chainNameEditing = ref(false);
const chainNameInputRef = ref<HTMLInputElement | null>(null);
const descriptionModalOpen = ref(false);
const chainNameBeforeEdit = ref(props.chain.name);

const nodeNameEditing = ref(false);
const nodeNameInputRef = ref<HTMLInputElement | null>(null);
const showAdvancedModelSettings = ref(false);
const showAvailableData = ref(false);
const mappingStudioOpen = ref(false);
const systemEditorMode = ref<'plain' | 'markdown' | 'xml'>('plain');
const systemEditorPreset = ref<'minimal' | 'ide'>('minimal');
const userEditorMode = ref<'plain' | 'markdown' | 'xml'>('plain');
const userEditorPreset = ref<'minimal' | 'ide'>('minimal');
const toast = useToast();

const updateChain = () => {
    chainForm
        .transform((data) => ({
            name: data.name,
            description: data.description || null,
        }))
        .put(chainRoutes.update({ project: props.project.uuid, chain: props.chain.id }).url, {
            preserveScroll: true,
        });
};

const startNameEdit = () => {
    chainNameEditing.value = true;
    chainNameBeforeEdit.value = chainForm.name;
    nextTick(() => {
        chainNameInputRef.value?.focus();
        chainNameInputRef.value?.select();
    });
};

const commitChainName = () => {
    const trimmed = chainForm.name.trim();
    if (!trimmed) {
        chainForm.name = props.chain.name;
        chainNameEditing.value = false;
        return;
    }

    chainForm.name = trimmed;
    chainNameEditing.value = false;
    if (trimmed !== chainNameBeforeEdit.value) {
        updateChain();
    }
};

const openDescriptionEditor = () => {
    descriptionModalOpen.value = true;
};

const saveDescription = () => {
    updateChain();
    descriptionModalOpen.value = false;
};

const cancelDescriptionEdit = () => {
    descriptionModalOpen.value = false;
};

const sortedNodes = computed(() => [...props.nodes].sort((a, b) => a.order_index - b.order_index));
const activeNodeId = ref<number | 'new' | 'input' | 'output' | null>(null);
const hasProviderCredentials = computed(() => props.providerCredentials.length > 0);
const hasDatasets = computed(() => props.datasets.length > 0);
const currentOrderIndex = computed(() => Number(nodeForm.order_index) || props.nodes.length + 1);
const promptModeOptions = [
    { label: 'Template', value: 'template' },
    { label: 'Custom', value: 'inline' },
];

const parseMessagesConfig = (messages: ChainNodeMessage[]) => {
    const systemEntry = messages.find((message) => message.role === 'system');
    const userEntry = messages.find((message) => message.role === 'user');

    const resolveTemplateId = (entry: ChainNodeMessage | undefined) => {
        if (entry?.prompt_template_id) return entry.prompt_template_id;
        if (entry?.prompt_version_id) {
            return versionLookup.value.get(entry.prompt_version_id)?.templateId ?? null;
        }
        return null;
    };

    return {
        system_mode: (systemEntry as any)?.mode ?? 'template',
        system_prompt_template_id: resolveTemplateId(systemEntry),
        system_prompt_version_id: systemEntry?.prompt_version_id ?? null,
        system_variables: (systemEntry as any)?.variables ?? {},
        system_inline_content: (systemEntry as any)?.inline_content ?? '',
        user_mode: (userEntry as any)?.mode ?? 'template',
        user_prompt_template_id: resolveTemplateId(userEntry),
        user_prompt_version_id: userEntry?.prompt_version_id ?? null,
        user_variables: (userEntry as any)?.variables ?? {},
        user_inline_content: (userEntry as any)?.inline_content ?? '',
    };
};

const buildMessagesConfig = (
    systemMode: 'template' | 'inline',
    systemTemplateId: number | null,
    systemVersionId: number | null,
    systemInlineContent: string,
    systemVariables: Record<string, any>,
    userMode: 'template' | 'inline',
    userTemplateId: number | null,
    userVersionId: number | null,
    userInlineContent: string,
    userVariables: Record<string, any>,
): ChainNodeMessage[] => {
    const config: ChainNodeMessage[] = [];

    if (systemMode === 'inline' || systemTemplateId) {
        config.push({
            role: 'system',
            mode: systemMode,
            prompt_template_id: systemTemplateId,
            prompt_version_id: systemVersionId,
            inline_content: systemMode === 'inline' ? systemInlineContent : null,
            variables: systemVariables,
        });
    }

    if (userMode === 'inline' || userTemplateId) {
        config.push({
            role: 'user',
            mode: userMode,
            prompt_template_id: userTemplateId,
            prompt_version_id: userVersionId,
            inline_content: userMode === 'inline' ? userInlineContent : null,
            variables: userVariables,
        });
    }

    return config;
};

const extractModelParams = (params: Record<string, unknown> | null) => ({
    temperature: typeof params?.temperature === 'number' ? params.temperature : null,
    max_tokens: typeof params?.max_tokens === 'number' ? params.max_tokens : null,
});

const buildModelParams = (data: { temperature: number | null; max_tokens: number | null }) => {
    const params: Record<string, unknown> = {};

    if (data.temperature !== null && !Number.isNaN(Number(data.temperature))) {
        params.temperature = Number(data.temperature);
    }

    if (data.max_tokens !== null && !Number.isNaN(Number(data.max_tokens))) {
        params.max_tokens = Number(data.max_tokens);
    }

    return Object.keys(params).length ? params : null;
};

const normalizeVersionSelection = (value: number | string | null): number | null => {
    if (value === null || value === '' || value === 'latest') {
        return null;
    }

    const num = Number(value);
    return Number.isNaN(num) ? null : num;
};

interface TreeNode {
    label: string;
    path: string;
    type: string;
    children?: TreeNode[];
}


const buildTree = (value: unknown, basePath: string): TreeNode[] => {
    if (value === null || value === undefined) return [];

    const buildNode = (label: string, path: string, val: unknown): TreeNode => {
        if (Array.isArray(val)) {
            const childPath = `${path}[0]`;
            return {
                label,
                path,
                type: 'array',
                children: buildTree(val[0], childPath),
            };
        }

        if (val && typeof val === 'object') {
            const children = Object.entries(val as Record<string, unknown>).map(([key, childVal]) =>
                buildNode(key, path ? `${path}.${key}` : key, childVal),
            );

            return {
                label,
                path,
                type: 'object',
                children,
            };
        }

        return {
            label,
            path,
            type: typeof val || 'string',
        };
    };

    if (typeof value === 'object' && !Array.isArray(value)) {
        return Object.entries(value as Record<string, unknown>).map(([key, val]) =>
            buildNode(key, basePath ? `${basePath}.${key}` : key, val),
        );
    }

    return [buildNode(basePath, basePath, value)];
};

const inputTree = computed<TreeNode[]>(() => buildTree(props.contextSample.input ?? {}, 'input'));

const previousSteps = computed(() =>
    (props.contextSample.steps || []).filter((step) => step.order_index < currentOrderIndex.value),
);

const stepsTree = computed<TreeNode[]>(() =>
    previousSteps.value.map((step) => ({
        label: step.name,
        path: `steps.${step.key}`,
        type: 'object',
        children: buildTree(step.sample ?? {}, `steps.${step.key}`),
    })),
);

const insertPlaceholder = (path: string) => {
    const placeholder = `{{${path}}}`;
    const active = document.activeElement as HTMLTextAreaElement | HTMLInputElement | null;

    if (active && (active.tagName === 'TEXTAREA' || active.tagName === 'INPUT')) {
        const start = active.selectionStart ?? active.value.length;
        const end = active.selectionEnd ?? active.value.length;
        const value = active.value;
        active.value = value.slice(0, start) + placeholder + value.slice(end);
        active.dispatchEvent(new Event('input', { bubbles: true }));
        active.focus();
        active.selectionStart = active.selectionEnd = start + placeholder.length;
        return;
    }

    navigator.clipboard.writeText(placeholder).catch(() => {});
};


interface PrimeTreeNode {
    key: string;
    label: string;
    data?: { path?: string; type?: string };
    children?: PrimeTreeNode[];
}

const transformToPrimeTreeNodes = (nodes: TreeNode[]): PrimeTreeNode[] =>
    nodes.map((node) => {
        const children = node.children ? transformToPrimeTreeNodes(node.children) : undefined;

        return {
            key: node.path || node.label,
            label: node.label,
            data: { path: node.path, type: node.type },
            children: children && children.length ? children : undefined,
        };
    });

const availableDataTree = computed<PrimeTreeNode[]>(() => {
    const inputNodes = transformToPrimeTreeNodes(inputTree.value);
    const stepNodes = transformToPrimeTreeNodes(stepsTree.value);

    return [
        {
            key: 'input',
            label: 'Input',
            data: { path: 'input', type: 'object' },
            children: inputNodes,
        },
        ...stepNodes,
    ];
});

const filterTreeNodes = (nodes: PrimeTreeNode[], term: string): PrimeTreeNode[] => {
    if (!term.trim()) return nodes;
    const lowered = term.toLowerCase();

    return nodes
        .map((node) => {
            const labelMatch = node.label.toLowerCase().includes(lowered);
            const pathMatch = node.data?.path?.toLowerCase().includes(lowered);
            const children = node.children ? filterTreeNodes(node.children, term) : [];

            if (labelMatch || pathMatch || children.length) {
                return {
                    ...node,
                    children: children.length ? children : undefined,
                };
            }

            return null;
        })
        .filter((node): node is PrimeTreeNode => Boolean(node));
};

const filteredAvailableDataTree = computed(() =>
    filterTreeNodes(availableDataTree.value, availableDataSearch.value),
);

const filteredStudioDataTree = computed(() =>
    filterTreeNodes(availableDataTree.value, mappingStudioSearch.value),
);

const onTreeSelect = (event: { node: PrimeTreeNode }) => {
    const path = event.node?.data?.path;
    if (path) {
        insertPlaceholder(path);
    }
};

const onStudioTreeSelect = (event: { node: PrimeTreeNode }) => {
    const path = event.node?.data?.path;
    if (!path || !mappingTarget.value) return;

    applyMappingText(mappingTarget.value.role, mappingTarget.value.name, path);
    const key = `${mappingTarget.value.role}:${mappingTarget.value.name}`;
    mappingFlashKey.value = key;
    window.setTimeout(() => {
        if (mappingFlashKey.value === key) {
            mappingFlashKey.value = null;
        }
    }, 450);
};

const handleTreeDragStart = (event: DragEvent, path?: string) => {
    if (!path || !event.dataTransfer) return;
    event.dataTransfer.setData('text/plain', path);
    event.dataTransfer.setData('text', path);
    event.dataTransfer.effectAllowed = 'copy';

    const ghost = document.createElement('div');
    ghost.textContent = path;
    ghost.style.fontSize = '12px';
    ghost.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace';
    ghost.style.padding = '4px 8px';
    ghost.style.background = 'rgba(15, 23, 42, 0.85)';
    ghost.style.color = 'white';
    ghost.style.borderRadius = '6px';
    ghost.style.position = 'absolute';
    ghost.style.top = '-9999px';
    ghost.style.left = '-9999px';
    document.body.appendChild(ghost);
    event.dataTransfer.setDragImage(ghost, 0, 0);
    window.setTimeout(() => ghost.remove(), 0);
};

const handleMappingDrop = (
    event: DragEvent,
    role: 'system' | 'user',
    name: string,
) => {
    event.preventDefault();
    const path = event.dataTransfer?.getData('text/plain') ?? '';
    if (!path) return;

    mappingTarget.value = { role, name };
    applyMappingText(role, name, path);
    const key = `${role}:${name}`;
    mappingFlashKey.value = key;
    window.setTimeout(() => {
        if (mappingFlashKey.value === key) {
            mappingFlashKey.value = null;
        }
    }, 450);
};

const copyPath = (path?: string) => {
    if (!path) return;
    navigator.clipboard.writeText(path).catch(() => {});
};

const timelineItems = computed(() => {
    const items: Array<{
        id: number | string;
        type: 'input' | 'step' | 'output';
        name: string;
        model?: string | null;
        provider?: string | null;
        order?: number;
        rawNode?: ChainNodePayload;
    }> = [
        { id: 'input', type: 'input', name: 'Input' },
        ...sortedNodes.value.map((node) => ({
            id: node.id,
            type: 'step',
            name: node.name,
            model: node.model_name,
            provider: node.provider_credential?.name ?? null,
            order: node.order_index,
            rawNode: node,
        })),
        { id: 'output', type: 'output', name: 'Output' },
    ];

    return items;
});

const getTemplateVariables = (templateId: number | null) => {
    if (!templateId) return [];
    const template = props.promptTemplates.find((t) => t.id === templateId);
    if (!template) return [];

    return (template.variables || [])
        .map((v: any) => (typeof v === 'string' ? v : v?.name))
        .filter(Boolean) as string[];
};

const extractInlineVariables = (content: string) => {
    const regex = /\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*\}\}/g;
    const names: string[] = [];
    const seen = new Set<string>();
    let match;
    while ((match = regex.exec(content))) {
        const name = match[1];
        if (!seen.has(name)) {
            names.push(name);
            seen.add(name);
        }
    }
    return names;
};

const mappingTarget = ref<{ role: 'system' | 'user'; name: string } | null>(null);
const availableDataSearch = ref('');
const mappingStudioSearch = ref('');
const mappingFlashKey = ref<string | null>(null);

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

const mappingRows = (role: 'system' | 'user') => {
    const templateId =
        role === 'system' ? nodeForm.system_prompt_template_id : nodeForm.user_prompt_template_id;
    const mode = role === 'system' ? nodeForm.system_mode : nodeForm.user_mode;
    const mappings = role === 'system' ? nodeForm.system_variables : nodeForm.user_variables;
    const inlineContent = role === 'system' ? nodeForm.system_inline_content : nodeForm.user_inline_content;

    const variables =
        mode === 'inline' ? extractInlineVariables(inlineContent || '') : getTemplateVariables(templateId);
    return variables.map((name) => ({
        name,
        mapping: mappings[name] ?? {},
        mappingText: toMappingText(mappings[name] ?? {}),
    }));
};

const variableRowsSystem = computed(() => mappingRows('system'));
const variableRowsUser = computed(() => mappingRows('user'));

const updateMapping = (
    role: 'system' | 'user',
    name: string,
    value: VariableMapping,
) => {
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

const openMappingStudio = (role?: 'system' | 'user', name?: string) => {
    mappingStudioOpen.value = true;
    mappingTarget.value = role && name ? { role, name } : null;
};

const variablesMissingMapping = computed(() => {
    const collectMissing = (rows: { name: string; mapping: VariableMapping }[]) =>
        rows.filter((row) => !toMappingText(row.mapping)).map((row) => row.name);

    return {
        system: collectMissing(variableRowsSystem.value),
        user: collectMissing(variableRowsUser.value),
    };
});

const buildSnippetParts = (text: string, variable: string) => {
    if (!text) {
        return { prefix: '', match: `{{ ${variable} }}`, suffix: '' };
    }

    const escaped = variable.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&');
    const pattern = new RegExp(`{{\\s*${escaped}\\s*}}`);
    const match = pattern.exec(text);
    if (!match || match.index === undefined) {
        const preview = text.slice(0, 80);
        return { prefix: preview, match: `{{ ${variable} }}`, suffix: '' };
    }

    const start = Math.max(0, match.index - 40);
    const end = Math.min(text.length, match.index + match[0].length + 40);
    return {
        prefix: `${start > 0 ? '…' : ''}${text.slice(start, match.index)}`,
        match: match[0],
        suffix: `${text.slice(match.index + match[0].length, end)}${end < text.length ? '…' : ''}`,
    };
};

const mappingRowsStudio = computed(() => {
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

interface NodeFormState {
    name: string;
    provider_credential_id: number | null;
    model_name: string;
    model_choice: string;
    temperature: number | null;
    max_tokens: number | null;
    system_mode: 'template' | 'inline';
    system_prompt_template_id: number | null;
    system_prompt_version_id: number | string | null;
    system_inline_content: string;
    system_variables: Record<string, VariableMapping>;
    user_mode: 'template' | 'inline';
    user_prompt_template_id: number | null;
    user_prompt_version_id: number | string | null;
    user_inline_content: string;
    user_variables: Record<string, VariableMapping>;
    output_schema_definition: string;
    stop_on_validation_error: boolean;
    order_index: number;
}

const CUSTOM_MODEL_VALUE = 'custom';

const normalizeCredentialId = (value: number | string | undefined | null): number | null => {
    if (value === null || value === undefined) return null;
    const parsed = typeof value === 'number' ? value : Number(value);
    return Number.isNaN(parsed) ? null : parsed;
};

const getModelsForCredential = (credentialId: number | null): ModelOption[] => {
    if (!credentialId) return [];

    return props.providerCredentialModels[credentialId] ?? [];
};

const groupedModelOptions = computed<ModelSelectGroup[]>(() =>
    props.providerCredentials
        .map((credential) => {
            const credentialId = normalizeCredentialId(credential.value);
            if (!credentialId) {
                return null;
            }

            const models = getModelsForCredential(credentialId);
            const items: ModelSelectOption[] = models.map((model) => ({
                label: `${model.display_name} (${model.name})`,
                value: `${credentialId}:${model.id}`,
                credentialId,
                modelId: model.id,
            }));

            items.push({
                label: 'Custom model…',
                value: `${credentialId}:${CUSTOM_MODEL_VALUE}`,
                credentialId,
                modelId: CUSTOM_MODEL_VALUE,
            });

            return { label: credential.label, items };
        })
        .filter((group): group is ModelSelectGroup => Boolean(group)),
);

const buildVersionOptions = (templateId: number | null) => {
    const template = props.promptTemplates.find((t) => t.id === templateId);
    if (!template) return [];

    const options = [
        {
            value: 'latest',
            label: 'Latest',
        },
        ...template.versions.map((version) => {
            const timestamp = version.created_at ? ` - ${version.created_at}` : '';
            return {
                value: version.id,
                label: `v${version.version}${timestamp}`,
            };
        }),
    ];

    return options;
};

const systemVersionOptions = computed(() => buildVersionOptions(nodeForm.system_prompt_template_id));
const userVersionOptions = computed(() => buildVersionOptions(nodeForm.user_prompt_template_id));

const resolveTemplateContent = (templateId: number | null, versionId: number | string | null) => {
    if (!templateId) return '';
    const template = props.promptTemplates.find((item) => item.id === templateId);
    if (!template) return '';

    const resolvedVersionId =
        versionId && versionId !== 'latest' ? Number(versionId) : template.latest_version_id;
    if (!resolvedVersionId) return '';

    const version = template.versions.find((item) => item.id === resolvedVersionId);
    return version?.content ?? '';
};

const systemPromptText = computed(() =>
    nodeForm.system_mode === 'template'
        ? resolveTemplateContent(nodeForm.system_prompt_template_id, nodeForm.system_prompt_version_id)
        : nodeForm.system_inline_content,
);

const userPromptText = computed(() =>
    nodeForm.user_mode === 'template'
        ? resolveTemplateContent(nodeForm.user_prompt_template_id, nodeForm.user_prompt_version_id)
        : nodeForm.user_inline_content,
);

const selectModelForCredential = (credentialId: number | null, preferredModelName?: string | null) => {
    const options = getModelsForCredential(credentialId);

    if (preferredModelName && options.some((option) => option.id === preferredModelName)) {
        return { modelChoice: preferredModelName, modelName: preferredModelName };
    }

    const firstOption = options[0];

    if (firstOption) {
        return { modelChoice: firstOption.id, modelName: firstOption.id };
    }

    return { modelChoice: CUSTOM_MODEL_VALUE, modelName: preferredModelName ?? '' };
};

const buildInitialNodeFormState = (): NodeFormState => {
    const defaultCredentialId = normalizeCredentialId(props.providerCredentials[0]?.value);
    const modelSelection = selectModelForCredential(defaultCredentialId);

    return {
        name: '',
        provider_credential_id: defaultCredentialId,
        model_name: modelSelection.modelName,
        model_choice: modelSelection.modelChoice,
        temperature: null,
        max_tokens: null,
        system_mode: 'template',
        system_prompt_template_id: null,
        system_prompt_version_id: null,
        system_inline_content: '',
        system_variables: {},
        user_mode: 'template',
        user_prompt_template_id: null,
        user_prompt_version_id: null,
        user_inline_content: '',
        user_variables: {},
        output_schema_definition: '',
        stop_on_validation_error: false,
        order_index: props.nodes.length + 1,
    };
};

const nodeForm = useForm<NodeFormState>(buildInitialNodeFormState());

const editingNodeId = ref<number | null>(null);
const isCustomModel = computed(() => nodeForm.model_choice === CUSTOM_MODEL_VALUE);
const selectedModelValue = computed<string>({
    get: () => {
        if (!nodeForm.provider_credential_id) {
            return '';
        }

        return `${nodeForm.provider_credential_id}:${nodeForm.model_choice || CUSTOM_MODEL_VALUE}`;
    },
    set: (value) => {
        if (!value) {
            nodeForm.provider_credential_id = null;
            nodeForm.model_choice = '';
            return;
        }

        const [credentialIdRaw, modelIdRaw] = value.split(':');
        const credentialId = credentialIdRaw ? Number(credentialIdRaw) : null;
        const modelId = modelIdRaw || '';
        const previousCredentialId = nodeForm.provider_credential_id;
        const previousChoice = nodeForm.model_choice;

        nodeForm.provider_credential_id = Number.isNaN(credentialId) ? null : credentialId;
        nodeForm.model_choice = modelId;

        if (
            modelId === CUSTOM_MODEL_VALUE &&
            (previousChoice !== CUSTOM_MODEL_VALUE || previousCredentialId !== nodeForm.provider_credential_id)
        ) {
            nodeForm.model_name = '';
        }
    },
});

const resetModelSelection = (
    credentialId: number | null,
    preferredModelName?: string | null,
    forceCustom = false,
) => {
    const selection = forceCustom
        ? { modelChoice: CUSTOM_MODEL_VALUE, modelName: preferredModelName ?? '' }
        : selectModelForCredential(credentialId, preferredModelName);

    nodeForm.model_choice = selection.modelChoice;
    nodeForm.model_name = selection.modelName;
};

watch(
    () => nodeForm.model_choice,
    (choice, previousChoice) => {
        if (choice && choice !== CUSTOM_MODEL_VALUE) {
            nodeForm.model_name = choice;
            return;
        }

        if (choice === CUSTOM_MODEL_VALUE && previousChoice !== CUSTOM_MODEL_VALUE) {
            if (nodeForm.model_name === previousChoice || !nodeForm.model_name) {
                nodeForm.model_name = '';
            }
        } else if (!choice) {
            nodeForm.model_name = '';
        }
    },
);

watch(
    () => nodeForm.system_prompt_template_id,
    (templateId) => {
        nodeForm.system_prompt_version_id = templateId ? 'latest' : null;
    },
);

watch(
    () => nodeForm.user_prompt_template_id,
    (templateId) => {
        nodeForm.user_prompt_version_id = templateId ? 'latest' : null;
    },
);

watch(
    sortedNodes,
    (nodes) => {
        if (activeNodeId.value === 'new') {
            return;
        }

        const exists = nodes.some((node) => node.id === activeNodeId.value);
        if (!exists) {
            activeNodeId.value = nodes[0]?.id ?? null;
        }
    },
    { immediate: false },
);

watch(
    activeNodeId,
    (id) => {
        if (id === 'new') {
            openCreateDrawer();
            return;
        }

        if (id === 'input' || id === 'output' || id === null) {
            editingNodeId.value = null;
            return;
        }

        const node = sortedNodes.value.find((item) => item.id === id);
        if (node) {
            openEditDrawer(node);
        }
    },
    { immediate: true },
);

const openCreateDrawer = () => {
    nodeNameEditing.value = false;
    editingNodeId.value = null;
    nodeForm.reset();
    nodeForm.name = '';
    const defaultCredentialId = normalizeCredentialId(props.providerCredentials[0]?.value);
    nodeForm.provider_credential_id = defaultCredentialId;
    resetModelSelection(defaultCredentialId);
    nodeForm.temperature = null;
    nodeForm.max_tokens = null;
    nodeForm.system_mode = 'template';
    nodeForm.system_prompt_template_id = props.promptTemplates[0]?.id ?? null;
    nodeForm.system_prompt_version_id = null;
    nodeForm.system_inline_content = '';
    nodeForm.system_variables = {};
    nodeForm.user_mode = 'template';
    nodeForm.user_prompt_template_id = null;
    nodeForm.user_prompt_version_id = null;
    nodeForm.user_inline_content = '';
    nodeForm.user_variables = {};
    nodeForm.output_schema_definition = '';
    nodeForm.stop_on_validation_error = false;
    nodeForm.order_index = props.nodes.length + 1;
};

const openEditDrawer = (node: ChainNodePayload) => {
    nodeNameEditing.value = false;
    editingNodeId.value = node.id;
    const prompts = parseMessagesConfig(node.messages_config);
    const params = extractModelParams(node.model_params);
    nodeForm.name = node.name;
    nodeForm.provider_credential_id = node.provider_credential_id;
    resetModelSelection(node.provider_credential_id, node.model_name);
    nodeForm.temperature = params.temperature;
    nodeForm.max_tokens = params.max_tokens;
    nodeForm.system_mode = prompts.system_mode as 'template' | 'inline';
    nodeForm.system_prompt_template_id = prompts.system_prompt_template_id;
    nodeForm.system_prompt_version_id = prompts.system_prompt_version_id;
    nodeForm.system_inline_content = prompts.system_inline_content || '';
    nodeForm.system_variables = prompts.system_variables || {};
    nodeForm.user_mode = prompts.user_mode as 'template' | 'inline';
    nodeForm.user_prompt_template_id = prompts.user_prompt_template_id;
    nodeForm.user_prompt_version_id = prompts.user_prompt_version_id;
    nodeForm.user_inline_content = prompts.user_inline_content || '';
    nodeForm.user_variables = prompts.user_variables || {};
    nodeForm.output_schema_definition = node.output_schema_definition || '';
    nodeForm.stop_on_validation_error = node.stop_on_validation_error;
    nodeForm.order_index = node.order_index;
};

const saveNode = () => {
    if (nodeForm.system_mode === 'template' && !nodeForm.system_prompt_template_id) {
        nodeForm.setError('system_prompt_template_id', 'System prompt template is required');
        return;
    }
    nodeForm.clearErrors('system_prompt_template_id');

    nodeForm
        .transform((data) => ({
            name: data.name,
            provider_credential_id: data.provider_credential_id ? Number(data.provider_credential_id) : null,
            model_name: data.model_name,
            model_params: buildModelParams({ temperature: data.temperature, max_tokens: data.max_tokens }),
            messages_config: buildMessagesConfig(
                data.system_mode,
                data.system_prompt_template_id ? Number(data.system_prompt_template_id) : null,
                normalizeVersionSelection(data.system_prompt_version_id),
                data.system_inline_content,
                data.system_variables,
                data.user_mode,
                data.user_prompt_template_id ? Number(data.user_prompt_template_id) : null,
                normalizeVersionSelection(data.user_prompt_version_id),
                data.user_inline_content,
                data.user_variables,
            ),
            output_schema_definition: data.output_schema_definition || null,
            stop_on_validation_error: data.stop_on_validation_error,
            order_index: Number(data.order_index) || 1,
        }))
        [editingNodeId.value ? 'put' : 'post'](
            editingNodeId.value
                ? chainRoutes.nodes.update({
                      project: props.project.uuid,
                      chain: props.chain.id,
                      chainNode: editingNodeId.value,
                  }).url
                : chainRoutes.nodes.store({
                      project: props.project.uuid,
                      chain: props.chain.id,
                  }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Step saved',
                        detail: 'Chain step saved successfully.',
                        life: 3000,
                    });
                },
                onError: () => {
                    toast.add({
                        severity: 'error',
                        summary: 'Save failed',
                        detail: 'Please fix the errors and try again.',
                        life: 4000,
                    });
                },
            },
        );
};

const updateOrder = (node: ChainNodePayload, delta: number) => {
    const newOrder = Math.max(1, node.order_index + delta);
    router.put(
        chainRoutes.nodes.update({
            project: props.project.uuid,
            chain: props.chain.id,
            chainNode: node.id,
        }).url,
        {
            name: node.name,
            provider_credential_id: node.provider_credential_id,
            model_name: node.model_name,
            model_params: node.model_params,
            messages_config: node.messages_config,
            output_schema: node.output_schema,
            stop_on_validation_error: node.stop_on_validation_error,
            order_index: newOrder,
        },
        { preserveScroll: true },
    );
};

const deleteNode = (nodeId: number) => {
    router.delete(
        chainRoutes.nodes.destroy({
            project: props.project.uuid,
            chain: props.chain.id,
            chainNode: nodeId,
        }).url,
        { preserveScroll: true },
    );
};

const providerModalOpen = ref(false);
const providerForm = useForm({
    provider: props.providerOptions[0]?.value ?? 'openai',
    name: '',
    api_key: '',
    metadataJson: '',
});

const submitProviderCredential = () => {
    let metadata: Record<string, unknown> | null = null;

    if (providerForm.metadataJson.trim()) {
        try {
            metadata = JSON.parse(providerForm.metadataJson) as Record<string, unknown>;
            providerForm.clearErrors('metadataJson');
        } catch (error) {
            providerForm.setError('metadataJson', 'Metadata must be valid JSON');
            return;
        }
    }

    providerForm
        .transform((data) => ({
            provider: data.provider,
            name: data.name,
            api_key: data.api_key,
            metadata,
        }))
        .post(providerCredentialRoutes.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                providerModalOpen.value = false;
                providerForm.reset();
                providerForm.provider = props.providerOptions[0]?.value ?? 'openai';
                providerForm.metadataJson = '';

                router.reload({
                    only: ['providerCredentials', 'providerCredentialModels'],
                });
            },
        });
};

const runModalOpen = ref(false);
const runMode = ref<'manual' | 'dataset'>('manual');
const runForm = useForm({
    input: '{}',
});
const runDatasetForm = useForm({
    dataset_id: (props.datasets[0]?.value as number) ?? null,
});

const submitRun = () => {
    if (runMode.value === 'dataset') {
        if (!runDatasetForm.dataset_id) {
            runDatasetForm.setError('dataset_id', 'Select a dataset');
            return;
        }

        runDatasetForm.post(`/projects/${props.project.uuid}/chains/${props.chain.id}/run-dataset`, {
            preserveScroll: true,
            onSuccess: () => {
                runModalOpen.value = false;
            },
        });
        return;
    }

    runForm.post(`/projects/${props.project.uuid}/chains/${props.chain.id}/run`, {
        preserveScroll: true,
        onSuccess: () => {
            runModalOpen.value = false;
        },
    });
};
</script>

<template>
    <ProjectLayout :project="project" :title-suffix="`Chains - ${chain.name}`">
        <Toast />
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-transparent bg-transparent">
                <div class="flex flex-wrap items-center gap-3">
                    <Button variant="outline" size="sm" :href="chainRoutes.index(project.uuid).url" as="a">
                        Back to chains
                    </Button>
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-2">
                            <div v-if="chainNameEditing" class="flex items-center gap-2">
                                <Input
                                    ref="chainNameInputRef"
                                    v-model="chainForm.name"
                                    class="h-10 w-72 text-xl font-semibold"
                                    @blur="commitChainName"
                                    @keyup.enter.prevent="commitChainName"
                                />
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <h1
                                    class="cursor-pointer text-2xl font-semibold text-foreground"
                                    @click="startNameEdit"
                                >
                                    {{ chainForm.name }}
                                </h1>
                                <Button variant="ghost" size="icon" class="h-8 w-8" @click="startNameEdit">
                                    <Icon name="pencil" class="h-4 w-4 text-muted-foreground" />
                                </Button>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                aria-label="Edit description"
                                @click="openDescriptionEditor"
                            >
                                <Icon name="info" class="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="runModalOpen = true">Run</Button>
                    <Button size="sm" :disabled="chainForm.processing" @click="updateChain">Save</Button>
                </div>
            </div>
            <InputError v-if="chainForm.errors.name" :message="chainForm.errors.name" />

            <div class="rounded-lg border border-border bg-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-foreground">Steps</h3>
                        <p class="text-sm text-muted-foreground">
                            Compact view of chain nodes. Edit or add via the side panel.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="!hasProviderCredentials"
                            size="sm"
                            variant="outline"
                            @click="providerModalOpen = true"
                        >
                            Add provider
                        </Button>
                        <Button size="sm" @click="activeNodeId = 'new'">+ Add step</Button>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-12 min-h-[520px] lg:min-h-[calc(100vh-260px)]">
                    <div class="chain-timeline overflow-y-auto pr-1 lg:col-span-2">
                        <Timeline :value="timelineItems" align="left" layout="vertical" class="timeline">
                            <template #marker="slotProps">
                                <div
                                    :class="[
                                        'flex h-7 w-7 items-center justify-center rounded-full border text-xs font-semibold transition',
                                        slotProps.item.id === activeNodeId
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-background text-muted-foreground',
                                    ]"
                                >
                                    <span v-if="slotProps.item.type === 'step'">#{{ slotProps.item.order }}</span>
                                    <Icon
                                        v-else
                                        :name="slotProps.item.type === 'input' ? 'logIn' : 'logOut'"
                                        class="h-4 w-4"
                                    />
                                </div>
                            </template>
                            <template #content="slotProps">
                                <div
                                    v-if="slotProps.item.type === 'step'"
                                    :class="[
                                        'group ml-2 rounded-md border border-transparent px-3 py-2 text-sm transition hover:bg-primary/5 cursor-pointer',
                                        slotProps.item.id === activeNodeId ? 'border-l-4 border-primary bg-primary/10 shadow-sm' : 'bg-background/60',
                                    ]"
                                    @click="activeNodeId = slotProps.item.id as number"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-foreground">{{ slotProps.item.name }}</p>
                                            <p class="text-[11px] text-muted-foreground">
                                                {{ slotProps.item.model || 'No model' }} ·
                                                {{ slotProps.item.provider || 'No provider' }}
                                            </p>
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" class="h-7 w-7 opacity-0 transition hover:opacity-100 group-hover:opacity-100">
                                                    <Icon name="moreVertical" class="h-4 w-4 text-muted-foreground" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-40">
                                                <DropdownMenuItem @click="updateOrder(slotProps.item.rawNode, -1)">Move up</DropdownMenuItem>
                                                <DropdownMenuItem @click="updateOrder(slotProps.item.rawNode, 1)">Move down</DropdownMenuItem>
                                                <DropdownMenuItem class="text-destructive" @click="deleteNode(slotProps.item.rawNode.id)">
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="ml-2 rounded-md bg-background/60 px-3 py-1 text-xs font-semibold text-muted-foreground"
                                >
                                    {{ slotProps.item.name }}
                                </div>
                            </template>
                        </Timeline>
                    </div>

                    <div class="flex min-h-[520px] flex-col rounded-md border border-border bg-background/60 lg:col-span-10">
                        <div class="flex items-center justify-between border-b border-border px-3 py-2">
                            <div class="flex items-center gap-2">
                                <div v-if="nodeNameEditing" class="flex items-center gap-2">
                                    <Input
                                        ref="nodeNameInputRef"
                                        v-model="nodeForm.name"
                                        class="h-9 w-72 text-xl font-semibold"
                                        placeholder="Step name"
                                        @blur="saveNode"
                                        @keyup.enter.prevent="saveNode"
                                    />
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <h2
                                        class="cursor-pointer text-xl font-semibold text-foreground"
                                        @click="nodeNameEditing = true; nextTick(() => nodeNameInputRef?.focus())"
                                    >
                                        {{ nodeForm.name || (activeNodeId === 'new' ? 'New step' : 'Select step') }}
                                    </h2>
                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="nodeNameEditing = true; nextTick(() => nodeNameInputRef?.focus())">
                                        <Icon name="pencil" class="h-4 w-4 text-muted-foreground" />
                                    </Button>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    @click="showAvailableData = !showAvailableData"
                                >
                                    <Icon name="info" class="h-4 w-4 text-muted-foreground" />
                                </Button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto p-3">
                            <div v-if="!activeNodeId" class="flex h-full flex-col items-center justify-center gap-3 text-center">
                                <i class="pi pi-sitemap text-4xl text-muted-foreground/70"></i>
                                <div class="text-sm text-muted-foreground">Select a step to edit or create a new one</div>
                                <Button size="sm" @click="activeNodeId = 'new'">Create Step</Button>
                            </div>
                            <div v-else class="space-y-4">
                                <div class="relative">
                                    <div class="space-y-6">
                                        <div class="space-y-4 border-b border-border/60 pb-4">
                                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Step settings</p>
                                            <div class="grid gap-2">
                                                <div class="flex items-center justify-between">
                                                    <Label for="model_selection">Model</Label>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="h-6 w-6"
                                                        @click="showAdvancedModelSettings = !showAdvancedModelSettings"
                                                    >
                                                        <Icon name="settings" class="h-3.5 w-3.5 text-muted-foreground" />
                                                    </Button>
                                                </div>
                                                <Select
                                                    v-model="selectedModelValue"
                                                    inputId="model_selection"
                                                    :options="groupedModelOptions"
                                                    optionLabel="label"
                                                    optionValue="value"
                                                    optionGroupLabel="label"
                                                    optionGroupChildren="items"
                                                    placeholder="Select a model"
                                                    filter
                                                    :filterFields="['label']"
                                                    size="small"
                                                    class="w-full"
                                                    :disabled="!groupedModelOptions.length"
                                                />
                                                <p v-if="!groupedModelOptions.length" class="text-xs text-muted-foreground">
                                                    No provider credentials available yet.
                                                </p>
                                                <InputError
                                                    :message="nodeForm.errors.provider_credential_id || nodeForm.errors.model_name"
                                                />
                                            </div>

                                            <div v-if="isCustomModel" class="grid gap-2">
                                                <Label for="custom_model_name">Custom model name</Label>
                                                <Input
                                                    id="custom_model_name"
                                                    v-model="nodeForm.model_name"
                                                    name="model_name"
                                                    placeholder="custom-model-1"
                                                    required
                                                    class="py-1.5"
                                                />
                                                <InputError :message="nodeForm.errors.model_name" />
                                            </div>

                                            <div v-if="showAdvancedModelSettings" class="grid gap-3 md:grid-cols-2">
                                                <div class="grid gap-2">
                                                    <Label for="temperature">Temperature</Label>
                                                    <Input
                                                        id="temperature"
                                                        type="number"
                                                        step="0.1"
                                                        min="0"
                                                        max="2"
                                                        v-model.number="nodeForm.temperature"
                                                        name="temperature"
                                                        class="py-1.5"
                                                    />
                                                </div>

                                                <div class="grid gap-2">
                                                    <Label for="max_tokens">Max tokens</Label>
                                                    <Input
                                                        id="max_tokens"
                                                        type="number"
                                                        min="1"
                                                        v-model.number="nodeForm.max_tokens"
                                                        name="max_tokens"
                                                        class="py-1.5"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-4 border-b border-border/60 pb-4">
                                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Prompt config</p>
                                            <div class="grid gap-4 lg:grid-cols-2">
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <Label>System prompt</Label>
                                                        <SelectButton
                                                            v-model="nodeForm.system_mode"
                                                            :options="promptModeOptions"
                                                            option-label="label"
                                                            option-value="value"
                                                            size="small"
                                                        />
                                                    </div>
                                                    <div v-if="nodeForm.system_mode === 'template'" class="grid w-full gap-2 md:grid-cols-2">
                                                        <Select
                                                            v-model="nodeForm.system_prompt_template_id"
                                                            :options="templateOptions"
                                                            option-label="label"
                                                            option-value="value"
                                                            placeholder="Select template"
                                                            filter
                                                            size="small"
                                                            class="w-full"
                                                        />
                                                        <Select
                                                            v-model="nodeForm.system_prompt_version_id"
                                                            :options="systemVersionOptions"
                                                            option-label="label"
                                                            option-value="value"
                                                            placeholder="Latest"
                                                            filter
                                                            size="small"
                                                            class="w-full"
                                                            :disabled="!nodeForm.system_prompt_template_id"
                                                        />
                                                    </div>
                                                    <div
                                                        v-else
                                                        class="flex w-full items-center rounded-md border border-dashed border-border/70 bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground"
                                                    >
                                                        Manual input
                                                    </div>
                                                    <div class="relative">
                                                        <PromptEditor
                                                            :model-value="
                                                                nodeForm.system_mode === 'template'
                                                                    ? systemPromptText
                                                                    : nodeForm.system_inline_content
                                                            "
                                                            v-model:mode="systemEditorMode"
                                                            v-model:preset="systemEditorPreset"
                                                            :read-only="nodeForm.system_mode === 'template'"
                                                            placeholder="Write system prompt with {{variables}}"
                                                            height="220px"
                                                            show-controls
                                                            @update:model-value="
                                                                (value) => {
                                                                    if (nodeForm.system_mode === 'inline') {
                                                                        nodeForm.system_inline_content = value;
                                                                    }
                                                                }
                                                            "
                                                        />
                                                        <span
                                                            v-if="nodeForm.system_mode === 'template'"
                                                            class="pointer-events-none absolute right-3 top-9 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
                                                        >
                                                            Read-only template
                                                        </span>
                                                    </div>
                                                    <InputError :message="nodeForm.errors['messages_config.0.inline_content']" />
                                                    <InputError
                                                        :message="
                                                            nodeForm.errors['messages_config.0.prompt_template_id'] ||
                                                            nodeForm.errors['messages_config.0.prompt_version_id'] ||
                                                            nodeForm.errors['messages_config.0.role'] ||
                                                            nodeForm.errors.messages_config ||
                                                            nodeForm.errors.system_prompt_template_id
                                                        "
                                                    />
                                                    <div v-if="variableRowsSystem.length" class="space-y-2">
                                                        <div class="flex items-center justify-between">
                                                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">System variables</p>
                                                            <span
                                                                v-if="variablesMissingMapping.system.length"
                                                                class="text-[11px] text-amber-600"
                                                            >
                                                                Incomplete mappings
                                                            </span>
                                                        </div>
                                                        <div class="flex flex-wrap gap-2">
                                                            <button
                                                                v-for="row in variableRowsSystem"
                                                                :key="row.name"
                                                                type="button"
                                                                class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-xs font-semibold text-foreground transition hover:border-primary/60 hover:bg-primary/5"
                                                                :class="!row.mappingText ? 'border-rose-300' : 'border-border/60'"
                                                                @click="openMappingStudio('system', row.name)"
                                                            >
                                                                <span>{{ row.name }}</span>
                                                                <span v-if="row.mappingText" class="text-xs font-normal text-muted-foreground">
                                                                    {{ row.mappingText }}
                                                                </span>
                                                                <Icon
                                                                    v-if="!row.mappingText"
                                                                    name="unlink"
                                                                    class="h-3.5 w-3.5 text-amber-600"
                                                                />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <Label>User prompt</Label>
                                                        <SelectButton
                                                            v-model="nodeForm.user_mode"
                                                            :options="promptModeOptions"
                                                            option-label="label"
                                                            option-value="value"
                                                            size="small"
                                                        />
                                                    </div>
                                                    <div v-if="nodeForm.user_mode === 'template'" class="grid w-full gap-2 md:grid-cols-2">
                                                        <Select
                                                            v-model="nodeForm.user_prompt_template_id"
                                                            :options="userTemplateOptions"
                                                            option-label="label"
                                                            option-value="value"
                                                            placeholder="No user prompt"
                                                            filter
                                                            size="small"
                                                            class="w-full"
                                                        />
                                                        <Select
                                                            v-model="nodeForm.user_prompt_version_id"
                                                            :options="userVersionOptions"
                                                            option-label="label"
                                                            option-value="value"
                                                            placeholder="Latest"
                                                            filter
                                                            size="small"
                                                            class="w-full"
                                                            :disabled="!nodeForm.user_prompt_template_id"
                                                        />
                                                    </div>
                                                    <div
                                                        v-else
                                                        class="flex w-full items-center rounded-md border border-dashed border-border/70 bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground"
                                                    >
                                                        Manual input
                                                    </div>
                                                    <div class="relative">
                                                        <PromptEditor
                                                            :model-value="
                                                                nodeForm.user_mode === 'template'
                                                                    ? userPromptText
                                                                    : nodeForm.user_inline_content
                                                            "
                                                            v-model:mode="userEditorMode"
                                                            v-model:preset="userEditorPreset"
                                                            :read-only="nodeForm.user_mode === 'template'"
                                                            placeholder="Write user prompt with {{variables}}"
                                                            height="220px"
                                                            show-controls
                                                            @update:model-value="
                                                                (value) => {
                                                                    if (nodeForm.user_mode === 'inline') {
                                                                        nodeForm.user_inline_content = value;
                                                                    }
                                                                }
                                                            "
                                                        />
                                                        <span
                                                            v-if="nodeForm.user_mode === 'template'"
                                                            class="pointer-events-none absolute right-3 top-9 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
                                                        >
                                                            Read-only template
                                                        </span>
                                                    </div>
                                                    <InputError :message="nodeForm.errors['messages_config.1.inline_content']" />
                                                    <InputError
                                                        :message="
                                                            nodeForm.errors['messages_config.1.prompt_template_id'] ||
                                                            nodeForm.errors['messages_config.1.prompt_version_id'] ||
                                                            nodeForm.errors['messages_config.1.role'] ||
                                                            nodeForm.errors.messages_config
                                                        "
                                                    />
                                                    <div v-if="variableRowsUser.length" class="space-y-2">
                                                        <div class="flex items-center justify-between">
                                                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">User variables</p>
                                                            <span
                                                                v-if="variablesMissingMapping.user.length"
                                                                class="text-[11px] text-amber-600"
                                                            >
                                                                Incomplete mappings
                                                            </span>
                                                        </div>
                                                        <div class="flex flex-wrap gap-2">
                                                            <button
                                                                v-for="row in variableRowsUser"
                                                                :key="row.name"
                                                                type="button"
                                                                class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-xs font-semibold text-foreground transition hover:border-primary/60 hover:bg-primary/5"
                                                                :class="!row.mappingText ? 'border-rose-300' : 'border-border/60'"
                                                                @click="openMappingStudio('user', row.name)"
                                                            >
                                                                <span>{{ row.name }}</span>
                                                                <span v-if="row.mappingText" class="text-xs font-normal text-muted-foreground">
                                                                    {{ row.mappingText }}
                                                                </span>
                                                                <Icon
                                                                    v-if="!row.mappingText"
                                                                    name="unlink"
                                                                    class="h-3.5 w-3.5 text-amber-600"
                                                                />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-4 border-b border-border/60 pb-4">
                                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Output + behavior</p>
                                            <div class="grid gap-2">
                                                <Label for="output_schema">Output schema (TS-like, optional)</Label>
                                                <textarea
                                                    id="output_schema"
                                                    v-model="nodeForm.output_schema_definition"
                                                    name="output_schema_definition"
                                                    rows="6"
                                                    placeholder='{\n  question: string;\n  answers: string[];\n  explanation?: string;\n  difficulty: "easy" | "medium" | "hard";\n}'
                                                    class="w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                                                ></textarea>
                                                <InputError :message="nodeForm.errors.output_schema_definition" />
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <Checkbox id="stop_on_validation_error" v-model:checked="nodeForm.stop_on_validation_error" />
                                                <Label for="stop_on_validation_error">Stop on validation error</Label>
                                            </div>

                                        </div>

                                        <div class="flex items-center justify-end gap-2">
                                            <Button variant="outline" @click="activeNodeId = null">Cancel</Button>
                                            <Button :disabled="nodeForm.processing" @click="saveNode">
                                                {{ editingNodeId ? 'Save changes' : 'Create step' }}
                                            </Button>
                                        </div>
                                    </div>

                                    <div
                                        v-if="showAvailableData"
                                        class="absolute right-0 top-0 z-10 h-full w-full max-w-sm border-l border-border/60 bg-background/95 p-3 backdrop-blur"
                                    >
                                        <div class="flex items-center justify-between border-b border-border/60 pb-2">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Available data</span>
                                            <Button variant="ghost" size="icon" class="h-7 w-7" @click="showAvailableData = false">
                                                <Icon name="x" class="h-4 w-4 text-muted-foreground" />
                                            </Button>
                                        </div>
                                        <div class="mt-2 text-[11px] text-muted-foreground">Order {{ currentOrderIndex }}</div>
                                        <Input
                                            v-model="availableDataSearch"
                                            placeholder="Search fields..."
                                            class="mt-3"
                                        />
                                        <Tree
                                            class="mt-3 w-full font-mono text-xs"
                                            :value="filteredAvailableDataTree"
                                            selectionMode="single"
                                            @node-select="onTreeSelect"
                                        >
                                            <template #default="{ node }">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-sans text-foreground">{{ node.label }}</span>
                                                    <span v-if="node.data?.path" class="text-[11px] text-muted-foreground">
                                                        {{ node.data.path }}
                                                    </span>
                                                </div>
                                            </template>
                                        </Tree>
                                        <div class="mt-4 flex justify-end">
                                            <Button variant="outline" size="sm" @click="showAvailableData = false">
                                                Close
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ProjectLayout>

    <Dialog :open="mappingStudioOpen" @update:open="mappingStudioOpen = $event">
        <DialogContent class="w-[90vw] sm:max-w-7xl">
            <DialogHeader>
                <DialogTitle>Variable Mapper</DialogTitle>
                <DialogDescription>Map required variables to input or previous step outputs.</DialogDescription>
            </DialogHeader>
            <div class="flex h-[70vh] gap-6">
                <div class="flex w-[360px] flex-col gap-3">
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Available Data
                    </div>
                    <Input
                        v-model="mappingStudioSearch"
                        placeholder="Search fields..."
                        class="text-sm"
                    />
                    <div class="flex-1 overflow-y-auto overflow-x-auto">
                        <Tree
                            class="w-full font-mono text-xs"
                            :value="filteredStudioDataTree"
                            selectionMode="single"
                            @node-select="onStudioTreeSelect"
                        >
                            <template #default="{ node }">
                                <div class="flex items-center gap-2 border-l border-border/30 pl-2">
                                    <span class="text-sm font-sans text-foreground">{{ node.label }}</span>
                                    <span
                                        v-if="node.data?.path"
                                        class="cursor-grab text-[11px] text-muted-foreground"
                                    >
                                        {{ node.data.path }}
                                    </span>
                                    <button
                                        v-if="node.data?.path"
                                        type="button"
                                        class="ml-1 cursor-grab text-muted-foreground transition hover:text-foreground"
                                        draggable="true"
                                        title="Drag to map"
                                        @dragstart.stop="(event) => handleTreeDragStart(event, node.data?.path)"
                                    >
                                        <Icon name="move" class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        v-if="node.data?.path"
                                        type="button"
                                        class="ml-auto text-muted-foreground transition hover:text-foreground"
                                        @click.stop="copyPath(node.data.path)"
                                    >
                                        <Icon name="copy" class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </template>
                        </Tree>
                    </div>
                </div>
                <div class="hidden w-px bg-border/60 lg:block"></div>
                <div class="flex flex-1 flex-col gap-3">
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Required Mappings
                    </div>
                    <div class="flex-1 space-y-4 overflow-y-auto pr-2">
                        <button
                            v-for="row in mappingRowsStudio"
                            :key="`${row.role}:${row.name}`"
                            type="button"
                            class="w-full rounded-md px-2 py-2 text-left transition"
                            :class="mappingTarget?.role === row.role && mappingTarget?.name === row.name ? 'bg-muted/40' : ''"
                            @click="mappingTarget = { role: row.role, name: row.name }"
                        >
                            <div class="flex min-h-[56px] items-center gap-2 text-xs font-mono text-muted-foreground">
                                <span>{{ row.snippet.prefix }}</span>
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-900">
                                    {{ row.snippet.match }}
                                </span>
                                <span>{{ row.snippet.suffix }}</span>
                                <Icon v-if="row.mappingText" name="check" class="ml-auto h-4 w-4 text-emerald-600" />
                            </div>
                            <div class="mt-2">
                                <div
                                    class="rounded-md"
                                    @dragover.prevent
                                    @drop="(event) => handleMappingDrop(event, row.role, row.name)"
                                >
                                    <Input
                                        :model-value="row.mappingText"
                                        placeholder="Click a data node on the left..."
                                        class="text-xs font-mono"
                                        :class="[
                                            row.mappingText ? 'text-blue-600' : '',
                                            mappingFlashKey === `${row.role}:${row.name}` ? 'ring-2 ring-emerald-300/60' : '',
                                            mappingTarget?.role === row.role && mappingTarget?.name === row.name
                                                ? 'ring-1 ring-primary/30'
                                                : '',
                                        ]"
                                        @update:model-value="(value) => applyMappingText(row.role, row.name, value)"
                                        @focus="mappingTarget = { role: row.role, name: row.name }"
                                    />
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
            <DialogFooter class="flex items-center justify-end gap-2">
                <Button variant="outline" @click="mappingStudioOpen = false">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="providerModalOpen" @update:open="providerModalOpen = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Add provider credential</DialogTitle>
                <DialogDescription>Save a new credential to use for chain steps.</DialogDescription>
            </DialogHeader>

            <div class="space-y-3">
                <div class="grid gap-2">
                    <Label for="provider">Provider</Label>
                    <select
                        id="provider"
                        v-model="providerForm.provider"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option v-for="provider in props.providerOptions" :key="provider.value" :value="provider.value">
                            {{ provider.label }}
                        </option>
                    </select>
                    <InputError :message="providerForm.errors.provider" />
                </div>

                <div class="grid gap-2">
                    <Label for="provider_name">Name</Label>
                    <Input
                        id="provider_name"
                        v-model="providerForm.name"
                        name="provider_name"
                        placeholder="OpenAI Sandbox"
                        required
                    />
                    <InputError :message="providerForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="provider_api_key">API key</Label>
                    <Input
                        id="provider_api_key"
                        type="password"
                        v-model="providerForm.api_key"
                        name="provider_api_key"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="providerForm.errors.api_key" />
                </div>

                <div class="grid gap-2">
                    <Label for="provider_metadata">Metadata (JSON, optional)</Label>
                    <textarea
                        id="provider_metadata"
                        v-model="providerForm.metadataJson"
                        rows="3"
                        placeholder='{"baseUrl":"https://api.openai.com/v1"}'
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    ></textarea>
                    <InputError :message="providerForm.errors.metadataJson" />
                </div>
            </div>

            <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                <Button variant="outline" @click="providerModalOpen = false">Cancel</Button>
                <Button :disabled="providerForm.processing" @click="submitProviderCredential">Save provider</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="descriptionModalOpen" @update:open="descriptionModalOpen = $event">
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
                        v-model="chainForm.description"
                        name="description"
                        rows="3"
                        placeholder="Short description of the chain"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    ></textarea>
                    <InputError :message="chainForm.errors.description" />
                </div>
            </div>

            <DialogFooter class="flex items-center justify-end gap-2 pt-2">
                <Button variant="outline" @click="cancelDescriptionEdit">Cancel</Button>
                <Button :disabled="chainForm.processing" @click="saveDescription">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="runModalOpen" @update:open="runModalOpen = $event">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Run chain</DialogTitle>
                <DialogDescription>Provide input manually or run against a dataset.</DialogDescription>
            </DialogHeader>

            <div class="flex items-center gap-2 rounded-md border border-border p-1 text-sm">
                <Button
                    variant="ghost"
                    size="sm"
                    :class="runMode === 'manual' ? 'bg-primary/10 text-primary' : ''"
                    @click="runMode = 'manual'"
                >
                    Manual input
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    :class="runMode === 'dataset' ? 'bg-primary/10 text-primary' : ''"
                    @click="runMode = 'dataset'"
                >
                    Dataset
                </Button>
            </div>

            <div v-if="runMode === 'manual'" class="space-y-3">
                <div class="grid gap-2">
                    <Label for="run_input">Input (JSON)</Label>
                    <textarea
                        id="run_input"
                        v-model="runForm.input"
                        name="input"
                        rows="6"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    ></textarea>
                    <InputError :message="runForm.errors.input" />
                </div>
            </div>

            <div v-else class="space-y-3">
                <div class="grid gap-2">
                    <Label for="dataset_id">Dataset</Label>
                    <select
                        id="dataset_id"
                        v-model.number="runDatasetForm.dataset_id"
                        name="dataset_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option value="">Select dataset</option>
                        <option v-for="dataset in props.datasets" :key="dataset.value" :value="dataset.value">
                            {{ dataset.label }}
                        </option>
                    </select>
                    <InputError :message="runDatasetForm.errors.dataset_id" />
                    <p v-if="!hasDatasets" class="text-xs text-muted-foreground">No datasets available.</p>
                </div>
            </div>

            <DialogFooter class="flex items-center justify-end gap-2">
                <Button variant="outline" @click="runModalOpen = false">Cancel</Button>
                <Button
                    :disabled="runMode === 'dataset' ? runDatasetForm.processing : runForm.processing"
                    @click="submitRun"
                >
                    {{ runMode === 'dataset' ? 'Run on dataset' : 'Run' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

</template>

<style scoped>
:global(.chain-timeline .p-timeline-event-opposite) {
    flex: 0;
}
</style>
