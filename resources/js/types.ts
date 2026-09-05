export interface PageType {
    component: string;
    props: Record<string, unknown>;
    url: string;
    version?: string;
    encryptHistory?: boolean;
    clearHistory?: boolean;
}

export interface Flash {
    success?: string;
    error?: string;
}

export interface Paginated<T> {
    data: T[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

export interface Project {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    status: ProjectStatus;
    readiness: {
        ready: boolean;
        score: number;
        criteria: ReadinessCriterion[];
        missing: string[];
    } | null;
    created_at: string;
    updated_at: string;
}

export type ProjectStatus =
    | 'discovery'
    | 'requirements_in_progress'
    | 'ready_for_prd'
    | 'generating'
    | 'review'
    | 'approved'
    | 'archived';

export interface ReadinessCriterion {
    key: string;
    label: string;
    status: 'met' | 'missing' | 'partial';
}

export interface Message {
    id: number;
    conversation_id: number;
    role: 'user' | 'assistant' | 'system';
    content: string;
    meta: Record<string, unknown> | null;
    created_at: string;
}

export interface Conversation {
    id: number;
    project_id: number;
    title: string | null;
    created_at: string;
    updated_at: string;
}

export interface ProjectContext {
    id: number;
    project_id: number;
    problem: string | null;
    target_users: string | null;
    product_concept: string | null;
    core_features: string[] | null;
    platform: string | null;
    constraints: string | null;
    goals: string[] | null;
    mvp_scope: string | null;
    updated_at: string;
}

export interface Requirement {
    id: number;
    project_id: number;
    type: string;
    title: string;
    content: string;
    status: 'proposed' | 'confirmed' | 'rejected' | 'needs_review';
    source: 'extracted' | 'manual';
    priority: 'low' | 'medium' | 'high' | 'critical';
    created_at: string;
    updated_at: string;
}

export interface PrdSection {
    id: number;
    prd_id: number;
    key: string;
    title: string;
    content: string;
    order: number;
    status: 'draft' | 'reviewed' | 'approved';
    updated_at: string;
}

export interface Prd {
    id: number;
    project_id: number;
    title: string;
    summary: string | null;
    status: 'draft' | 'review' | 'approved';
    created_at: string;
    updated_at: string;
}

export interface PrdVersion {
    id: number;
    prd_id: number;
    version: string;
    label: string | null;
    snapshot: Record<string, unknown>;
    created_at: string;
}

export interface AiProvider {
    id: number;
    name: string;
    base_url: string;
    model: string;
    status: 'connected' | 'error' | 'untested';
    is_default: boolean;
    last_tested_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface AiGeneration {
    id: number;
    type: string;
    status: 'pending' | 'completed' | 'failed';
    latency_ms: number | null;
    created_at: string;
}
