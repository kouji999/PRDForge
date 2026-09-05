import { Head, Link, useForm } from '@inertiajs/react';
import { AppShell } from '@/Components/AppShell';
import { StatusPill } from '@/Components/StatusPill';
import { Button } from '@/Components/Button';
import { Input, Label, Textarea } from '@/Components/Input';
import { timeAgo } from '@/lib';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';

interface Props {
    auth: { user: import('@/types').User };
    sidebar: import('@/Pages/Dashboard').SidebarShared;
    projects: Array<{
        id: number;
        name: string;
        slug: string;
        description: string | null;
        status: string;
        requirements_count: number;
        has_prd: boolean;
        updated_at: string;
    }>;
    stats: { active_projects: number; total_prds: number; total_requirements: number; approved: number };
    filter?: string;
}

export default function ProjectsIndex({ auth, sidebar, projects }: Props) {
    const [creating, setCreating] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('projects.store'), {
            onSuccess: () => {
                reset();
                setCreating(false);
            },
        });
    };

    return (
        <AppShell user={auth.user} activeCount={sidebar.activeProjects} current="Projects">
            <Head title="Projects" />

            <div className="flex-1 overflow-y-auto p-4 md:p-6">
                <div className="mx-auto max-w-6xl space-y-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="font-mono text-[11px] uppercase tracking-wider text-ink-3">
                                Architect Suite // Project Registry
                            </div>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight text-ink">Projects</h1>
                        </div>
                        <Button variant="primary" onClick={() => setCreating(true)}>
                            <Plus size={14} />
                            New Project
                        </Button>
                    </div>

                    {projects.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-line-strong bg-surface p-12 text-center">
                            <p className="font-medium text-ink">Belum ada project</p>
                            <p className="mx-auto mt-1 max-w-sm text-sm text-ink-3">
                                Buat project untuk mulai diskusi discovery dengan AI, kumpulkan requirement,
                                dan generate PRD.
                            </p>
                            <Button variant="primary" className="mt-5" onClick={() => setCreating(true)}>
                                <Plus size={14} />
                                Project Pertama
                            </Button>
                        </div>
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {projects.map((p) => {
                                const tone = sidebar.statuses[p.status]?.tone ?? 'muted';
                                const label = sidebar.statuses[p.status]?.label ?? p.status;

                                return (
                                    <Link
                                        key={p.id}
                                        href={`/projects/${p.id}`}
                                        className="group flex flex-col rounded-lg border border-line bg-surface p-4 transition-colors hover:border-line-strong"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <StatusPill label={label} tone={tone as 'ok'} />
                                            {p.has_prd && (
                                                <span className="font-mono text-[10px] text-ok">PRD ✓</span>
                                            )}
                                        </div>
                                        <div className="mt-3 font-medium text-ink group-hover:text-accent">
                                            {p.name}
                                        </div>
                                        {p.description && (
                                            <p className="mt-1 line-clamp-2 text-sm text-ink-3">
                                                {p.description}
                                            </p>
                                        )}
                                        <div className="mt-auto pt-3 font-mono text-[11px] text-ink-3">
                                            {p.requirements_count} req · {timeAgo(p.updated_at)}
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* Create modal */}
            {creating && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                    <div className="w-full max-w-md rounded-xl border border-line-strong bg-surface-3 p-5 shadow-[0_4px_16px_rgba(0,0,0,0.45)]">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-semibold text-ink">Project Baru</h2>
                            <button
                                type="button"
                                onClick={() => setCreating(false)}
                                className="rounded p-1 text-ink-3 hover:bg-surface-2 hover:text-ink"
                            >
                                <X size={16} />
                            </button>
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="name">Nama Project</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="cth: Aplikasi Gym Tracker"
                                    autoFocus
                                    required
                                />
                                {errors.name && <p className="mt-1.5 text-xs text-risk">{errors.name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="description">Deskripsi Singkat (opsional)</Label>
                                <Textarea
                                    id="description"
                                    rows={3}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Satu-dua kalimat tentang idenya..."
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button type="button" onClick={() => setCreating(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" variant="primary" disabled={processing}>
                                    {processing ? 'Membuat…' : 'Buat Project'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppShell>
    );
}
