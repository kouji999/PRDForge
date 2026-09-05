import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { AppShell } from '@/Components/AppShell';
import { Button } from '@/Components/Button';
import { StatusPill } from '@/Components/StatusPill';
import { cn } from '@/lib';
import { Markdown } from '@/Components/Markdown';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Download,
    FileText,
    GitBranch,
    Loader2,
    Pencil,
    Save,
    ShieldCheck,
    Sparkles,
    Trash2,
    Wand2,
    X,
} from 'lucide-react';

interface Section {
    id: number;
    key: string;
    title: string;
    content: string;
    order: number;
    status: string;
    updated_at: string;
}

interface Version {
    id: number;
    version: string;
    label: string | null;
    section_count: number;
    created_at: string;
}

interface Props {
    auth: { user: import('@/types').User };
    sidebar: import('@/Pages/Dashboard').SidebarShared;
    project: { id: number; name: string; slug: string; status: string };
    prd: { id: number; title: string; summary: string | null; status: string; sections: Section[] };
    versions: Version[];
    registry: Array<{ key: string; title: string; hint: string }>;
}

type Proposal = {
    section_id: number;
    action: string;
    content: string;
    changelog: string;
    original: string | null;
} | null;

interface ReviewResult {
    gaps: string[];
    contradictions: string[];
    suggestions: string[];
    verdict: string;
}

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const AI_ACTIONS: Array<{ key: string; label: string }> = [
    { key: 'rewrite', label: 'Rewrite' },
    { key: 'expand', label: 'Expand' },
    { key: 'simplify', label: 'Simplify' },
    { key: 'review', label: 'Review' },
    { key: 'contradictions', label: 'Find contradictions' },
];

