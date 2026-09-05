import { useState } from 'react';
import { cn } from '@/lib';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FileText, LayoutDashboard, Menu, Settings2, Sparkles, X } from 'lucide-react';
import type { User } from '@/types';
import { UserMenu } from './UserMenu';

interface NavItem {
    label: string;
    href: string;
    icon: ReactNode;
    badge?: number;
}

export function AppShell({
    user,
    activeCount,
    children,
    current,
}: {
    user: User;
    activeCount?: number;
    children: ReactNode;
    current?: string;
}) {
    const [navOpen, setNavOpen] = useState(false);

    const nav: NavItem[] = [
        { label: 'Dashboard', href: route('dashboard'), icon: <LayoutDashboard size={16} /> },
        { label: 'Projects', href: route('projects.index'), icon: <FileText size={16} />, badge: activeCount },
        { label: 'Providers', href: route('settings.providers'), icon: <Settings2 size={16} /> },
    ];

    return (
        <div className="flex h-dvh overflow-hidden bg-canvas">
            {/* Left rail — overlay drawer < md, docked ≥ md */}
            {navOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 md:hidden"
                    onClick={() => setNavOpen(false)}
                />
            )}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 w-64 shrink-0 flex-col border-r border-line bg-surface transition-transform duration-200 md:static md:z-auto md:flex md:translate-x-0',
                    navOpen ? 'flex translate-x-0' : 'hidden -translate-x-full',
                )}
            >
                <div className="flex h-14 shrink-0 items-center justify-between gap-2.5 border-b border-line px-4">
                    <Link href={route('dashboard')} className="flex items-center gap-2.5" onClick={() => setNavOpen(false)}>
                        <div className="flex h-7 w-7 items-center justify-center rounded bg-accent">
                            <Sparkles size={14} className="text-white" />
                        </div>
                        <div>
                            <div className="text-sm font-semibold leading-tight text-ink">PRDForge</div>
                            <div className="font-mono text-[10px] leading-tight tracking-wider text-ink-ghost">
                                AI PRD STUDIO
                            </div>
                        </div>
                    </Link>
                    <button
                        type="button"
                        onClick={() => setNavOpen(false)}
                        className="rounded p-1.5 text-ink-3 transition-colors hover:bg-surface-2 hover:text-ink md:hidden"
                    >
                        <X size={16} />
                    </button>
                </div>

                <nav className="flex-1 space-y-0.5 overflow-y-auto p-3">
                    {nav.map((item) => (
                        <Link
                            key={item.label}
                            href={item.href}
                            onClick={() => setNavOpen(false)}
                            className={cn(
                                'flex h-9 shrink-0 items-center gap-2.5 rounded px-2.5 text-sm transition-colors',
                                current === item.label
                                    ? 'bg-surface-2 text-ink'
                                    : 'text-ink-3 hover:bg-surface-2 hover:text-ink',
                            )}
                        >
                            <span className="shrink-0">{item.icon}</span>
                            <span className="flex-1 truncate">{item.label}</span>
                            {item.badge !== undefined && item.badge > 0 && (
                                <span className="rounded bg-surface-3 px-1.5 py-0.5 font-mono text-[10px] text-ink-3">
                                    {item.badge}
                                </span>
                            )}
                        </Link>
                    ))}
                </nav>

                <div className="border-t border-line p-3">
                    <UserMenu user={user} />
                </div>
            </aside>

            {/* Main — locked to viewport; children own their internal scroll */}
            <main className="flex h-dvh min-w-0 flex-1 flex-col overflow-hidden">
                <header className="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-line bg-surface px-3 md:px-6">
                    <div className="flex min-w-0 items-center gap-2 md:hidden">
                        <button
                            type="button"
                            onClick={() => setNavOpen(true)}
                            className="rounded p-1.5 text-ink-3 transition-colors hover:bg-surface-2 hover:text-ink"
                            title="Menu"
                        >
                            <Menu size={18} />
                        </button>
                        <Link href={route('dashboard')} className="flex items-center gap-1.5 text-sm font-semibold text-ink">
                            <Sparkles size={14} className="text-accent" />
                            PRDForge
                        </Link>
                    </div>
                    <div className="hidden text-sm text-ink-3 md:block">
                        AI Product Discovery &amp; PRD Studio
                    </div>
                    <div className="ml-auto flex items-center gap-2">
                        <div className="w-40 md:hidden">
                            <UserMenu user={user} />
                        </div>
                    </div>
                </header>
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden">{children}</div>
            </main>
        </div>
    );
}
