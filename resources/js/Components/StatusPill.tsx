import { cn } from '@/lib';

type Tone = 'ok' | 'warn' | 'risk' | 'info' | 'muted';

const TONE_STYLES: Record<Tone, string> = {
    ok: 'border-[rgba(16,185,129,0.3)] bg-[rgba(16,185,129,0.08)] text-ok',
    warn: 'border-[rgba(245,158,11,0.3)] bg-[rgba(245,158,11,0.08)] text-warn',
    risk: 'border-[rgba(244,63,94,0.3)] bg-[rgba(244,63,94,0.08)] text-risk',
    info: 'border-[rgba(14,165,233,0.3)] bg-[rgba(14,165,233,0.08)] text-info',
    muted: 'border-line bg-surface text-ink-3',
};

const DOT_STYLES: Record<Tone, string> = {
    ok: 'bg-ok',
    warn: 'bg-warn',
    risk: 'bg-risk',
    info: 'bg-info',
    muted: 'bg-ink-3',
};

export function StatusPill({ label, tone = 'muted', className }: { label: string; tone?: Tone; className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex h-5 items-center gap-1.5 rounded-full border px-2 font-mono text-[11px] leading-none',
                TONE_STYLES[tone],
                className,
            )}
        >
            <span className={cn('h-1.5 w-1.5 rounded-full', DOT_STYLES[tone])} />
            {label}
        </span>
    );
}

export function CodeChip({ children, className }: { children: React.ReactNode; className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex h-5 items-center rounded border border-line bg-surface px-1.5 font-mono text-[11px] leading-none text-ink-3',
                className,
            )}
        >
            {children}
        </span>
    );
}
