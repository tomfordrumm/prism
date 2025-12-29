import { ref, type Ref } from 'vue';
import type { PrimeTreeNode } from '@/composables/useAvailableDataTree';

type MappingTarget = { role: 'system' | 'user'; name: string } | null;

const flashKeyFor = (role: 'system' | 'user', name: string) => `${role}:${name}`;

export const useMappingDragDrop = (options: {
    mappingTarget: Ref<MappingTarget>;
    applyMappingText: (role: 'system' | 'user', name: string, value: string) => void;
}) => {
    const mappingFlashKey = ref<string | null>(null);

    const flashMapping = (role: 'system' | 'user', name: string) => {
        const key = flashKeyFor(role, name);
        mappingFlashKey.value = key;
        window.setTimeout(() => {
            if (mappingFlashKey.value === key) {
                mappingFlashKey.value = null;
            }
        }, 450);
    };

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

    const onStudioTreeSelect = (event: { node: PrimeTreeNode }) => {
        const path = event.node?.data?.path;
        if (!path || !options.mappingTarget.value) return;

        options.applyMappingText(options.mappingTarget.value.role, options.mappingTarget.value.name, path);
        flashMapping(options.mappingTarget.value.role, options.mappingTarget.value.name);
    };

    const handleTreeDragStart = (event: DragEvent, path?: string) => {
        if (!path || !event.dataTransfer) return;
        event.dataTransfer.setData('text/plain', path);
        event.dataTransfer.setData('text', path);
        event.dataTransfer.effectAllowed = 'copy';

        const ghost = document.createElement('div');
        ghost.textContent = path;
        ghost.style.fontSize = '12px';
        ghost.style.fontFamily =
            'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace';
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

    const handleMappingDrop = (event: DragEvent, role: 'system' | 'user', name: string) => {
        event.preventDefault();
        const path = event.dataTransfer?.getData('text/plain') ?? '';
        if (!path) return;

        options.mappingTarget.value = { role, name };
        options.applyMappingText(role, name, path);
        flashMapping(role, name);
    };

    const copyPath = (path?: string) => {
        if (!path) return;
        navigator.clipboard.writeText(path).catch(() => {});
    };

    return {
        mappingFlashKey,
        insertPlaceholder,
        onStudioTreeSelect,
        handleTreeDragStart,
        handleMappingDrop,
        copyPath,
    };
};
