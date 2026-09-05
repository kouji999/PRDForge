import { cn } from '@/lib';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FileText, LayoutDashboard, Settings2, Sparkles } from 'lucide-react';
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
    const nav: NavItem[] = [
        { label: 'Dashboard', href: route('dashboard'), icon: <LayoutDashboard size={16} /> },
        { label: 'Projects', href: route('projects.index'), icon: <FileText size={16} />, badge: activeCount },
        { label: 'Providers', href: route('settings.providers'), icon: <Settings2 size={16} /> },
    ];

    return (
        <div className="flex min-h-dvh bg-canvas">
            {/* Left rail */}
            <aside className="hidden w-64 shrink-0 flex-col border-r border-line bg-surface md:flex">
                <div className="flex h-14 items-center gap-2.5 border-b border-line px-4">
                    <div className="flex h-7 w-7 items-center justify-center rounded bg-accent">
                        <Sparkles size={14} className="text-white" />
                    </div>
                    <div>
                        <div className="text-sm font-semibold leading-tight text-ink">PRDForge</div>
                        <div className="font-mono text-[10px] leading-tight tracking-wider text-ink-ghost">
                            AI PRD STUDIO
                        </div>
                    </div>
                </div>

                <nav className="flex-1 space-y-0.5 overflow-y-auto p-3">
                    {nav.map((item) => (
                        <Link
                            key={item.label}
                            href={item.href}
                            className={cn(
                                'flex h-9 items-center gap-2.5 rounded px-2.5 text-sm transition-colors',
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

            {/* Main */}
            <main className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-14 items-center justify-between border-b border-line bg-surface px-4 md:px-6">
                    <MobileNav activeCount={activeCount} current={current} />
                    <div className="hidden text-sm text-ink-3 md:block">
                        AI Product Discovery &amp; PRD Studio
                    </div>
                    <div className="md:hidden">
                        <UserMenu user={user} />
                    </div>
                </header>
                <div className="flex min-h-0 flex-1 flex-col">{children}</div>
            </main>
        </div>
    );
}

function MobileNav({ activeCount, current }: { activeCount?: number; current?: string }) {
    return (
        <div className="flex items-center gap-1 md:hidden">
            <Link href={route('dashboard')} className="flex items-center gap-1.5 text-sm font-semibold text-ink">
                <Sparkles size={14} className="text-accent" />
                PRDForge
            </Link>
            <Link
                href={route('projects.index')}
                className={cn(
                    'ml-3 rounded px-2 py-1 text-xs',
                    current === 'Projects' ? 'bg-surface-2 text-ink' : 'text-ink-3',
                )}
            >
                Projects{activeCount ? ` (${activeCount})` : ''}
            </Link>
        </div>
    );
}
