export type VariableSource = 'input' | 'previous_step' | 'constant' | '';

export interface VariableMapping {
    source?: VariableSource;
    path?: string;
    step_key?: string;
    value?: string;
}

export interface ChainNodeMessage {
    role: string;
    mode?: 'template' | 'inline';
    prompt_template_id?: number | null;
    prompt_version_id: number | null;
    variables?: Record<string, VariableMapping>;
    inline_content?: string | null;
}

export interface PromptDetails {
    mode: 'template' | 'inline';
    template_id: number | null;
    template_name: string | null;
    prompt_version_id: number | null;
    prompt_version: number | null;
    content: string | null;
    variables: string[];
}

export interface InternalSchemaNode {
    type?: string;
    fields?: Record<string, InternalSchemaNode>;
    items?: InternalSchemaNode;
    values?: string[];
    required?: boolean;
}

export interface ChainNodePayload {
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

export interface ModelOption {
    id: string;
    name: string;
    display_name: string;
}

export interface PromptTemplateVersion {
    id: number;
    version: number;
    created_at: string | null;
    content: string;
}

export interface PromptTemplateOption {
    id: number;
    name: string;
    latest_version_id: number | null;
    versions: PromptTemplateVersion[];
    variables?: Array<{ name: string }> | string[] | null;
}

export interface ContextStepSample {
    key: string;
    name: string;
    order_index: number;
    sample: unknown;
}

export interface ContextSample {
    input: Record<string, unknown> | null;
    steps: ContextStepSample[];
}

export interface ProviderCredentialOption {
    value: number | string;
    label: string;
    provider?: string;
}
