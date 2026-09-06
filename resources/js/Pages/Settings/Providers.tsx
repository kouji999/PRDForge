import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { AppShell } from '@/Components/AppShell';
import { Button } from '@/Components/Button';
import { Input, Label } from '@/Components/Input';
import { StatusPill } from '@/Components/StatusPill';
import { CombosSection } from './CombosSection';
import { CheckCircle2, Loader2, Plug, Plus, Star, Trash2, X, Zap } from 'lucide-react';

interface Provider {
    id: number;
    name: string;
    base_url: string;
    model: string;
    status: string;
    last_error: string | null;
    is_default: boolean;
    last_tested_at: string | null;
}

interface Props {
    auth: { user: import('@/types').User };
    sidebar: import('@/Pages/Dashboard').SidebarShared;
}

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const EMPTY_FORM = { name: '', base_url: '', api_key: '', model: '' };

export default function ProviderSettings({ auth, sidebar }: Props) {
    const [providers, setProviders] = useState<Provider[]>([]);
    const [loading, setLoading] = useState(true);
    const [adding, setAdding] = useState(false);
    const [form, setForm] = useState(EMPTY_FORM);
    const [busy, setBusy] = useState<string | null>(null);
    const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
    const [testResult, setTestResult] = useState<{ ok: boolean; text: string } | null>(null);

    useEffect(() => {
        fetch(route('ai.providers.index'))
            .then((r) => r.json())
            .then((b) => setProviders(b.providers ?? []))
            .finally(() => setLoading(false));
    }, []);

    const flash = (ok: boolean, text: string) => {
        setMessage({ ok, text });
        setTimeout(() => setMessage(null), 4000);
    };

    const create = async (e: React.FormEvent) => {
        e.preventDefault();
        setBusy('create');

        try {
            const res = await fetch(route('ai.providers.store'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(form),
            });

            const body = await res.json();

            if (!res.ok) throw new Error(body?.message ?? 'Gagal menyimpan provider.');

            setProviders((prev) => [...prev, body.provider]);
            setForm(EMPTY_FORM);
            setAdding(false);
            flash(true, 'Provider tersimpan. Klik Test Connection untuk verifikasi.');
        } catch (err) {
            flash(false, err instanceof Error ? err.message : 'Gagal menyimpan provider.');
        } finally {
            setBusy(null);
        }
    };

    const test = async (provider?: Provider) => {
        setBusy(provider ? `test-${provider.id}` : 'test-form');

        try {
            const res = await fetch(
                provider
                    ? route('ai.providers.test', { provider: provider.id })
                    : route('ai.providers.test-unsaved'),
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: provider ? '{}' : JSON.stringify(form),
                },
            );

            const body = await res.json();

            setTestResult({ ok: body.connected, text: body.message });

            if (provider) {
                setProviders((prev) =>
                    prev.map((p) =>
                        p.id === provider.id
                            ? { ...p, status: body.connected ? 'connected' : 'error', last_error: body.connected ? null : body.message }
                            : p,
                    ),
                );
            }
        } catch {
            setTestResult({ ok: false, text: 'Test connection gagal dijalankan.' });
        } finally {
            setBusy(null);
        }
    };

    const makeDefault = async (provider: Provider) => {
        setBusy(`default-${provider.id}`);

        try {
            await fetch(route('ai.providers.update', { provider: provider.id }), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ is_default: true }),
            });

            setProviders((prev) =>
                prev.map((p) => ({ ...p, is_default: p.id === provider.id })),
            );
            flash(true, `${provider.name} sekarang default provider.`);
        } catch {
            flash(false, 'Gagal mengubah default.');
        } finally {
            setBusy(null);
        }
    };

    const remove = async (provider: Provider) => {
        setBusy(`del-${provider.id}`);

        try {
            const res = await fetch(route('ai.providers.destroy', { provider: provider.id }), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf() },
            });

            if (!res.ok) throw new Error();

            setProviders((prev) => prev.filter((p) => p.id !== provider.id));
            flash(true, 'Provider dihapus.');
        } catch {
            flash(false, 'Gagal menghapus provider.');
        } finally {
            setBusy(null);
        }
    };

    return (
        <AppShell user={auth.user} activeCount={sidebar.activeProjects} current="Settings">
            <Head title="AI Providers" />

            <div className="flex-1 overflow-y-auto p-4 md:p-6">
                <div className="mx-auto max-w-3xl space-y-5">
                    <div>
                        <div className="font-mono text-[11px] uppercase tracking-wider text-ink-3">
                            Settings // AI Gateway
                        </div>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-ink">AI Providers</h1>
                        <p className="mt-1 text-sm text-ink-3">
                            Konfigurasi provider AI (OpenAI-compatible). API key dienkripsi dan tidak pernah
                            ditampilkan kembali.
                        </p>
                    </div>

                    {message && (
                        <div
                            className={
                                message.ok
                                    ? 'rounded-lg border border-[rgba(16,185,129,0.3)] bg-[rgba(16,185,129,0.08)] px-3 py-2 text-sm text-ok'
                                    : 'rounded-lg border border-[rgba(244,63,94,0.3)] bg-[rgba(244,63,94,0.08)] px-3 py-2 text-sm text-risk'
                            }
                        >
                            {message.text}
                        </div>
                    )}

                    {/* Provider list */}
                    {loading ? (
                        <div className="flex items-center justify-center rounded-lg border border-line bg-surface p-8">
                            <Loader2 size={18} className="animate-spin text-ink-3" />
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {providers.map((p) => (
                                <div key={p.id} className="rounded-lg border border-line bg-surface p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-ink">{p.name}</span>
                                            {p.is_default && <StatusPill label="DEFAULT" tone="info" />}
                                            <StatusPill
                                                label={p.status}
                                                tone={
                                                    p.status === 'connected'
                                                        ? 'ok'
                                                        : p.status === 'error'
                                                          ? 'risk'
                                                          : 'muted'
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center gap-1">
                                            {!p.is_default && (
                                                <Button
                                                    size="sm"
                                                    onClick={() => makeDefault(p)}
                                                    disabled={busy === `default-${p.id}`}
                                                >
                                                    <Star size={12} />
                                                    Set Default
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                onClick={() => test(p)}
                                                disabled={busy === `test-${p.id}`}
                                            >
                                                {busy === `test-${p.id}` ? (
                                                    <Loader2 size={12} className="animate-spin" />
                                                ) : (
                                                    <Plug size={12} />
                                                )}
                                                Test
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="danger"
                                                onClick={() => remove(p)}
                                                disabled={busy === `del-${p.id}`}
                                            >
                                                <Trash2 size={12} />
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="mt-2.5 space-y-1 font-mono text-[11px] text-ink-3">
                                        <div className="truncate">{p.base_url}</div>
                                        <div className="flex items-center gap-1.5">
                                            <Zap size={11} className="text-accent" />
                                            {p.model}
                                        </div>
                                    </div>
                                    {p.last_error && (
                                        <p className="mt-2 rounded border border-[rgba(244,63,94,0.3)] bg-[rgba(244,63,94,0.08)] px-2 py-1.5 text-xs text-risk">
                                            {p.last_error}
                                        </p>
                                    )}
                                </div>
                            ))}

                            {providers.length === 0 && (
                                <div className="rounded-lg border border-dashed border-line-strong bg-surface p-8 text-center">
                                    <p className="text-sm text-ink-3">
                                        Belum ada provider. Tambahkan untuk mulai pakai AI.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Add / test form */}
                    {adding ? (
                        <form
                            onSubmit={create}
                            className="space-y-4 rounded-lg border border-line-strong bg-surface-3 p-5"
                        >
                            <div className="flex items-center justify-between">
                                <h2 className="font-semibold text-ink">Provider Baru</h2>
                                <button
                                    type="button"
                                    onClick={() => setAdding(false)}
                                    className="rounded p-1 text-ink-3 hover:bg-surface-2 hover:text-ink"
                                >
                                    <X size={16} />
                                </button>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="p-name">Nama</Label>
                                    <Input
                                        id="p-name"
                                        value={form.name}
                                        onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                                        placeholder="9Router / OpenAI / Groq..."
                                        required
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="p-model">Model</Label>
                                    <Input
                                        id="p-model"
                                        value={form.model}
                                        onChange={(e) => setForm((f) => ({ ...f, model: e.target.value }))}
                                        placeholder="cth: gpt-4o / claude-3-5-sonnet"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="p-url">Base URL</Label>
                                <Input
                                    id="p-url"
                                    value={form.base_url}
                                    onChange={(e) => setForm((f) => ({ ...f, base_url: e.target.value }))}
                                    placeholder="https://api.example.com/v1"
                                    required
                                />
                                <p className="mt-1 font-mono text-[10px] text-ink-ghost">
                                    Root API tanpa /chat/completions
                                </p>
                            </div>

                            <div>
                                <Label htmlFor="p-key">API Key</Label>
                                <Input
                                    id="p-key"
                                    type="password"
                                    value={form.api_key}
                                    onChange={(e) => setForm((f) => ({ ...f, api_key: e.target.value }))}
                                    placeholder="sk-..."
                                    required
                                />
                            </div>

                            {testResult && (
                                <div
                                    className={
                                        testResult.ok
                                            ? 'flex items-center gap-2 rounded border border-[rgba(16,185,129,0.3)] bg-[rgba(16,185,129,0.08)] px-3 py-2 text-sm text-ok'
                                            : 'rounded border border-[rgba(244,63,94,0.3)] bg-[rgba(244,63,94,0.08)] px-3 py-2 text-sm text-risk'
                                    }
                                >
                                    {testResult.ok && <CheckCircle2 size={14} />}
                                    {testResult.text}
                                </div>
                            )}

                            <div className="flex items-center justify-between">
                                <Button
                                    type="button"
                                    onClick={() => test()}
                                    disabled={busy === 'test-form'}
                                >
                                    {busy === 'test-form' ? (
                                        <Loader2 size={13} className="animate-spin" />
                                    ) : (
                                        <Plug size={13} />
                                    )}
                                    Test Connection
                                </Button>
                                <Button type="submit" variant="primary" disabled={busy === 'create'}>
                                    {busy === 'create' ? (
                                        <Loader2 size={13} className="animate-spin" />
                                    ) : (
                                        <Plus size={13} />
                                    )}
                                    Simpan Provider
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <Button variant="primary" onClick={() => setAdding(true)}>
                            <Plus size={14} />
                            Tambah Provider
                        </Button>
                    )}

                    {/* Divider */}
                    <div className="my-6 border-t border-line" />

                    {/* AI Combos */}
                    <CombosSection
                        providers={providers.map((p) => ({
                            id: p.id,
                            name: p.name,
                            model: p.model,
                            status: p.status,
                        }))}
                    />
                </div>
            </div>
        </AppShell>
    );
}
