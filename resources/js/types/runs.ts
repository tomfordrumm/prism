export interface RunDatasetInfo {
    id: number;
    name: string;
}

export interface RunChainInfo {
    id: number;
    name: string;
}

export interface RunStepChainNode {
    id: number;
    name: string;
    provider?: string | null;
    provider_name?: string | null;
    model_name?: string | null;
}

export interface RunFeedbackItem {
    id: number;
    type: string;
    rating?: number | null;
    comment?: string | null;
    suggested_prompt_content?: string | null;
    analysis?: string | null;
}

export interface RunStepPayload {
    id: number;
    order_index: number;
    status: string;
    chain_node: RunStepChainNode | null;
    target_prompt_version_id?: number | null;
    target_prompt_template_id?: number | null;
    target_prompt_content?: string | null;
    request_payload: Record<string, unknown>;
    response_raw: Record<string, unknown>;
    parsed_output: unknown;
    tokens_in?: number | null;
    tokens_out?: number | null;
    duration_ms?: number | null;
    retry_count?: number | null;
    retry_reasons?: string[] | null;
    validation_errors?: string[] | null;
    created_at: string;
    feedback: RunFeedbackItem[];
}

export interface RunPayload {
    id: number;
    status: string;
    chain: RunChainInfo | null;
    chain_label?: string | null;
    dataset?: RunDatasetInfo | null;
    test_case?: RunDatasetInfo | null;
    input: Record<string, unknown> | null;
    chain_snapshot: Record<string, unknown> | null;
    total_tokens_in?: number | null;
    total_tokens_out?: number | null;
    total_cost?: number | string | null;
    duration_ms?: number | null;
    created_at: string;
    finished_at?: string | null;
}

export interface RunHistoryItem {
    id: number;
    status: string;
    duration_ms?: number | null;
    total_tokens_in?: number | null;
    total_tokens_out?: number | null;
    created_at: string | null;
    href?: string;
    final_snippet?: string | null;
}

export interface RunProviderCredentialOption {
    value: number;
    label: string;
    provider: string;
}

export interface RunModelOption {
    id: string;
    name: string;
    display_name: string;
}
