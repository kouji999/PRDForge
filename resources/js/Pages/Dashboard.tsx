import { Head, Link } from '@inertiajs/react';
import { AppShell } from '@/Components/AppShell';
import { StatusPill } from '@/Components/StatusPill';
import { Button } from '@/Components/Button';
import { timeAgo } from '@/lib';
import { FileText, Folder, ListChecks, Sparkles, TrendingUp } from 'lucide-react';

export interface SidebarShared {
    activeProjects: number;
    unreadConversations: number;
    statuses: Record<string, { label: string; tone: string }>;
}

export interface Props {
    auth: { user: import('@/types').User };
    sidebar: SidebarShared;
    stats: { active_projects: number; total_requirements: number; total_prds: number; approved: number };
    projects: Array<{
        id: number;
        name: string;
        slug: string;
        status: string;
        requirements_count: number;
        has_prd: boolean;
        updated_at: string;
    }>;
    recentGenerations: Array<{
        id: number;
        type: string;
        status: string;
        latency_ms: number | null;
        created_at: string;
    }>;
}

const TYPE_LABELS: Record<string, string> = {
    conversation: 'Chat discovery',
    extraction: 'Requirement extraction',
    readiness: 'Readiness check',
    prd_generation: 'PRD generation',
    section_action: 'AI section action',
    prd_review: 'PRD review',
};

export default function Dashboard({ auth, sidebar, stats, projects, recentGenerations }: Props) {
    return (
        <AppShell user={auth.user} activeCount={sidebar.activeProjects} current="Dashboard">
            <Head title="Dashboard" />

            <div className="flex-1 overflow-y-auto p-4 md:p-6">
                <div className="mx-auto max-w-6xl space-y-6">
                    {/* Header */}
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="font-mono text-[11px] uppercase tracking-wider text-ink-3">
                                Architect Suite // Workspace
                            </div>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight text-ink">
                                Project Workspace
                            </h1>
                            <p className="mt-1 max-w-xl text-sm text-ink-3">
                                Ubah ide mentah jadi PRD engineering-ready lewat discovery AI terstruktur.
                            </p>
                        </div>
                        <Link href={route('projects.index')}>
                            <Button variant="primary">
                                <Sparkles size={14} />
                                New Project
                            </Button>
                        </Link>
                    </div>

                    {/* Stats */}
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <StatCard
                            icon={<Folder size={15} className="text-info" />}
                            label="Active Projects"
                            value={stats.active_projects}
                            sub={`${stats.approved} approved`}
                        />
                        <StatCard
                            icon={<ListChecks size={15} className="text-ok" />}
                            label="Requirements"
                            value={stats.total_requirements}
                            sub="total terkumpul"
                        />
                        <StatCard
                            icon={<FileText size={15} className="text-warn" />}
                            label="PRD Documents"
                            value={stats.total_prds}
                            sub="di semua project"
                        />
                        <StatCard
                            icon={<TrendingUp size={15} className="text-accent" />}
                            label="Approved"
                            value={stats.approved}
                            sub="PRD v1.0"
                        />
                    </div>

                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* Projects */}
                        <div className="lg:col-span-2">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-semibold text-ink">Active Specifications</h2>
                                <Link href={route('projects.index')} className="font-mono text-[11px] text-accent hover:underline">
                                    Lihat semua →
                                </Link>
                            </div>

                            {projects.length === 0 ? (
                                <div className="rounded-lg border border-line bg-surface p-8 text-center">
                                    <div className="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded bg-surface-2">
                                        <Folder size={18} className="text-ink-3" />
                                    </div>
                                    <p className="text-sm font-medium text-ink">Belum ada project</p>
                                    <p className="mt-1 text-sm text-ink-3">
                                        Mulai dengan project pertama — AI bakal bantu gali requirement-nya.
                                    </p>
                                    <Link href={route('projects.index')} className="mt-4 inline-block">
                                        <Button variant="primary">Buat Project Pertama</Button>
                                    </Link>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {projects.slice(0, 6).map((p) => {
                                        const tone = sidebar.statuses[p.status]?.tone ?? 'muted';
                                        const label = sidebar.statuses[p.status]?.label ?? p.status;

                                        return (
                                            <Link
                                                key={p.id}
                                                href={`/projects/${p.id}`}
                                                className="block rounded-lg border border-line bg-surface p-4 transition-colors hover:border-line-strong"
                                            >
                                                <div className="flex items-center justify-between gap-3">
                                                    <StatusPill label={label} tone={tone as 'ok' | 'warn' | 'risk' | 'info' | 'muted'} />
                                                    <span className="font-mono text-[11px] text-ink-3">
                                                        {timeAgo(p.updated_at)}
                                                    </span>
                                                </div>
                                                <div className="mt-2.5 font-medium text-ink">{p.name}</div>
                                                <div className="mt-1 flex items-center gap-3 font-mono text-[11px] text-ink-3">
                                                    <span>{p.requirements_count} requirements</span>
                                                    {p.has_prd && <span className="text-ok">PRD ✓</span>}
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Activity */}
                        <div>
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-semibold text-ink">Recent Activity</h2>
                                <span className="font-mono text-[11px] text-ink-3">live feed</span>
                            </div>

                            {recentGenerations.length === 0 ? (
                                <div className="rounded-lg border border-line bg-surface p-6 text-center">
                                    <p className="text-sm text-ink-3">
                                        Belum ada aktivitas AI. Mulai percakapan discovery di salah satu project.
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {recentGenerations.map((g) => (
                                        <div
                                            key={g.id}
                                            className="flex items-center justify-between gap-3 rounded-lg border border-line bg-surface px-3 py-2.5"
                                        >
                                            <div className="min-w-0">
                                                <div className="truncate text-sm text-ink-2">
                                                    {TYPE_LABELS[g.type] ?? g.type}
                                                </div>
                                                <div className="font-mono text-[11px] text-ink-3">
                                                    {timeAgo(g.created_at)}
                                                </div>
                                            </div>
                                            <StatusPill
                                                label={g.status}
                                                tone={g.status === 'completed' ? 'ok' : g.status === 'failed' ? 'risk' : 'info'}
                                            />
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppShell>
    );
}

function StatCard({
    icon,
    label,
    value,
    sub,
}: {
    icon: React.ReactNode;
    label: string;
    value: number;
    sub: string;
}) {
    return (
        <div className="rounded-lg border border-line bg-surface p-4">
            <div className="flex items-center justify-between">
                <span className="font-mono text-[11px] uppercase tracking-wider text-ink-3">{label}</span>
                {icon}
            </div>
            <div className="mt-3 text-3xl font-semibold tracking-tight text-ink">{value}</div>
            <div className="mt-1 font-mono text-[11px] text-ink-3">{sub}</div>
        </div>
    );
}
