import { memo } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib';

/**
 * Design-system markdown renderer.
 * Per DESIGN.md: body-md/body-lg sizing, JetBrains Mono for code,
 * hairline borders, no decorative colors.
 */
export const Markdown = memo(function Markdown({
    content,
    className,
    compact = false,
}: {
    content: string;
    className?: string;
    compact?: boolean;
}) {
    return (
        <div className={cn('md', compact ? 'text-sm' : 'text-[0.9375rem]', className)}>
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                    h1: ({ children }) => (
                        <h1 className="mt-5 mb-2.5 text-xl font-semibold tracking-tight text-ink first:mt-0">
                            {children}
                        </h1>
                    ),
                    h2: ({ children }) => (
                        <h2 className="mt-4 mb-2 text-base font-semibold tracking-tight text-ink first:mt-0">
                            {children}
                        </h2>
                    ),
                    h3: ({ children }) => (
                        <h3 className="mt-3 mb-1.5 text-sm font-semibold text-ink first:mt-0">
                            {children}
                        </h3>
                    ),
                    p: ({ children }) => (
                        <p className="mb-2.5 leading-relaxed text-ink-2 last:mb-0">{children}</p>
                    ),
                    ul: ({ children }) => (
                        <ul className="mb-2.5 list-disc space-y-1 pl-5 text-ink-2 [&>li]:leading-relaxed">
                            {children}
                        </ul>
                    ),
                    ol: ({ children }) => (
                        <ol className="mb-2.5 list-decimal space-y-1 pl-5 text-ink-2 [&>li]:leading-relaxed">
                            {children}
                        </ol>
                    ),
                    li: ({ children }) => <li className="marker:text-ink-ghost">{children}</li>,
                    strong: ({ children }) => (
                        <strong className="font-semibold text-ink">{children}</strong>
                    ),
                    a: ({ href, children }) => (
                        <a href={href} className="text-accent hover:underline" target="_blank" rel="noreferrer">
                            {children}
                        </a>
                    ),
                    blockquote: ({ children }) => (
                        <blockquote className="mb-2.5 border-l-2 border-accent/60 pl-3 text-ink-3 italic">
                            {children}
                        </blockquote>
                    ),
                    code: ({ className: cls, children }) => {
                        const isBlock = /language-/.test(cls ?? '');

                        if (isBlock) {
                            return (
                                <code className={cn(cls, 'block font-mono text-xs leading-relaxed')}>
                                    {children}
                                </code>
                            );
                        }

                        return (
                            <code className="rounded border border-line bg-surface px-1 py-0.5 font-mono text-[0.8125rem] text-ink-2">
                                {children}
                            </code>
                        );
                    },
                    pre: ({ children }) => (
                        <pre className="mb-2.5 overflow-x-auto rounded border border-line bg-surface p-3 text-ink-2">
                            {children}
                        </pre>
                    ),
                    table: ({ children }) => (
                        <div className="mb-2.5 overflow-x-auto rounded border border-line">
                            <table className="w-full border-collapse text-sm">{children}</table>
                        </div>
                    ),
                    thead: ({ children }) => (
                        <thead className="bg-surface font-mono text-[10px] uppercase tracking-wider text-ink-3">
                            {children}
                        </thead>
                    ),
                    th: ({ children }) => (
                        <th className="border-b border-line px-2.5 py-2 text-left font-medium">{children}</th>
                    ),
                    td: ({ children }) => (
                        <td className="border-b border-line/60 px-2.5 py-2 align-top text-ink-2">{children}</td>
                    ),
                    hr: () => <hr className="my-4 border-line" />,
                }}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
});
