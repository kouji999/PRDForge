import { useMemo, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Input, Label, Textarea } from '@/Components/Input';
import { StatusPill } from '@/Components/StatusPill';
import { cn } from '@/lib';

interface RequirementItem {
    id: number;
    type: string;
    title: string;
    content: string;
    status: string;
    source: string;
    priority: string;
}

const STATUS_TONES: Record<string, 'ok' | 'warn' | 'risk' | 'info' | 'muted'> = {
    confirmed: 'ok',
    proposed: 'info',
    needs_review: 'warn',
    rejected: 'risk',
};

const PRIORITY_TONES: Record<string, string> = {
    critical: 'text-risk',
    high: 'text-warn',
    medium: 'text-ink-2',
    low: 'text-ink-3',
};

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export function RequirementsPanel({
    requirements,
    projectId,
}: {
    requirements: RequirementItem[];
    projectId: number;
}) {
    const [items, setItems] = useState(requirements);
    const [filter, setFilter] = useState<'all' | 'proposed' | 'confirmed'>('all');
    const [adding, setAdding] = useState(false);
    const [form, setForm] = useState({
        type: 'functional',
        title: '',
        content: '',
        priority: 'medium',
    });
    const [busy, setBusy] = useState<number | 'new' | null>(null);
    const [error, setError] = useState<string | null>(null);

    const visible = useMemo(
        () => (filter === 'all' ? items : items.filter((r) => r.status === filter)),
        [items, filter],
    );

    const setStatus = async (id: number, status: string) => {
        setBusy(id);
        setError(null);

        try {
            const res = await fetch(route('projects.requirements.update', { project: projectId, requirement: id }), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ status }),
            });

            if (!res.ok) throw new Error();

            setItems((prev) => prev.map((r) => (r.id === id ? { ...r, status } : r)));
        } catch {
            setError('Gagal update status requirement.');
        } finally {
            setBusy(null);
        }
    };

    const remove = async (id: number) => {
        setBusy(id);
        setError(null);

        try {
            const res = await fetch(route('projects.requirements.destroy', { project: projectId, requirement: id }), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf() },
            });

            if (!res.ok) throw new Error();

            setItems((prev) => prev.filter((r) => r.id !== id));
        } catch {
            setError('Gagal menghapus requirement.');
        } finally {
            setBusy(null);
        }
    };

    const create = async (e: React.FormEvent) => {
        e.preventDefault();
        setBusy('new');
        setError(null);

        try {
            const res = await fetch(route('projects.requirements.store', { project: projectId }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(form),
            });

            const body = await res.json();

            if (!res.ok) throw new Error(body?.message ?? 'Gagal menambah requirement.');

            setItems((prev) => [...prev, body.requirement]);
            setForm({ type: 'functional', title: '', content: '', priority: 'medium' });
            setAdding(false);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Gagal menambah requirement.');
        } finally {
            setBusy(null);
        }
    };

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center gap-2 border-b border-line px-3 py-2">
                {(['all', 'proposed', 'confirmed'] as const).map((f) => (
                    <button
                        key={f}
                        type="button"
                        onClick={() => setFilter(f)}
                        className={cn(
                            'rounded px-2 py-1 font-mono text-[11px] transition-colors',
                            filter === f ? 'bg-surface-3 text-ink' : 'text-ink-3 hover:text-ink',
                        )}
                    >
                        {f === 'all' ? 'All' : f}
                        <span className="ml-1 text-ink-ghost">
                            {f === 'all' ? items.length : items.filter((r) => r.status === f).length}
                        </span>
                    </button>
                ))}
                <button
                    type="button"
                    onClick={() => setAdding((v) => !v)}
                    className="ml-auto rounded p-1 text-ink-3 hover:bg-surface-2 hover:text-ink"
                >
                    <Plus size={15} />
                </button>
            </div>

            {error && <p className="border-b border-line px-3 py-2 text-xs text-risk">{error}</p>}

            {adding && (
                <form onSubmit={create} className="space-y-2.5 border-b border-line bg-surface-2 p-3">
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <Label>Type</Label>
                            <select
                                value={form.type}
                                onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
                                className="h-8 w-full rounded border border-line bg-canvas px-2 text-sm text-ink outline-none focus:border-accent"
                            >
                                <option value="functional">Functional</option>
                                <option value="non_functional">Non-functional</option>
                            </select>
                        </div>
                        <div>
                            <Label>Priority</Label>
                            <select
                                value={form.priority}
                                onChange={(e) => setForm((f) => ({ ...f, priority: e.target.value }))}
                                className="h-8 w-full rounded border border-line bg-canvas px-2 text-sm text-ink outline-none focus:border-accent"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <Label>Title</Label>
                        <Input
                            value={form.title}
                            onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                            placeholder="REQ singkat..."
                            required
                        />
                    </div>
                    <div>
                        <Label>Detail</Label>
                        <Textarea
                            rows={2}
                            value={form.content}
                            onChange={(e) => setForm((f) => ({ ...f, content: e.target.value }))}
                            placeholder="Deskripsi requirement..."
                            required
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button size="sm" type="button" onClick={() => setAdding(false)}>
                            Batal
                        </Button>
                        <Button size="sm" type="submit" variant="primary" disabled={busy === 'new'}>
                            Tambah
                        </Button>
                    </div>
                </form>
            )}

            <div className="flex-1 space-y-2 overflow-y-auto p-3">
                {visible.length === 0 && (
                    <p className="px-1 py-6 text-center text-xs text-ink-ghost">
                        Belum ada requirement. Klik Extract di chat, atau tambah manual.
                    </p>
                )}

                {visible.map((r) => (
                    <div key={r.id} className="rounded-lg border border-line bg-surface-2 p-2.5">
                        <div className="flex items-center justify-between gap-2">
                            <div className="flex items-center gap-1.5">
                                <StatusPill label={r.status} tone={STATUS_TONES[r.status] ?? 'muted'} />
                                <span className={cn('font-mono text-[10px] uppercase', PRIORITY_TONES[r.priority])}>
                                    {r.priority}
                                </span>
                            </div>
                            <div className="flex items-center gap-0.5">
                                {r.status !== 'confirmed' && (
                                    <button
                                        type="button"
                                        onClick={() => setStatus(r.id, 'confirmed')}
                                        disabled={busy === r.id}
                                        title="Confirm"
                                        className="rounded p-1 text-ink-3 hover:bg-surface-3 hover:text-ok"
                                    >
                                        ✓
                                    </button>
                                )}
                                {r.status !== 'rejected' && (
                                    <button
                                        type="button"
                                        onClick={() => setStatus(r.id, 'rejected')}
                                        disabled={busy === r.id}
                                        title="Reject"
                                        className="rounded p-1 text-ink-3 hover:bg-surface-3 hover:text-risk"
                                    >
                                        ×
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => remove(r.id)}
                                    disabled={busy === r.id}
                                    title="Delete"
                                    className="rounded p-1 text-ink-3 hover:bg-surface-3 hover:text-risk"
                                >
                                    <Trash2 size={12} />
                                </button>
                            </div>
                        </div>
                        <div className="mt-2 text-sm font-medium leading-snug text-ink">{r.title}</div>
                        <p className="mt-1 text-xs leading-relaxed text-ink-3">{r.content}</p>
                        <div className="mt-1.5 font-mono text-[10px] text-ink-ghost">
                            {r.type} · {r.source}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
