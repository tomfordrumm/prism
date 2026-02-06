import hljs from 'highlight.js/lib/core';
import json from 'highlight.js/lib/languages/json';

let isRegistered = false;

const escapeHtml = (value: string) =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

const ensureHighlight = () => {
    if (isRegistered) return;
    hljs.registerLanguage('json', json);
    isRegistered = true;
};

export const useRunHighlighting = () => {
    ensureHighlight();

    const highlightJson = (value: unknown) => {
        let jsonText = '';
        try {
            jsonText = JSON.stringify(value, null, 2);
            return hljs.highlight(jsonText, { language: 'json' }).value;
        } catch {
            jsonText = String(value ?? '');
        }

        return escapeHtml(jsonText);
    };

    const highlightFinalResult = (text: string) => {
        const trimmed = text.trim();
        if (!trimmed) {
            return escapeHtml(text);
        }

        try {
            const parsed = JSON.parse(trimmed);
            return highlightJson(parsed);
        } catch {
            return escapeHtml(text);
        }
    };

    return {
        highlightJson,
        highlightFinalResult,
    };
};
