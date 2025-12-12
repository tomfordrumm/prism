<script setup lang="ts">
import ProjectLayout from '@/layouts/app/ProjectLayout.vue';
import chainRoutes from '@/routes/projects/chains';
import providerCredentialRoutes from '@/routes/provider-credentials';
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

import Icon from '@/components/Icon.vue';
import InputError from '@/components/InputError.vue';
import Card from 'primevue/card';
import SelectButton from 'primevue/selectbutton';
import Timeline from 'primevue/timeline';
import Tree from 'primevue/tree';
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

const updateChain = () => {
    chainForm
        .transform((data) => ({
            name: data.name,
            description: data.description || null,
        }))
        .put(chainRoutes.update({ project: props.project.id, chain: props.chain.id }).url, {
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

interface FlatTreeNode extends TreeNode {
    level: number;
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

const flattenTree = (nodes: TreeNode[], level = 0): FlatTreeNode[] =>
    nodes.flatMap((node) => [
        { ...node, level },
        ...flattenTree(node.children ?? [], level + 1),
    ]);

const inputTreeFlat = computed(() => flattenTree(inputTree.value));
const stepsTreeFlat = computed(() =>
    stepsTree.value.flatMap((root) => flattenTree([root], 0)),
);

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

const onTreeSelect = (event: { node: PrimeTreeNode }) => {
    const path = event.node?.data?.path;
    if (path) {
        insertPlaceholder(path);
    }
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

const selectedVariable = ref<{ role: 'system' | 'user'; name: string } | null>(null);

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

const onTreeLeafClick = (node: FlatTreeNode) => {
    const selection = selectedVariable.value;
    if (!selection) {
        insertPlaceholder(node.path);
        return;
    }

    if (node.path.startsWith('input.')) {
        const path = node.path.replace(/^input\./, '');
        updateMapping(selection.role, selection.name, { source: 'input', path });
    } else if (node.path.startsWith('steps.')) {
        const rest = node.path.replace(/^steps\./, '');
        const [stepKey, ...pathParts] = rest.split('.');
        const path = pathParts.join('.') || null;
        updateMapping(selection.role, selection.name, {
            source: 'previous_step',
            step_key: stepKey,
            path: path || undefined,
        });
    }
};

const variablesMissingMapping = computed(() => {
    const collectMissing = (rows: { name: string; mapping: VariableMapping }[]) =>
        rows
            .filter(
                (row) =>
                    !row.mapping.source ||
                    (row.mapping.source === 'previous_step' && !row.mapping.step_key) ||
                    (row.mapping.source !== 'constant' && !row.mapping.path && row.mapping.source !== 'previous_step'),
            )
            .map((row) => row.name);

    return {
        system: collectMissing(variableRowsSystem.value),
        user: collectMissing(variableRowsUser.value),
    };
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

const getModelsForCredential = (credentialId: number | null): ModelOption[] => {
    if (!credentialId) return [];

    return props.providerCredentialModels[credentialId] ?? [];
};

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
    const defaultCredentialId = (props.providerCredentials[0]?.value as number | null) ?? null;
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
const currentModelOptions = computed(() => getModelsForCredential(nodeForm.provider_credential_id));
const isCustomModel = computed(() => nodeForm.model_choice === CUSTOM_MODEL_VALUE);

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

const handleProviderChange = () => {
    resetModelSelection(
        nodeForm.provider_credential_id,
        isCustomModel.value ? nodeForm.model_name : null,
        isCustomModel.value,
    );
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
    const defaultCredentialId = (props.providerCredentials[0]?.value as number | null) ?? null;
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
                      project: props.project.id,
                      chain: props.chain.id,
                      chainNode: editingNodeId.value,
                  }).url
                : chainRoutes.nodes.store({
                      project: props.project.id,
                      chain: props.chain.id,
                  }).url,
            {
                preserveScroll: true,
            },
        );
};

const updateOrder = (node: ChainNodePayload, delta: number) => {
    const newOrder = Math.max(1, node.order_index + delta);
    router.put(
        chainRoutes.nodes.update({
            project: props.project.id,
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
            project: props.project.id,
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

        runDatasetForm.post(`/projects/${props.project.id}/chains/${props.chain.id}/run-dataset`, {
            preserveScroll: true,
            onSuccess: () => {
                runModalOpen.value = false;
            },
        });
        return;
    }

    runForm.post(`/projects/${props.project.id}/chains/${props.chain.id}/run`, {
        preserveScroll: true,
        onSuccess: () => {
            runModalOpen.value = false;
        },
    });
};
</script>

<template>
    <ProjectLayout :project="project" :title-suffix="`Chains - ${chain.name}`">
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-transparent bg-transparent">
                <div class="flex flex-wrap items-center gap-3">
                    <Button variant="outline" size="sm" :href="chainRoutes.index(project.id).url" as="a">
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
                                    @click="showAdvancedModelSettings = !showAdvancedModelSettings"
                                >
                                    <Icon name="settings" class="h-4 w-4 text-muted-foreground" />
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
                                <div class="grid gap-4 lg:grid-cols-[7fr_3fr]">
                                    <div class="space-y-4">
                                        <div class="grid gap-2 md:grid-cols-2">
                                            <div class="grid gap-2">
                                                <Label for="provider_credential_id">Provider credential</Label>
                                                <select
                                                    id="provider_credential_id"
                                                    v-model.number="nodeForm.provider_credential_id"
                                                    name="provider_credential_id"
                                                    @change="handleProviderChange"
                                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                >
                                                    <option v-if="!hasProviderCredentials" :value="null">No credentials available</option>
                                                    <option
                                                        v-for="credential in props.providerCredentials"
                                                        :key="credential.value"
                                                        :value="credential.value"
                                                    >
                                                        {{ credential.label }}
                                                    </option>
                                                </select>
                                                <InputError :message="nodeForm.errors.provider_credential_id" />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label for="model_name">Model</Label>
                                                <select
                                                    id="model_name"
                                                    v-model="nodeForm.model_choice"
                                                    name="model_choice"
                                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                >
                                                    <option v-for="model in currentModelOptions" :key="model.id" :value="model.id">
                                                        {{ model.display_name }} ({{ model.name }})
                                                    </option>
                                                    <option :value="CUSTOM_MODEL_VALUE">Custom...</option>
                                                </select>
                                                <p v-if="!currentModelOptions.length" class="text-xs text-muted-foreground">
                                                    No models available for this provider yet.
                                                </p>
                                                <InputError v-if="!isCustomModel" :message="nodeForm.errors.model_name" />
                                            </div>
                                        </div>

                                        <div v-if="isCustomModel" class="grid gap-2">
                                            <Label for="custom_model_name">Custom model name</Label>
                                            <Input
                                                id="custom_model_name"
                                                v-model="nodeForm.model_name"
                                                name="model_name"
                                                placeholder="custom-model-1"
                                                required
                                            />
                                            <InputError :message="nodeForm.errors.model_name" />
                                        </div>

                                        <div v-if="showAdvancedModelSettings" class="grid gap-4 md:grid-cols-2">
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
                                                />
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <Label>System prompt</Label>
                                            <SelectButton
                                                v-model="nodeForm.system_mode"
                                                :options="promptModeOptions"
                                                option-label="label"
                                                option-value="value"
                                            />
                                            <div v-if="nodeForm.system_mode === 'template'" class="grid gap-2">
                                                <div class="grid gap-2 md:grid-cols-2">
                                                    <select
                                                        v-model.number="nodeForm.system_prompt_template_id"
                                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                        required
                                                    >
                                                        <option value="">Select template</option>
                                                        <option v-for="template in templateOptions" :key="template.value" :value="template.value">
                                                            {{ template.label }}
                                                        </option>
                                                    </select>
                                                    <select
                                                        v-model="nodeForm.system_prompt_version_id"
                                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                        :disabled="!nodeForm.system_prompt_template_id"
                                                    >
                                                        <option v-for="version in systemVersionOptions" :key="version.value" :value="version.value">
                                                            {{ version.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div v-else class="grid gap-2">
                                                <Label class="text-xs text-muted-foreground">Prompt text</Label>
                                                <textarea
                                                    v-model="nodeForm.system_inline_content"
                                                    rows="6"
                                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                    placeholder="Write system prompt with {{variables}}"
                                                ></textarea>
                                                <InputError :message="nodeForm.errors['messages_config.0.inline_content']" />
                                            </div>
                                            <InputError
                                                :message="
                                                    nodeForm.errors['messages_config.0.prompt_template_id'] ||
                                                    nodeForm.errors['messages_config.0.prompt_version_id'] ||
                                                    nodeForm.errors['messages_config.0.role'] ||
                                                    nodeForm.errors.messages_config ||
                                                    nodeForm.errors.system_prompt_template_id
                                                "
                                            />
                                        </div>

                                        <div class="rounded-md border border-border/80 bg-background p-3">
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-semibold text-foreground">Variable mapping</p>
                                                <span
                                                    v-if="variablesMissingMapping.system.length"
                                                    class="text-[11px] text-amber-600"
                                                >
                                                    Incomplete mappings
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                Select a row to fill via explorer or edit manually.
                                            </p>
                                            <div class="mt-2 overflow-hidden rounded-md border border-border/60">
                                                <div class="grid grid-cols-3 bg-muted px-2 py-1 text-[11px] font-semibold uppercase text-muted-foreground">
                                                    <span>Variable</span>
                                                    <span>Source</span>
                                                    <span>Value / Path</span>
                                                </div>
                                                <div
                                                    v-for="row in variableRowsSystem"
                                                    :key="row.name"
                                                    class="grid grid-cols-3 items-center gap-1 px-2 py-1 text-xs"
                                                    :class="selectedVariable?.role === 'system' && selectedVariable?.name === row.name ? 'bg-primary/10' : ''"
                                                    @click="selectedVariable = { role: 'system', name: row.name }"
                                                >
                                                    <span class="font-semibold text-foreground">{{ row.name }}</span>
                                                    <select
                                                        v-model="row.mapping.source"
                                                        class="w-full rounded border border-input bg-background px-2 py-1 text-xs text-foreground"
                                                        @change="updateMapping('system', row.name, { source: row.mapping.source as VariableSource })"
                                                    >
                                                        <option value="">Select</option>
                                                        <option value="input">Input</option>
                                                        <option value="previous_step">Previous step</option>
                                                        <option value="constant">Constant</option>
                                                    </select>
                                                    <div class="flex items-center gap-1">
                                                        <select
                                                            v-if="row.mapping.source === 'previous_step'"
                                                            v-model="row.mapping.step_key"
                                                            class="w-1/2 rounded border border-input bg-background px-2 py-1 text-xs text-foreground"
                                                            @change="updateMapping('system', row.name, { step_key: row.mapping.step_key })"
                                                        >
                                                            <option value="">Step</option>
                                                            <option
                                                                v-for="step in previousSteps"
                                                                :key="step.key"
                                                                :value="step.key"
                                                            >
                                                                {{ step.name }}
                                                            </option>
                                                        </select>
                                                        <input
                                                            v-if="row.mapping.source === 'constant'"
                                                            v-model="row.mapping.value"
                                                            class="w-full rounded border border-input bg-background px-2 py-1 text-xs text-foreground"
                                                            placeholder="Value"
                                                            @input="updateMapping('system', row.name, { value: row.mapping.value })"
                                                        />
                                                        <input
                                                            v-else
                                                            v-model="row.mapping.path"
                                                            class="w-full rounded border border-input bg-background px-2 py-1 text-xs text-foreground"
                                                            placeholder="Path"
                                                            @input="updateMapping('system', row.name, { path: row.mapping.path })"
                                                        />
                                                    </div>
                                                </div>
                                                <div v-if="variableRowsSystem.length === 0" class="px-2 py-2 text-xs text-muted-foreground">
                                                    No variables detected for this template.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <Label>User prompt (optional)</Label>
                                            <SelectButton
                                                v-model="nodeForm.user_mode"
                                                :options="promptModeOptions"
                                                option-label="label"
                                                option-value="value"
                                            />
                                            <div v-if="nodeForm.user_mode === 'template'" class="grid gap-2">
                                                <div class="grid gap-2 md:grid-cols-2">
                                                    <select
                                                        v-model.number="nodeForm.user_prompt_template_id"
                                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                    >
                                                        <option value="">No user prompt</option>
                                                        <option v-for="template in templateOptions" :key="template.value" :value="template.value">
                                                            {{ template.label }}
                                                        </option>
                                                    </select>
                                                    <select
                                                        v-model="nodeForm.user_prompt_version_id"
                                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                        :disabled="!nodeForm.user_prompt_template_id"
                                                    >
                                                        <option v-for="version in userVersionOptions" :key="version.value" :value="version.value">
                                                            {{ version.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div v-else class="grid gap-2">
                                                <Label class="text-xs text-muted-foreground">Prompt text</Label>
                                                <textarea
                                                    v-model="nodeForm.user_inline_content"
                                                    rows="6"
                                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                                    placeholder="Write user prompt with {{variables}}"
                                                ></textarea>
                                                <InputError :message="nodeForm.errors['messages_config.1.inline_content']" />
                                            </div>
                                            <InputError
                                                :message="
                                                    nodeForm.errors['messages_config.1.prompt_template_id'] ||
                                                    nodeForm.errors['messages_config.1.prompt_version_id'] ||
                                                    nodeForm.errors['messages_config.1.role'] ||
                                                    nodeForm.errors.messages_config
                                                "
                                            />
                                        </div>

                                        <div class="rounded-md border border-border/80 bg-background p-3">
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-semibold text-foreground">Variable mapping (user)</p>
                                                <span
                                                    v-if="variablesMissingMapping.user.length"
                                                    class="text-[11px] text-amber-600"
                                                >
                                                    Incomplete mappings
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                Select a row to fill via explorer or edit manually.
                                            </p>
                                            <div class="mt-2 overflow-hidden rounded-md border border-border/60">
                                                <div class="grid grid-cols-3 bg-muted px-2 py-1 text-[11px] font-semibold uppercase text-muted-foreground">
                                                    <span>Variable</span>
                                                    <span>Source</span>
                                                    <span>Value / Path</span>
                                                </div>
                                                <div
                                                    v-for="row in variableRowsUser"
                                                    :key="row.name"
                                                    class="grid grid-cols-3 items-center gap-1 px-2 py-1 text-xs"
                                                    :class="selectedVariable?.role === 'user' && selectedVariable?.name === row.name ? 'bg-primary/10' : ''"
                                                    @click="selectedVariable = { role: 'user', name: row.name }"
                                                >
                                                    <span class="font-semibold text-foreground">{{ row.name }}</span>
                                                    <select
                                                        v-model="row.mapping.source"
                                                        class="w-full rounded border border-input bg-background px-2 py-1 text-xs text-foreground"
                                                        @change="updateMapping('user', row.name, { source: row.mapping.source as VariableSource })"
                                                    >
                                                        <option value="">Select</option>
                                                        <option value="input">Input</option>
                                                        <option value="previous_step">Previous step</option>
                                                        <option value="constant">Constant</option>
                                                    </select>
                                                    <div class="flex items-center gap-1">
                                                        <select
                                                            v-if="row.mapping.source === 'previous_step'"
                                                            v-model="row.mapping.step_key"
                                                            class="w-1/2 rounded border border-input bg-background px-2 py-1 text-xs text-foreground"
                                                            @change="updateMapping('user', row.name, { step_key: row.mapping.step_key })"
                                                        >
                                                            <option value="">Step</option>
                                                            <option
                                                                v-for="step in previousSteps"
                                                                :key="step.key"
                                                                :value="step.key"
                                                            >
                                                                {{ step.name }}
                                                            </option>
                                                        </select>
                                                        <input
                                                            v-if="row.mapping.source === 'constant'"
                                                            v-model="row.mapping.value"
                                                            class="w-full rounded border border-input bg-background px-2 py-1 text-xs text-foreground"
                                                            placeholder="Value"
                                                            @input="updateMapping('user', row.name, { value: row.mapping.value })"
                                                        />
                                                        <input
                                                            v-else
                                                            v-model="row.mapping.path"
                                                            class="w-full rounded border border-input bg-background px-2 py-1 text-xs text-foreground"
                                                            placeholder="Path"
                                                            @input="updateMapping('user', row.name, { path: row.mapping.path })"
                                                        />
                                                    </div>
                                                </div>
                                                <div v-if="variableRowsUser.length === 0" class="px-2 py-2 text-xs text-muted-foreground">
                                                    No variables detected for this template.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="output_schema">Output schema (TS-like, optional)</Label>
                                            <textarea
                                                id="output_schema"
                                                v-model="nodeForm.output_schema_definition"
                                                name="output_schema_definition"
                                                rows="6"
                                                placeholder='{\n  question: string;\n  answers: string[];\n  explanation?: string;\n  difficulty: "easy" | "medium" | "hard";\n}'
                                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                            ></textarea>
                                            <InputError :message="nodeForm.errors.output_schema_definition" />
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <Checkbox id="stop_on_validation_error" v-model:checked="nodeForm.stop_on_validation_error" />
                                            <Label for="stop_on_validation_error">Stop on validation error</Label>
                                        </div>

                                        <div class="grid gap-2 md:w-32">
                                            <Label for="order_index">Order</Label>
                                            <Input
                                                id="order_index"
                                                type="number"
                                                min="1"
                                                v-model.number="nodeForm.order_index"
                                                name="order_index"
                                            />
                                            <InputError :message="nodeForm.errors.order_index" />
                                        </div>

                                        <div class="flex items-center justify-end gap-2">
                                            <Button variant="outline" @click="activeNodeId = null">Cancel</Button>
                                            <Button :disabled="nodeForm.processing" @click="saveNode">
                                                {{ editingNodeId ? 'Save changes' : 'Create step' }}
                                            </Button>
                                        </div>
                                    </div>

                                    <Card class="lg:sticky lg:top-4 h-fit bg-muted/40">
                                        <template #title>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm font-semibold text-foreground">Available Data</span>
                                                <span class="text-[11px] text-muted-foreground">Order {{ currentOrderIndex }}</span>
                                            </div>
                                        </template>
                                        <template #content>
                                            <Tree
                                                class="w-full"
                                                :value="availableDataTree"
                                                selectionMode="single"
                                                @node-select="onTreeSelect"
                                            />
                                        </template>
                                    </Card>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ProjectLayout>

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
