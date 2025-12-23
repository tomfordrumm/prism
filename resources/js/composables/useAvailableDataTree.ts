import { computed, type Ref } from 'vue';
import type { ContextSample, ContextStepSample } from '@/types/chains';

interface TreeNode {
    label: string;
    path: string;
    type: string;
    children?: TreeNode[];
}

export interface PrimeTreeNode {
    key: string;
    label: string;
    data?: { path?: string; type?: string };
    children?: PrimeTreeNode[];
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

const isPrimeTreeNode = (node: PrimeTreeNode | null): node is PrimeTreeNode => node !== null;

const filterTreeNodes = (nodes: PrimeTreeNode[], term: string): PrimeTreeNode[] => {
    if (!term.trim()) return nodes;
    const lowered = term.toLowerCase();

    const mapped: Array<PrimeTreeNode | null> = nodes.map((node) => {
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
    });

    return mapped.filter(isPrimeTreeNode);
};

export function useAvailableDataTree(options: {
    contextSample: Ref<ContextSample>;
    currentOrderIndex: Ref<number>;
    availableDataSearch: Ref<string>;
    mappingStudioSearch: Ref<string>;
}) {
    const { contextSample, currentOrderIndex, availableDataSearch, mappingStudioSearch } = options;

    const inputTree = computed<TreeNode[]>(() => buildTree(contextSample.value.input ?? {}, 'input'));

    const previousSteps = computed<ContextStepSample[]>(() =>
        (contextSample.value.steps || []).filter((step) => step.order_index < currentOrderIndex.value),
    );

    const stepsTree = computed<TreeNode[]>(() =>
        previousSteps.value.map((step) => ({
            label: step.name,
            path: `steps.${step.key}`,
            type: 'object',
            children: buildTree(step.sample ?? {}, `steps.${step.key}`),
        })),
    );

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

    const filteredAvailableDataTree = computed(() =>
        filterTreeNodes(availableDataTree.value, availableDataSearch.value),
    );

    const filteredStudioDataTree = computed(() =>
        filterTreeNodes(availableDataTree.value, mappingStudioSearch.value),
    );

    return {
        availableDataTree,
        filteredAvailableDataTree,
        filteredStudioDataTree,
        previousSteps,
    };
}
