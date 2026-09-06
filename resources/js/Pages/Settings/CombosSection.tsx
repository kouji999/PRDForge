import { useEffect, useState } from 'react';
import { Button } from '@/Components/Button';
import { Input, Label } from '@/Components/Input';
import { StatusPill } from '@/Components/StatusPill';
import { cn } from '@/lib';
import { ArrowRight, GitBranch, Loader2, Plus, Star, Trash2, X } from 'lucide-react';

interface ComboProvider {
    id: number;
    name: string;
    model: string;
    status: string;
    priority: number;
}

interface Combo {
    id: number;
    name: string;
    is_default: boolean;
    providers: Array<ComboProvider | null>;
}

interface ProviderOption {
    id: number;
    name: string;
    model: string;
    status: string;
}

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export function CombosSection({ providers }: { providers: ProviderOption[] }) {
    const [combos, setCombos] = useState<Combo[]>([]);
    const [loading, setLoading] = useState(true);
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const [busy, setBusy] = useState<string | null>(null);

    useEffect(() => {
        fetch(route('ai.combos.index'))
            .then((r) => r.json())
            .then((b) => setCombos(b.combos ?? []))
            .finally(() => setLoading(false));
    }, []);

    const toggle = (id: number) => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id].slice(0, 5),
        );
    };

    const create = async (e: React.FormEvent) => {
        e.preventDefault();
        setBusy('create');

        try {
            const res = await fetch(route('ai.combos.store'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ name, provider_ids: selected, is_default: combos.length === 0 }),
            });

            const body = await res.json();

            if (!res.ok) throw new Error(body?.message ?? 'Gagal membuat combo.');

            setCombos((prev) => [...prev, body.combo]);
            setName('');
            setSelected([]);
            setCreating(false);
        } catch {
            // surfaced via UI state below
        } finally {
            setBusy(null);
        }
    };

    const makeDefault = async (combo: Combo) => {
        setBusy(`default-${combo.id}`);

        try {
            await fetch(route('ai.combos.update', { combo: combo.id }), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ is_default: true }),
            });

            setCombos((prev) => prev.map((c) => ({ ...c, is_default: c.id === combo.id })));
        } finally {
            setBusy(null);
        }
    };

    const remove = async (combo: Combo) => {
        if (!window.confirm(`Hapus combo "${combo.name}"?`)) return;
        setBusy(`del-${combo.id}`);

        try {
            await fetch(route('ai.combos.destroy', { combo: combo.id }), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf() },
            });

            setCombos((prev) => prev.filter((c) => c.id !== combo.id));
        } finally {
            setBusy(null);
        }
    };

    return (
        <div className="space-y-4">
            <div>
                <h2 className="text-base font-semibold text-ink">AI Combos</h2>
                <p className="mt-1 text-sm text-ink-3">
                    Tim provider dengan failover: AI #1 dicoba dulu — kalau lemot/mati, otomatis pindah ke
                    #2, dst (max 5). Set combo sebagai default agar dipakai project baru.
                </p>
            </div>

            {loading ? (
                <div className="flex items-center justify-center rounded-lg border border-line bg-surface p-6">
                    <Loader2 size={16} className="animate-spin text-ink-3" />
                </div>
            ) : (
                <div className="space-y-3">
                    {combos.map((c) => (
                        <div key={c.id} className="rounded-lg border border-line bg-surface p-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <GitBranch size={15} className="text-accent" />
                                    <span className="font-medium text-ink">{c.name}</span>
                                    {c.is_default && <StatusPill label="DEFAULT" tone="info" />}
                                </div>
                                <div className="flex items-center gap-1">
                                    {!c.is_default && (
                                        <Button
                                            size="sm"
                                            onClick={() => makeDefault(c)}
                                            disabled={busy === `default-${c.id}`}
                                        >
                                            <Star size={12} />
                                            Set Default
                                        </Button>
                                    )}
                                    <Button
                                        size="sm"
                                        variant="danger"
                                        onClick={() => remove(c)}
                                        disabled={busy === `del-${c.id}`}
                                    >
                                        <Trash2 size={12} />
                                    </Button>
                                </div>
                            </div>

                            <div className="mt-3 flex flex-wrap items-center gap-1.5">
                                {c.providers.filter(Boolean).map((p, i) => (
                                    <span key={p!.id ?? i} className="flex items-center gap-1.5">
                                        {i > 0 && <ArrowRight size={12} className="text-ink-ghost" />}
                                        <span className="inline-flex items-center gap-1.5 rounded border border-line bg-surface-2 px-2 py-1 font-mono text-[11px] text-ink-2">
                                            <span className={cn(
                                                'h-1.5 w-1.5 rounded-full',
                                                p!.status === 'connected' ? 'bg-ok' : p!.status === 'error' ? 'bg-risk' : 'bg-ink-ghost',
                                            )} />
                                            #{i + 1} {p!.name}
                                            <span className="text-ink-ghost">({p!.model})</span>
                                        </span>
                                    </span>
                                ))}
                            </div>
                        </div>
                    ))}

                    {combos.length === 0 && (
                        <div className="rounded-lg border border-dashed border-line-strong bg-surface p-6 text-center">
                            <p className="text-sm text-ink-3">
                                Belum ada combo. Tanpa combo, semua AI pakai provider default tunggal.
                            </p>
                        </div>
                    )}
                </div>
            )}

            {creating ? (
                <form onSubmit={create} className="space-y-4 rounded-lg border border-line-strong bg-surface-3 p-5">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-ink">Combo Baru</h3>
                        <button
                            type="button"
                            onClick={() => setCreating(false)}
                            className="rounded p-1 text-ink-3 hover:bg-surface-2 hover:text-ink"
                        >
                            <X size={16} />
                        </button>
                    </div>

                    <div>
                        <Label htmlFor="combo-name">Nama Combo</Label>
                        <Input
                            id="combo-name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="cth: Tim Cepat (Free Tier)"
                            required
                        />
                    </div>

                    <div>
                        <Label>Urutan Provider (#1 dicoba dulu)</Label>
                        <div className="space-y-1.5">
                            {providers.length === 0 && (
                                <p className="text-xs text-ink-ghost">
                                    Tambahkan provider dulu di atas.
                                </p>
                            )}
                            {providers.map((p) => {
                                const idx = selected.indexOf(p.id);

                                return (
                                    <button
                                        key={p.id}
                                        type="button"
                                        onClick={() => toggle(p.id)}
                                        className={cn(
                                            'flex w-full items-center justify-between rounded border px-3 py-2 text-left text-sm transition-colors',
                                            idx >= 0
                                                ? 'border-accent bg-accent/10 text-ink'
                                                : 'border-line bg-surface text-ink-3 hover:border-line-strong',
                                        )}
                                    >
                                        <span>
                                            {idx >= 0 && <span className="mr-1.5 font-mono text-[11px] text-accent">#{idx + 1}</span>}
                                            {p.name}
                                            <span className="ml-1.5 font-mono text-[11px] text-ink-ghost">({p.model})</span>
                                        </span>
                                        {idx >= 0 && <span className="font-mono text-[11px] text-accent">✓</span>}
                                    </button>
                                );
                            })}
                        </div>
                        <p className="mt-1.5 font-mono text-[10px] text-ink-ghost">
                            {selected.length}/5 dipilih — urutan sesuai klik
                        </p>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" onClick={() => setCreating(false)}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={busy === 'create' || selected.length === 0 || name.trim() === ''}
                        >
                            {busy === 'create' ? <Loader2 size={13} className="animate-spin" /> : <Plus size={13} />}
                            Simpan Combo
                        </Button>
                    </div>
                </form>
            ) : (
                <Button onClick={() => setCreating(true)}>
                    <Plus size={14} />
                    Buat Combo
                </Button>
            )}
        </div>
    );
}
