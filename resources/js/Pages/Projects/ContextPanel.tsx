import { CheckCircle2, Circle, CircleDashed } from 'lucide-react';
import type { ReadinessCriterion } from '@/types';

interface Props {
    project: {
        id: number;
        context: {
            problem: string | null;
            target_users: string | null;
            product_concept: string | null;
            core_features: string[];
            platform: string | null;
            constraints: string | null;
            goals: string[];
            mvp_scope: string | null;
        } | null;
    };
    readiness: { ready: boolean; score: number; criteria: ReadinessCriterion[]; missing: string[] };
}

const CONTEXT_SECTIONS: Array<{ key: string; label: string; type: 'text' | 'list' }> = [
    { key: 'problem', label: '01. Problem Statement', type: 'text' },
    { key: 'target_users', label: '02. Target Users', type: 'text' },
    { key: 'product_concept', label: '03. Product Concept', type: 'text' },
    { key: 'core_features', label: '04. Core Features', type: 'list' },
    { key: 'platform', label: '05. Platform', type: 'text' },
    { key: 'constraints', label: '06. Constraints', type: 'text' },
    { key: 'goals', label: '07. Goals', type: 'list' },
    { key: 'mvp_scope', label: '08. MVP Scope', type: 'text' },
];

export function ContextPanel({ project, readiness }: Props) {
    const context = project.context;

    const statusFor = (key: string): 'met' | 'missing' | 'partial' => {
        const c = readiness.criteria.find((c) => c.key === key);
        if (c) return c.status as 'met' | 'missing' | 'partial';

        const value = context?.[key as keyof NonNullable<typeof context>];
        const filled = Array.isArray(value) ? value.length > 0 : typeof value === 'string' && value.length > 0;

        return filled ? 'met' : 'missing';
    };

    const StatusIcon = ({ status }: { status: string }) => {
        if (status === 'met')
            return <CheckCircle2 size={13} className="shrink-0 text-ok" />;
        if (status === 'partial')
            return <CircleDashed size={13} className="shrink-0 text-warn" />;
        return <Circle size={13} className="shrink-0 text-ink-ghost" />;
    };

    return (
        <div className="space-y-3 p-4">
            {readiness.missing.length > 0 && (
                <div className="rounded-lg border border-[rgba(245,158,11,0.3)] bg-[rgba(245,158,11,0.08)] p-3">
                    <div className="font-mono text-[10px] uppercase tracking-wider text-warn">
                        Informasi kurang
                    </div>
                    <ul className="mt-2 space-y-1">
                        {readiness.missing.slice(0, 4).map((m) => (
                            <li key={m} className="text-xs leading-relaxed text-ink-2">
                                • {m}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {CONTEXT_SECTIONS.map(({ key, label, type }) => {
                const status = statusFor(key);
                const value = context?.[key as keyof NonNullable<typeof context>];

                return (
                    <div
                        key={key}
                        className="rounded-lg border border-line bg-surface-2 p-3 transition-colors hover:border-line-strong"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-mono text-[10px] font-medium uppercase tracking-wider text-accent">
                                {label}
                            </span>
                            <StatusIcon status={status} />
                        </div>

                        {value == null || (Array.isArray(value) && value.length === 0) ? (
                            <p className="mt-2 text-xs italic text-ink-ghost">
                                Belum ada data — hasil ekstraksi AI akan muncul di sini.
                            </p>
                        ) : type === 'list' && Array.isArray(value) ? (
                            <ul className="mt-2 space-y-1">
                                {value.map((item, i) => (
                                    <li
                                        key={i}
                                        className="flex items-start gap-1.5 text-xs leading-relaxed text-ink-2"
                                    >
                                        <span className="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-accent" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-2 text-sm leading-relaxed text-ink-2">{String(value)}</p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