export default function PrdWorkspace({ auth, sidebar, project, prd, versions }: Props) {
    const [title] = useState(prd.title);
    const [sections, setSections] = useState(prd.sections);
    const [selectedId, setSelectedId] = useState<number | null>(prd.sections[0]?.id ?? null);
    const [editing, setEditing] = useState(false);
    const [editContent, setEditContent] = useState('');
    const [saving, setSaving] = useState(false);
    const [proposal, setProposal] = useState<Proposal>(null);
    const [aiBusy, setAiBusy] = useState<string | null>(null);
    const [aiError, setAiError] = useState<string | null>(null);
    const [review, setReview] = useState<ReviewResult | null>(null);
    const [reviewBusy, setReviewBusy] = useState(false);
    const [savingVersion, setSavingVersion] = useState(false);
    const [versionList, setVersionList] = useState(versions);
    const [versionSnapshot, setVersionSnapshot] = useState<{ version: string; snapshot: Record<string, unknown> } | null>(null);
    const [toast, setToast] = useState<string | null>(null);

    const selected = sections.find((s) => s.id === selectedId) ?? null;

    const flashToast = (msg: string) => {
        setToast(msg);
        setTimeout(() => setToast(null), 3500);
    };

    const saveSection = async () => {
        if (!selected) return;
        setSaving(true);

        try {
            const res = await fetch(
                route('projects.prd.sections.update', {
                    project: project.id,
                    prd: prd.id,
                    section: selected.id,
                }),
                {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ content: editContent }),
                },
            );

            if (!res.ok) throw new Error();

            const body = await res.json();

            setSections((prev) => prev.map((s) => (s.id === body.section.id ? body.section : s)));
            setEditing(false);
            flashToast('Section disimpan.');
        } catch {
            setAiError('Gagal menyimpan section.');
        } finally {
            setSaving(false);
        }
    };

    const runAiAction = async (action: string) => {
        if (!selected || aiBusy) return;

        setAiBusy(action);
        setAiError(null);
        setProposal(null);

        try {
            const res = await fetch(
                route('projects.prd.sections.ai', { project: project.id, prd: prd.id, section: selected.id }),
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ action }),
                },
            );

            const body = await res.json();

            if (!res.ok) throw new Error(body.error ?? 'AI action gagal.');

            setProposal(body.proposal);
        } catch (err) {
            setAiError(err instanceof Error ? err.message : 'AI action gagal.');
        } finally {
            setAiBusy(null);
        }
    };

    const applyProposal = async () => {
        if (!proposal || !selected) return;

        setSaving(true);

        try {
            const res = await fetch(
                route('projects.prd.sections.apply', { project: project.id, prd: prd.id, section: selected.id }),
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ content: proposal.content }),
                },
            );

            const body = await res.json();

            if (!res.ok) throw new Error(body.error ?? 'Apply gagal.');

            setSections((prev) => prev.map((s) => (s.id === body.section.id ? body.section : s)));
            setProposal(null);
            flashToast('Perubahan AI diaplikasikan.');
        } catch (err) {
            setAiError(err instanceof Error ? err.message : 'Apply gagal.');
        } finally {
            setSaving(false);
        }
    };

    const runReview = async () => {
        if (reviewBusy) return;
        setReviewBusy(true);
        setReview(null);

        try {
            const res = await fetch(route('projects.prd.review', { project: project.id, prd: prd.id }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            });

            const body = await res.json();

            if (!res.ok) throw new Error(body.error ?? 'Review gagal.');

            setReview(body.review);
        } catch (err) {
            setAiError(err instanceof Error ? err.message : 'Review gagal.');
        } finally {
            setReviewBusy(false);
        }
    };

    const saveVersion = async () => {
        if (savingVersion) return;
        setSavingVersion(true);

        try {
            const res = await fetch(route('projects.prd.versions.store', { project: project.id, prd: prd.id }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            });

            const body = await res.json();

            if (!res.ok) throw new Error(body.error ?? 'Gagal membuat versi.');

            setVersionList((prev) => [body.version, ...prev]);
            flashToast(`Versi ${body.version.version} dibuat.`);
        } catch (err) {
            setAiError(err instanceof Error ? err.message : 'Gagal membuat versi.');
        } finally {
            setSavingVersion(false);
        }
    };

    const loadVersion = async (versionId: number) => {
        try {
            const res = await fetch(
                route('projects.prd.versions.show', { project: project.id, prd: prd.id, versionId }),
            );

            const body = await res.json();

            if (!res.ok) throw new Error();

            setVersionSnapshot({ version: body.version.version, snapshot: body.version.snapshot });
        } catch {
            setAiError('Gagal memuat versi.');
        }
    };

    const approvePrd = async () => {
        try {
            const res = await fetch(route('projects.transition', { project: project.id }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ action: 'approve' }),
            });

            if (!res.ok) {
                const body = await res.json();
                throw new Error(body?.message ?? 'Approve gagal.');
            }

            window.location.reload();
        } catch (err) {
            setAiError(err instanceof Error ? err.message : 'Approve gagal.');
        }
    };

    const deleteSection = async (sectionId: number) => {
        if (!window.confirm('Hapus section ini? (versi tersimpan tetap ada di Version History)')) return;

        try {
            const res = await fetch(
                route('projects.prd.sections.destroy', { project: project.id, section: sectionId }),
                { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf() } },
            );

            if (!res.ok) throw new Error();

            setSections((prev) => prev.filter((s) => s.id !== sectionId));

            if (selectedId === sectionId) {
                setSelectedId(sections.find((s) => s.id !== sectionId)?.id ?? null);
            }

            flashToast('Section dihapus.');
        } catch {
            setAiError('Gagal menghapus section.');
        }
    };

    const moveSection = async (sectionId: number, direction: 'up' | 'down') => {
        const ordered = [...sections].sort((a, b) => a.order - b.order);
        const idx = ordered.findIndex((s) => s.id === sectionId);
        const swapWith = direction === 'up' ? idx - 1 : idx + 1;

        if (swapWith < 0 || swapWith >= ordered.length) return;

        const reordered = [...ordered];
        [reordered[idx], reordered[swapWith]] = [reordered[swapWith], reordered[idx]];
        const newSections = reordered.map((s, i) => ({ ...s, order: i }));

        setSections(newSections); // optimistic

        try {
            const res = await fetch(route('projects.prd.sections.reorder', { project: project.id }), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ order: newSections.map((s) => s.id) }),
            });

            if (!res.ok) throw new Error();

            flashToast('Urutan section diperbarui.');
        } catch {
            setSections(sections); // rollback
            setAiError('Gagal mengubah urutan.');
        }
    };

    const statusTone = (sidebar.statuses[project.status]?.tone ?? 'muted') as 'ok';
    const statusLabel = sidebar.statuses[project.status]?.label ?? project.status;
    const isApproved = project.status === 'approved';

    return (
        <AppShell user={auth.user} activeCount={sidebar.activeProjects} current="Projects">
            <Head title={`${prd.title} — PRD Workspace`} />

            <div className="flex min-h-0 flex-1">
                {/* Section outline */}
                <aside className="hidden w-64 shrink-0 flex-col overflow-y-auto border-r border-line bg-surface md:flex">
                    <div className="border-b border-line px-4 py-3">
                        <a
                            href={`/projects/${project.id}`}
                            className="flex items-center gap-1 font-mono text-[11px] text-ink-3 hover:text-ink"
                        >
                            <ArrowLeft size={12} />
                            {project.name}
                        </a>
                        <div className="mt-2 flex items-center gap-2">
                            <StatusPill label={statusLabel} tone={statusTone} />
                        </div>
                    </div>

                    <div className="flex-1 space-y-0.5 p-2">
                        {sections.map((s) => (
                            <div
                                key={s.id}
                                className={cn(
                                    'group/outline flex items-center gap-0.5 rounded',
                                    selectedId === s.id && 'bg-surface-3',
                                )}
                            >
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSelectedId(s.id);
                                        setEditing(false);
                                        setProposal(null);
                                    }}
                                    className={cn(
                                        'flex min-w-0 flex-1 items-center justify-between gap-2 rounded px-2.5 py-2 text-left text-sm transition-colors',
                                        selectedId === s.id
                                            ? 'text-ink'
                                            : 'text-ink-3 hover:bg-surface-2 hover:text-ink',
                                    )}
                                >
                                    <span className="truncate">{s.title}</span>
                                    <span className="font-mono text-[10px] text-ink-ghost">
                                        {String(s.order + 1).padStart(2, '0')}
                                    </span>
                                </button>
                                <div className="mr-1 hidden flex-col group-hover/outline:flex">
                                    <button
                                        type="button"
                                        onClick={() => moveSection(s.id, 'up')}
                                        disabled={s.order === 0}
                                        title="Pindah ke atas"
                                        className="rounded px-1 text-[9px] leading-none text-ink-3 hover:text-ink disabled:opacity-30"
                                    >
                                        ▲
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => moveSection(s.id, 'down')}
                                        disabled={s.order === sections.length - 1}
                                        title="Pindah ke bawah"
                                        className="rounded px-1 text-[9px] leading-none text-ink-3 hover:text-ink disabled:opacity-30"
                                    >
                                        ▼
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="space-y-2 border-t border-line p-3">
                        <Button size="sm" className="w-full" onClick={saveVersion} disabled={savingVersion}>
                            {savingVersion ? <Loader2 size={13} className="animate-spin" /> : <GitBranch size={13} />}
                            Save Version
                        </Button>
                        {!isApproved && (
                            <Button
                                size="sm"
                                variant="primary"
                                className="w-full"
                                onClick={approvePrd}
                            >
                                <ShieldCheck size={13} />
                                Approve PRD
                            </Button>
                        )}
                    </div>
                </aside>

                {/* Main editor */}
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div className="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-line bg-surface px-3 py-2.5 md:px-6 md:py-3">
                        <div className="min-w-0">
                            <a
                                href={`/projects/${project.id}`}
                                className="mb-1 flex items-center gap-1 font-mono text-[11px] text-ink-3 hover:text-ink md:hidden"
                            >
                                <ArrowLeft size={12} />
                                {project.name}
                            </a>
                            <div className="truncate text-sm font-semibold text-ink">{title}</div>
                            <div className="mt-0.5 font-mono text-[11px] text-ink-3">
                                {sections.length} sections · {versionList.length} versions
                            </div>
                        </div>

                        {/* Mobile section nav */}
                        <select
                            value={selectedId ?? ''}
                            onChange={(e) => {
                                setSelectedId(Number(e.target.value));
                                setEditing(false);
                                setProposal(null);
                            }}
                            className="h-8 max-w-[60%] rounded border border-line bg-canvas px-2 text-xs text-ink outline-none focus:border-accent md:hidden"
                        >
                            {sections.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {String(s.order + 1).padStart(2, '0')}. {s.title}
                                </option>
                            ))}
                        </select>

                        <div className="flex items-center gap-1.5 md:gap-2">
                            {/* Mobile-only actions */}
                            <Button
                                size="sm"
                                className="md:hidden"
                                onClick={saveVersion}
                                disabled={savingVersion}
                                title="Save Version"
                            >
                                <GitBranch size={13} />
                            </Button>
                            {!isApproved && (
                                <Button
                                    size="sm"
                                    variant="primary"
                                    className="md:hidden"
                                    onClick={approvePrd}
                                    title="Approve PRD"
                                >
                                    <ShieldCheck size={13} />
                                </Button>
                            )}
                            <a
                                href={route('projects.prd.export', { project: project.id })}
                                className="hidden sm:block"
                                title="Export .md"
                            >
                                <Button size="sm">
                                    <Download size={13} />
                                    Export
                                </Button>
                            </a>
                            <a
                                href={route('projects.prd.exportPdf', { project: project.id })}
                                className="hidden sm:block"
                                title="Export PDF"
                            >
                                <Button size="sm">
                                    <FileText size={13} />
                                    PDF
                                </Button>
                            </a>
                            <Button size="sm" onClick={runReview} disabled={reviewBusy}>
                                {reviewBusy ? (
                                    <Loader2 size={13} className="animate-spin" />
                                ) : (
                                    <AlertTriangle size={13} />
                                )}
                                <span className="hidden sm:inline">AI Review</span>
                            </Button>
                        </div>
                    </div>

                    {toast && (
                        <div className="border-b border-[rgba(16,185,129,0.3)] bg-[rgba(16,185,129,0.08)] px-4 py-2 text-sm text-ok md:px-6">
                            {toast}
                        </div>
                    )}
                    {aiError && (
                        <div className="flex items-center justify-between border-b border-[rgba(244,63,94,0.3)] bg-[rgba(244,63,94,0.08)] px-4 py-2 text-sm text-risk md:px-6">
                            {aiError}
                            <button type="button" onClick={() => setAiError(null)} className="underline">
                                tutup
                            </button>
                        </div>
                    )}

                    <div className="min-h-0 flex-1 overflow-y-auto px-4 py-5 md:px-6">
                        <div className="mx-auto max-w-3xl space-y-4">
                            {review && (
                                <div className="rounded-lg border border-line bg-surface-2 p-4">
                                    <div className="flex items-center gap-2">
                                        {review.verdict === 'pass' ? (
                                            <CheckCircle2 size={16} className="text-ok" />
                                        ) : (
                                            <AlertTriangle size={16} className="text-warn" />
                                        )}
                                        <span className="text-sm font-semibold text-ink">
                                            AI Review: {review.verdict === 'pass' ? 'Pass' : 'Needs Work'}
                                        </span>
                                    </div>

                                    {review.gaps.length > 0 && (
                                        <div className="mt-3">
                                            <div className="font-mono text-[10px] uppercase tracking-wider text-warn">
                                                Gaps
                                            </div>
                                            <ul className="mt-1 space-y-1">
                                                {review.gaps.map((g, i) => (
                                                    <li key={i} className="text-sm text-ink-2">• {g}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                    {review.contradictions.length > 0 && (
                                        <div className="mt-3">
                                            <div className="font-mono text-[10px] uppercase tracking-wider text-risk">
                                                Contradictions
                                            </div>
                                            <ul className="mt-1 space-y-1">
                                                {review.contradictions.map((c, i) => (
                                                    <li key={i} className="text-sm text-ink-2">• {c}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                    {review.suggestions.length > 0 && (
                                        <div className="mt-3">
                                            <div className="font-mono text-[10px] uppercase tracking-wider text-info">
                                                Suggestions
                                            </div>
                                            <ul className="mt-1 space-y-1">
                                                {review.suggestions.map((s, i) => (
                                                    <li key={i} className="text-sm text-ink-2">• {s}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </div>
                            )}

                            {selected && (
                                <>
                                    <div className="flex items-center justify-between gap-3">
                                        <h2 className="text-lg font-semibold tracking-tight text-ink">
                                            {selected.title}
                                        </h2>
                                        <div className="flex items-center gap-1.5">
                                            {!editing ? (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        onClick={() => {
                                                            setEditContent(selected.content);
                                                            setEditing(true);
                                                        }}
                                                    >
                                                        <Pencil size={13} />
                                                        <span className="hidden sm:inline">Edit</span>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="danger"
                                                        onClick={() => deleteSection(selected.id)}
                                                        title="Hapus section"
                                                    >
                                                        <Trash2 size={13} />
                                                    </Button>
                                                </>
                                            ) : (
                                                <>
                                                    <Button size="sm" onClick={() => setEditing(false)}>
                                                        <X size={13} />
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="primary"
                                                        onClick={saveSection}
                                                        disabled={saving}
                                                    >
                                                        {saving ? (
                                                            <Loader2 size={13} className="animate-spin" />
                                                        ) : (
                                                            <Save size={13} />
                                                        )}
                                                        Save
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    {editing ? (
                                        <textarea
                                            value={editContent}
                                            onChange={(e) => setEditContent(e.target.value)}
                                            rows={18}
                                            className="w-full resize-y rounded-lg border border-line bg-canvas px-4 py-3 font-mono text-sm leading-relaxed text-ink outline-none focus:border-accent focus:shadow-[0_0_0_1px_#6366f1]"
                                        />
                                    ) : (
                                        <div className="rounded-lg border border-line bg-surface px-4 py-3">
                                            <Markdown content={selected.content} />
                                        </div>
                                    )}

                                    {/* AI actions */}
                                    <div className="flex flex-wrap items-center gap-2 border-t border-line pt-4">
                                        <span className="flex items-center gap-1 font-mono text-[10px] uppercase tracking-wider text-ink-3">
                                            <Sparkles size={12} className="text-accent" />
                                            AI Actions
                                        </span>
                                        {AI_ACTIONS.map((a) => (
                                            <Button
                                                key={a.key}
                                                size="sm"
                                                onClick={() => runAiAction(a.key)}
                                                disabled={aiBusy !== null}
                                                title={a.label}
                                            >
                                                {aiBusy === a.key ? (
                                                    <Loader2 size={12} className="animate-spin" />
                                                ) : (
                                                    <Wand2 size={12} />
                                                )}
                                                <span className="hidden sm:inline">{a.label}</span>
                                            </Button>
                                        ))}
                                    </div>

                                    {/* Proposal diff */}
                                    {proposal && (
                                        <div className="rounded-lg border border-line-strong bg-surface-3 p-4">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div className="flex items-center gap-2">
                                                    <Sparkles size={14} className="text-accent" />
                                                    <span className="text-sm font-semibold text-ink">
                                                        AI Proposal — {proposal.action}
                                                    </span>
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button size="sm" onClick={() => setProposal(null)}>
                                                        Discard
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="primary"
                                                        onClick={applyProposal}
                                                        disabled={saving || proposal.original === null}
                                                    >
                                                        {proposal.original === null ? 'Info only' : 'Apply Changes'}
                                                    </Button>
                                                </div>
                                            </div>
                                            {proposal.changelog && (
                                                <p className="mt-2 text-xs text-ink-3">{proposal.changelog}</p>
                                            )}
                                            <div className="mt-3 grid gap-2 md:grid-cols-2">
                                                {proposal.original !== null && (
                                                    <div>
                                                        <div className="mb-1 font-mono text-[10px] uppercase tracking-wider text-risk">
                                                            Current
                                                        </div>
                                                        <div className="max-h-72 overflow-y-auto rounded border border-line bg-surface-2 p-3 text-xs leading-relaxed text-ink-3">
                                                            <Markdown content={proposal.original} compact />
                                                        </div>
                                                    </div>
                                                )}
                                                <div>
                                                    <div className="mb-1 font-mono text-[10px] uppercase tracking-wider text-ok">
                                                        Proposed
                                                    </div>
                                                    <div className="max-h-72 overflow-y-auto rounded border border-[rgba(16,185,129,0.3)] bg-[rgba(16,185,129,0.08)] p-3 text-xs leading-relaxed text-ink">
                                                        <Markdown content={proposal.content} compact />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Mobile version history */}
                                    {versionList.length > 0 && (
                                        <div className="rounded-lg border border-line bg-surface-2 p-3 xl:hidden">
                                            <div className="mb-2 flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-wider text-ink-3">
                                                <GitBranch size={12} />
                                                Version History
                                            </div>
                                            <div className="flex gap-2 overflow-x-auto pb-1">
                                                {versionList.map((v) => (
                                                    <button
                                                        key={v.id}
                                                        type="button"
                                                        onClick={() => loadVersion(v.id)}
                                                        className="shrink-0 rounded border border-line bg-surface px-3 py-2 text-left transition-colors hover:border-line-strong"
                                                    >
                                                        <div className="font-mono text-xs font-medium text-accent">
                                                            {v.version}
                                                        </div>
                                                        <div className="font-mono text-[10px] text-ink-ghost">
                                                            {v.section_count} sections
                                                        </div>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </div>

                {/* Version history */}
                <aside className="hidden w-72 shrink-0 flex-col border-l border-line bg-surface xl:flex">
                    <div className="flex items-center gap-2 border-b border-line px-4 py-3">
                        <GitBranch size={14} className="text-ink-3" />
                        <span className="text-sm font-semibold text-ink">Version History</span>
                    </div>

                    <div className="flex-1 space-y-2 overflow-y-auto p-3">
                        {versionList.length === 0 ? (
                            <p className="px-1 py-6 text-center text-xs text-ink-ghost">
                                Belum ada versi tersimpan.
                            </p>
                        ) : (
                            versionList.map((v) => (
                                <button
                                    key={v.id}
                                    type="button"
                                    onClick={() => loadVersion(v.id)}
                                    className="w-full rounded-lg border border-line bg-surface-2 p-3 text-left transition-colors hover:border-line-strong"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-mono text-xs font-medium text-accent">
                                            {v.version}
                                        </span>
                                        <span className="font-mono text-[10px] text-ink-ghost">
                                            {v.section_count} sections
                                        </span>
                                    </div>
                                    {v.label && (
                                        <div className="mt-1 text-xs text-ink-2">{v.label}</div>
                                    )}
                                    <div className="mt-0.5 font-mono text-[10px] text-ink-ghost">
                                        {new Date(v.created_at).toLocaleString('id-ID')}
                                    </div>
                                </button>
                            ))
                        )}
                    </div>
                </aside>
            </div>

            {/* Version snapshot modal */}
            {versionSnapshot && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
                    onClick={() => setVersionSnapshot(null)}
                >
                    <div
                        className="max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-line-strong bg-surface-3 p-5"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="font-semibold text-ink">
                                Snapshot {versionSnapshot.version} (immutable)
                            </h3>
                            <button
                                type="button"
                                onClick={() => setVersionSnapshot(null)}
                                className="rounded p-1 text-ink-3 hover:bg-surface-2 hover:text-ink"
                            >
                                <X size={16} />
                            </button>
                        </div>
                        <pre className="overflow-x-auto rounded border border-line bg-canvas p-3 font-mono text-[11px] leading-relaxed text-ink-2">
                            {JSON.stringify(versionSnapshot.snapshot, null, 2)}
                        </pre>
                    </div>
                </div>
            )}
        </AppShell>
    );
}
