import { diffLines as diffLinesLib } from 'diff';
import { computed, ref, type Ref } from 'vue';

export type DiffLine = {
    type: 'add' | 'remove' | 'context';
    oldLine: number | null;
    newLine: number | null;
    text: string;
};

export type DiffViewMode = 'diff' | 'final';

interface DiffPart {
    added?: boolean;
    removed?: boolean;
    value: string;
}

export function usePromptDiff(
    originalContent: Ref<string>,
    suggestedContent: Ref<string>
) {
    const viewMode = ref<DiffViewMode>('diff');

    const buildDiffLines = (original: string, updated: string): DiffLine[] => {
        const changes = diffLinesLib(original || '', updated || '') as DiffPart[];
        let oldLine = 1;
        let newLine = 1;

        return changes.flatMap((part: DiffPart) => {
            const split = part.value.split('\n');

            return split
                .filter((_: string, idx: number) => !(idx === split.length - 1 && split[idx] === ''))
                .map((line: string) => {
                    if (part.added) {
                        return {
                            type: 'add' as const,
                            oldLine: null,
                            newLine: newLine++,
                            text: line,
                        };
                    }

                    if (part.removed) {
                        return {
                            type: 'remove' as const,
                            oldLine: oldLine++,
                            newLine: null,
                            text: line,
                        };
                    }

                    return {
                        type: 'context' as const,
                        oldLine: oldLine++,
                        newLine: newLine++,
                        text: line,
                    };
                });
        });
    };

    const diffLinesResult = computed(() => {
        if (!originalContent.value || !suggestedContent.value) {
            return [];
        }

        return buildDiffLines(originalContent.value, suggestedContent.value);
    });

    const diffLineSymbol = (type: DiffLine['type']) => {
        if (type === 'add') return '+';
        if (type === 'remove') return '-';
        return ' ';
    };

    const hasSuggestion = computed(() => {
        return !!suggestedContent.value && suggestedContent.value.trim().length > 0;
    });

    const setViewMode = (mode: DiffViewMode) => {
        viewMode.value = mode;
    };

    return {
        viewMode,
        diffLines: diffLinesResult,
        diffLineSymbol,
        hasSuggestion,
        setViewMode,
    };
}
