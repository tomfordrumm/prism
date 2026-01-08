export interface ProjectPayload {
    id: number;
    uuid: string;
    name: string;
    description?: string | null;
}

export interface TemplateVariable {
    name: string;
    type?: string;
    description?: string | null;
}

export interface TemplateListItem {
    id: number;
    name: string;
    description?: string | null;
    latest_version?: number | null;
}

export interface DraftTemplate {
    id: string;
    name: string;
    description?: string | null;
    latest_version?: number | null;
    variables: TemplateVariable[];
    content: string;
    isDraft: true;
}

export interface TemplatePayload {
    id: number;
    name: string;
    description?: string | null;
    variables?: TemplateVariable[] | null;
}

export interface VersionPayload {
    id: number;
    version: number;
    changelog?: string | null;
    created_at: string;
    content?: string;
    rating?: {
        up: number;
        down: number;
        score: number;
    };
}
