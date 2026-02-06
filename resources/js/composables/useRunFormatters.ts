type RunHistoryItem = {
    total_tokens_in?: number | null;
    total_tokens_out?: number | null;
};

export const jsonPretty = (value: unknown) => {
    try {
        return JSON.stringify(value, null, 2);
    } catch {
        return String(value);
    }
};

export const durationHuman = (ms?: number | null) => {
    if (!ms && ms !== 0) return '—';
    if (ms >= 1000) return `${(ms / 1000).toFixed(2)} s`;
    return `${ms} ms`;
};

export const formatTimestamp = (value?: string | null) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleString(undefined, {
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export const formatFullTimestamp = (value?: string | null) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString();
};

export const relativeTime = (value?: string | null) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    const diffMs = Date.now() - date.getTime();
    const diffSeconds = Math.floor(diffMs / 1000);
    if (diffSeconds < 60) return `${diffSeconds}s ago`;
    const diffMinutes = Math.floor(diffSeconds / 60);
    if (diffMinutes < 60) return `${diffMinutes}m ago`;
    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays}d ago`;
};

export const formatCost = (value?: number | string | null) => {
    if (value == null) return null;
    const num = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(num)) return String(value);
    return `$${num.toFixed(4)}`;
};

export const totalTokens = (entry: RunHistoryItem) => {
    const inTokens = entry.total_tokens_in ?? 0;
    const outTokens = entry.total_tokens_out ?? 0;
    if (!inTokens && !outTokens) return '—';
    return `${inTokens + outTokens}`;
};
