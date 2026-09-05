import { useCallback, useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import { AppShell } from '@/Components/AppShell';
import { Button } from '@/Components/Button';
import { StatusPill } from '@/Components/StatusPill';
import { ContextPanel } from './ContextPanel';
import { RequirementsPanel } from './RequirementsPanel';
import { cn, timeAgo } from '@/lib';
import {
    ArrowUp,
    FileText,
    Loader2,
    RefreshCw,
    Sparkles,
    Square,
} from 'lucide-react';
import type { ReadinessCriterion } from '@/types';

interface ChatMessage {
    id: number | 'optimistic';
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
}

interface Props {
    auth: { user: import('@/types').User };
    sidebar: import('@/Pages/Dashboard').SidebarShared;
    project: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        status: string;
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
        requirements: Array<{
            id: number;
            type: string;
            title: string;
            content: string;
            status: string;
            source: string;
            priority: string;
        }>;
        prd: { id: number; sections_count: number } | null;
        created_at: string;
        updated_at: string;
    };
    readiness: { ready: boolean; score: number; criteria: ReadinessCriterion[]; missing: string[] };
    conversation: { id: number; title: string | null; messages: Array<ChatMessage> } | null;
    flash?: { success?: string; error?: string };
}

export default function ProjectShow({ auth, sidebar, project, readiness, conversation, flash }: Props) {
    const [tab, setTab] = useState<'context' | 'requirements'>('context');
    const [messages, setMessages] = useState<ChatMessage[]>(conversation?.messages ?? []);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const [streaming, setStreaming] = useState(false);
    const [streamContent, setStreamContent] = useState('');
    const [chatError, setChatError] = useState<string | null>(null);
    const [extracting, setExtracting] = useState(false);
    const [extractResult, setExtractResult] = useState<string | null>(null);
    const [generating, setGenerating] = useState(false);
    const [genError, setGenError] = useState<string | null>(null);
    const [genProgress, setGenProgress] = useState<{ chunk: number; total: number } | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => {
        if (flash?.error) setChatError(flash.error);
    }, [flash]);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight });
    }, [messages, streamContent]);

    const send = useCallback(
        async (text: string) => {
            const trimmed = text.trim();
            if (!trimmed || sending || streaming || !conversation) return;

            setChatError(null);
            setSending(true);
            setInput('');

            const optimistic: ChatMessage = {
                id: 'optimistic',
                role: 'user',
                content: trimmed,
                created_at: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, optimistic]);

            try {
                const controller = new AbortController();
                abortRef.current = controller;

                const res = await fetch(
                    route('conversations.stream', { project: project.id, conversation: conversation.id }),
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN':
                                document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                        },
                        body: JSON.stringify({ message: trimmed }),
                        signal: controller.signal,
                    },
                );

                if (!res.ok && res.status !== 200) {
                    const body = await res.json().catch(() => null);
                    throw new Error(body?.error ?? 'Gagal mengirim pesan.');
                }

                setStreaming(true);
                setStreamContent('');

                const reader = res.body?.getReader();
                const decoder = new TextDecoder();

                if (!reader) throw new Error('Streaming tidak didukung browser.');

                let buffer = '';
                let streamText = '';

                for (;;) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() ?? '';

                    for (const line of lines) {
                        if (line.startsWith('event: error')) {
                            const dataLine = lines[lines.indexOf(line) + 1];
                            if (dataLine?.startsWith('data:')) {
                                const parsed = JSON.parse(dataLine.slice(5));
                                throw new Error(parsed.error ?? 'AI error.');
                            }
                        }
                        if (!line.startsWith('data:')) continue;

                        try {
                            const payload = JSON.parse(line.slice(5));

                            if (payload.delta) {
                                streamText += payload.delta;
                                setStreamContent(streamText);
                            }
                            if (payload.status === 'complete') {
                                setMessages((prev) => [
                                    ...prev,
                                    {
                                        id: Date.now(),
                                        role: 'assistant',
                                        content: streamText,
                                        created_at: new Date().toISOString(),
                                    },
                                ]);
                            }
                        } catch {
                            // partial json line — skip
                        }
                    }
                }

                setStreamContent('');
            } catch (err) {
                const isAbort = err instanceof DOMException && err.name === 'AbortError';
                if (!isAbort) {
                    setChatError(err instanceof Error ? err.message : 'Gagal mengirim pesan.');
                }
                setStreamContent('');
            } finally {
                setSending(false);
                setStreaming(false);
                abortRef.current = null;
            }
        },
        [conversation, project.id, sending, streaming],
    );

    const cancelStream = () => {
        abortRef.current?.abort();
        setStreaming(false);
        setStreamContent('');
    };

    const extract = async () => {
        if (!conversation || extracting) return;

        setExtracting(true);
        setExtractResult(null);

        try {
            const res = await fetch(
                route('conversations.extract', { project: project.id, conversation: conversation.id }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    },
                },
            );

            const body = await res.json();

            if (!res.ok) throw new Error(body.error ?? 'Ekstraksi gagal.');

            setExtractResult(
                `${body.proposed.length} requirement baru diusulkan, konteks: ${body.context_changes?.length ?? 0} field diperbarui. Review di tab Requirements.`,
            );
            window.location.reload();
        } catch (err) {
            setExtractResult(err instanceof Error ? err.message : 'Ekstraksi gagal.');
        } finally {
            setExtracting(false);
        }
    };

    const generatePrd = async () => {
        if (generating) return;
        setGenerating(true);
        setGenError(null);
        setGenProgress(null);

        try {
            const res = await fetch(route('projects.prd.generate', { project: project.id }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            });

            const body = await res.json();

            if (!res.ok) throw new Error(body.error ?? 'Generate PRD gagal.');

            // Poll background job progress
            pollGeneration();
        } catch (err) {
            setGenError(err instanceof Error ? err.message : 'Generate PRD gagal.');
            setGenerating(false);
        }
    };

    const pollGeneration = () => {
        const tick = async () => {
            try {
                const res = await fetch(route('projects.prd.status', { project: project.id }));
                const body = await res.json();

                if (body.progress?.error) {
                    setGenError(body.progress.error);
                    setGenerating(false);
                    setGenProgress(null);
                    return;
                }

                if (body.prd_ready) {
                    window.location.href = route('projects.prd.show', { project: project.id });
                    return;
                }

                if (body.status === 'generating') {
                    setGenProgress(body.progress ?? { chunk: 0, total: 4 });
                    setTimeout(tick, 4000);
                } else {
                    setGenerating(false);
                    setGenProgress(null);
                }
            } catch {
                setTimeout(tick, 6000);
            }
        };

        tick();
    };

    const statusTone = (sidebar.statuses[project.status]?.tone ?? 'muted') as 'ok';
    const statusLabel = sidebar.statuses[project.status]?.label ?? project.status;
    const hasPrd = project.prd !== null;

    return (
        <AppShell user={auth.user} activeCount={sidebar.activeProjects} current="Projects">
            <Head title={project.name} />

            <div className="flex min-h-0 flex-1">
                {/* Chat column */}
                <div className="flex min-w-0 flex-1 flex-col">
                    {/* Project header */}
                    <div className="flex items-center justify-between gap-3 border-b border-line bg-surface px-4 py-3 md:px-6">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <h1 className="truncate font-semibold text-ink">{project.name}</h1>
                                <StatusPill label={statusLabel} tone={statusTone} />
                            </div>
                            <div className="mt-0.5 font-mono text-[11px] text-ink-3">
                                {readiness.score}% readiness · {timeAgo(project.updated_at)}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button size="sm" onClick={extract} disabled={extracting || !conversation}>
                                {extracting ? <Loader2 size={13} className="animate-spin" /> : <RefreshCw size={13} />}
                                Extract
                            </Button>
                            {hasPrd ? (
                                <a href={route('projects.prd.show', { project: project.id })}>
                                    <Button size="sm" variant="primary">
                                        <FileText size={13} />
                                        Buka PRD
                                    </Button>
                                </a>
                            ) : (
                                <Button
                                    size="sm"
                                    variant="primary"
                                    onClick={generatePrd}
                                    disabled={generating || !readiness.ready}
                                    title={readiness.ready ? 'Generate PRD' : 'Selesaikan readiness dulu'}
                                >
                                    {generating ? (
                                        <Loader2 size={13} className="animate-spin" />
                                    ) : (
                                        <Sparkles size={13} />
                                    )}
                                    {generating ? 'Generating…' : 'Generate PRD'}
                                </Button>
                            )}
                        </div>
                    </div>

                    {generating && genProgress && (
                        <div className="border-b border-[rgba(99,102,241,0.3)] bg-[rgba(99,102,241,0.08)] px-4 py-2 md:px-6">
                            <div className="flex items-center justify-between text-xs text-ink-2">
                                <span className="flex items-center gap-1.5">
                                    <Loader2 size={13} className="animate-spin text-accent" />
                                    Membangkitkan PRD — bagian {genProgress.chunk}/{genProgress.total}
                                </span>
                                <span className="font-mono text-accent">
                                    {Math.round((genProgress.chunk / genProgress.total) * 100)}%
                                </span>
                            </div>
                            <div className="mt-1.5 h-1 overflow-hidden rounded-full bg-surface-2">
                                <div
                                    className="h-full rounded-full bg-accent transition-all duration-500"
                                    style={{ width: `${(genProgress.chunk / genProgress.total) * 100}%` }}
                                />
                            </div>
                        </div>
                    )}
                    {genError && (
                        <div className="border-b border-[rgba(244,63,94,0.3)] bg-[rgba(244,63,94,0.08)] px-4 py-2 text-sm text-risk md:px-6">
                            {genError}
                        </div>
                    )}
                    {extractResult && (
                        <div className="border-b border-[rgba(16,185,129,0.3)] bg-[rgba(16,185,129,0.08)] px-4 py-2 text-sm text-ok md:px-6">
                            {extractResult}
                        </div>
                    )}

                    {/* Readiness bar */}
                    <div className="flex items-center gap-2 border-b border-line bg-surface px-4 py-2 md:px-6">
                        <span className="font-mono text-[10px] uppercase tracking-wider text-ink-3">
                            Readiness
                        </span>
                        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-2">
                            <div
                                className={cn('h-full rounded-full transition-all', readiness.ready ? 'bg-ok' : 'bg-warn')}
                                style={{ width: `${readiness.score}%` }}
                            />
                        </div>
                        <span className={cn('font-mono text-[11px]', readiness.ready ? 'text-ok' : 'text-warn')}>
                            {readiness.score}%
                        </span>
                    </div>

                    {/* Messages */}
                    <div ref={scrollRef} className="flex-1 overflow-y-auto px-4 py-4 md:px-6">
                        <div className="mx-auto max-w-3xl space-y-4">
                            {messages.length === 0 && !streaming && (
                                <div className="rounded-lg border border-dashed border-line-strong bg-surface/50 p-8 text-center">
                                    <Sparkles size={20} className="mx-auto mb-3 text-accent" />
                                    <p className="font-medium text-ink">Mulai discovery</p>
                                    <p className="mt-1 text-sm text-ink-3">
                                        Ceritakan idemu — AI akan menggali masalah, target user, dan fitur inti.
                                    </p>
                                </div>
                            )}

                            {messages.map((m) => (
                                <MessageBubble key={m.id} message={m} />
                            ))}

                            {streaming && (
                                <MessageBubble
                                    message={{
                                        id: -1,
                                        role: 'assistant',
                                        content: streamContent || '…',
                                        created_at: new Date().toISOString(),
                                    }}
                                    streaming
                                />
                            )}
                        </div>
                    </div>

                    {/* Composer */}
                    <div className="border-t border-line bg-surface px-4 py-3 md:px-6">
                        {chatError && (
                            <p className="mb-2 flex items-center justify-between rounded border border-[rgba(244,63,94,0.3)] bg-[rgba(244,63,94,0.08)] px-3 py-1.5 text-xs text-risk">
                                {chatError}
                                <button type="button" onClick={() => setChatError(null)} className="underline">
                                    tutup
                                </button>
                            </p>
                        )}
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                send(input);
                            }}
                            className="mx-auto flex max-w-3xl items-end gap-2"
                        >
                            <textarea
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey) {
                                        e.preventDefault();
                                        send(input);
                                    }
                                }}
                                rows={Math.min(5, Math.max(1, input.split('\n').length))}
                                placeholder="Ceritakan idemu… (Enter kirim, Shift+Enter baris baru)"
                                disabled={sending || streaming}
                                className="max-h-32 min-h-9 flex-1 resize-none rounded border border-line bg-canvas px-3 py-2 text-sm text-ink placeholder:text-ink-ghost outline-none focus:border-accent focus:shadow-[0_0_0_1px_#6366f1] disabled:opacity-60"
                            />
                            {streaming ? (
                                <Button type="button" variant="danger" onClick={cancelStream} className="h-9 w-9 p-0">
                                    <Square size={14} />
                                </Button>
                            ) : (
                                <Button
                                    type="submit"
                                    variant="primary"
                                    className="h-9 w-9 p-0"
                                    disabled={sending || !input.trim() || !conversation}
                                >
                                    {sending ? (
                                        <Loader2 size={14} className="animate-spin" />
                                    ) : (
                                        <ArrowUp size={16} />
                                    )}
                                </Button>
                            )}
                        </form>
                    </div>
                </div>

                {/* Right inspector */}
                <aside className="hidden w-80 shrink-0 flex-col border-l border-line bg-surface lg:flex">
                    <div className="flex border-b border-line">
                        <button
                            type="button"
                            onClick={() => setTab('context')}
                            className={cn(
                                'flex-1 px-3 py-2.5 text-sm transition-colors',
                                tab === 'context' ? 'border-b-2 border-accent text-ink' : 'text-ink-3 hover:text-ink',
                            )}
                        >
                            Context
                        </button>
                        <button
                            type="button"
                            onClick={() => setTab('requirements')}
                            className={cn(
                                'flex-1 px-3 py-2.5 text-sm transition-colors',
                                tab === 'requirements'
                                    ? 'border-b-2 border-accent text-ink'
                                    : 'text-ink-3 hover:text-ink',
                            )}
                        >
                            Requirements
                        </button>
                    </div>

                    <div className="flex-1 overflow-y-auto">
                        {tab === 'context' ? (
                            <ContextPanel project={project} readiness={readiness} />
                        ) : (
                            <RequirementsPanel requirements={project.requirements} projectId={project.id} />
                        )}
                    </div>
                </aside>
            </div>
        </AppShell>
    );
}

function MessageBubble({ message, streaming }: { message: ChatMessage; streaming?: boolean }) {
    const isUser = message.role === 'user';

    return (
        <div className={cn('flex', isUser ? 'justify-end' : 'justify-start')}>
            <div
                className={cn(
                    'max-w-[85%] rounded-lg border px-3.5 py-2.5 text-sm leading-relaxed',
                    isUser
                        ? 'border-accent/40 bg-accent/10 text-ink'
                        : 'border-line bg-surface-2 text-ink-2',
                )}
            >
                {!isUser && (
                    <div className="mb-1.5 flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-wider text-ink-3">
                        <Sparkles size={11} className="text-accent" />
                        PRDForge AI
                        {streaming && <span className="animate-pulse">streaming…</span>}
                    </div>
                )}
                <div className="whitespace-pre-wrap">{message.content}</div>
            </div>
        </div>
    );
}
