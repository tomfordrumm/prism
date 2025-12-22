<script setup lang="ts">
import { computed } from 'vue';
import { Codemirror } from 'vue-codemirror';
import { EditorState } from '@codemirror/state';
import type { ViewUpdate } from '@codemirror/view';
import {
    Decoration,
    EditorView,
    MatchDecorator,
    ViewPlugin,
    highlightActiveLine,
    highlightActiveLineGutter,
    keymap,
    lineNumbers,
    placeholder as placeholderExtension,
} from '@codemirror/view';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { markdown } from '@codemirror/lang-markdown';
import { xml } from '@codemirror/lang-xml';
import { foldGutter, foldKeymap } from '@codemirror/language';
import { search, searchKeymap } from '@codemirror/search';
import Icon from '@/components/Icon.vue';

type Mode = 'plain' | 'markdown' | 'xml';
type Preset = 'minimal' | 'ide';

interface Props {
    modelValue: string;
    mode?: Mode;
    preset?: Preset;
    placeholder?: string;
    readOnly?: boolean;
    height?: string | number;
    showControls?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    mode: 'plain',
    preset: 'minimal',
    placeholder: '',
    readOnly: false,
    showControls: false,
});

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
    (event: 'update:mode', value: Mode): void;
    (event: 'update:preset', value: Preset): void;
    (event: 'focus'): void;
    (event: 'blur'): void;
}>();

const editorValue = computed({
    get: () => props.modelValue,
    set: (value: string) => emit('update:modelValue', value),
});

const currentMode = computed<Mode>({
    get: () => props.mode,
    set: (value) => emit('update:mode', value),
});

const currentPreset = computed<Preset>({
    get: () => props.preset,
    set: (value) => emit('update:preset', value),
});

const promptVariableMatcher = new MatchDecorator({
    regexp: /{{\s*[\w.-]+\s*}}/g,
    decoration: Decoration.mark({ class: 'cm-prompt-var' }),
});

const promptVariableHighlight = ViewPlugin.fromClass(
    class {
        decorations = Decoration.none;

        constructor(view: EditorView) {
            this.decorations = promptVariableMatcher.createDeco(view);
        }

        update(update: ViewUpdate) {
            this.decorations = promptVariableMatcher.updateDeco(update, this.decorations);
        }
    },
    {
        decorations: (value) => value.decorations,
    },
);

const templateBraceKeymap = keymap.of([
    {
        key: '{',
        run(view) {
            const { state } = view;
            const range = state.selection.main;

            if (!range.empty) {
                return false;
            }

            const from = range.from;
            const prevChar = from > 0 ? state.doc.sliceString(from - 1, from) : '';
            if (prevChar !== '{') {
                return false;
            }

            const nextChars = state.doc.sliceString(from, from + 2);
            const insertText = nextChars === '}}' ? '{' : '{}}';

            view.dispatch({
                changes: { from, to: from, insert: insertText },
                selection: { anchor: from + 1 },
            });

            return true;
        },
    },
]);

const baseTheme = EditorView.theme(
    {
        '&': {
            fontFamily: 'inherit',
            fontSize: '0.875rem',
            backgroundColor: 'transparent',
            height: '100%',
        },
        '.cm-scroller': {
            fontFamily: 'inherit',
            padding: '16px',
            lineHeight: '1.6',
            overflow: 'auto',
        },
        '&.cm-focused': {
            outline: 'none',
        },
        '.cm-gutters': {
            backgroundColor: 'transparent',
            border: 'none',
        },
    },
    { dark: false },
);

const languageExtensions = computed(() => {
    if (currentMode.value === 'markdown') return [markdown()];
    if (currentMode.value === 'xml') return [xml()];
    return [];
});

const presetExtensions = computed(() => {
    if (currentPreset.value === 'ide') {
        return [
            lineNumbers(),
            highlightActiveLineGutter(),
            highlightActiveLine(),
            foldGutter(),
            search({ top: true }),
            keymap.of([...searchKeymap, ...foldKeymap]),
        ];
    }

    return [];
});

const placeholderConfig = computed(() =>
    props.placeholder ? [placeholderExtension(props.placeholder)] : [],
);

const editableExtensions = computed(() => [
    EditorState.readOnly.of(Boolean(props.readOnly)),
    EditorView.editable.of(!props.readOnly),
]);

const extensions = computed(() => [
    EditorView.lineWrapping,
    templateBraceKeymap,
    history(),
    keymap.of([...defaultKeymap, ...historyKeymap]),
    ...placeholderConfig.value,
    ...editableExtensions.value,
    ...languageExtensions.value,
    ...presetExtensions.value,
    promptVariableHighlight,
    baseTheme,
]);

const editorStyle = computed(() => {
    if (props.height === undefined || props.height === null || props.height === '') {
        return undefined;
    }

    return {
        height: typeof props.height === 'number' ? `${props.height}px` : props.height,
    };
});

const wrapperClass = computed(() =>
    props.readOnly ? 'bg-muted/40' : 'bg-background/60',
);
</script>

<template>
    <div class="overflow-hidden rounded-md border border-border/60" :class="wrapperClass" :style="editorStyle">
        <div
            v-if="showControls"
            class="flex items-center justify-between border-b border-border/60 px-2 py-1 text-xs text-muted-foreground"
        >
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded px-2 py-1 transition hover:text-foreground"
                    :class="currentPreset === 'minimal' ? 'bg-muted text-foreground' : ''"
                    aria-label="Minimal view"
                    @click="currentPreset = 'minimal'"
                >
                    <Icon name="sun" class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="rounded px-2 py-1 transition hover:text-foreground"
                    :class="currentPreset === 'ide' ? 'bg-muted text-foreground' : ''"
                    aria-label="IDE view"
                    @click="currentPreset = 'ide'"
                >
                    <Icon name="layout" class="h-4 w-4" />
                </button>
            </div>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded px-2 py-1 transition hover:text-foreground"
                    :class="currentMode === 'plain' ? 'bg-muted text-foreground' : ''"
                    aria-label="Plain text"
                    @click="currentMode = 'plain'"
                >
                    <Icon name="fileText" class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="rounded px-2 py-1 transition hover:text-foreground"
                    :class="currentMode === 'markdown' ? 'bg-muted text-foreground' : ''"
                    aria-label="Markdown"
                    @click="currentMode = 'markdown'"
                >
                    <Icon name="hash" class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="rounded px-2 py-1 transition hover:text-foreground"
                    :class="currentMode === 'xml' ? 'bg-muted text-foreground' : ''"
                    aria-label="XML"
                    @click="currentMode = 'xml'"
                >
                    <Icon name="code" class="h-4 w-4" />
                </button>
            </div>
        </div>
        <Codemirror
            v-model="editorValue"
            :extensions="extensions"
            class="h-full w-full"
            @focus="emit('focus')"
            @blur="emit('blur')"
        />
    </div>
</template>
