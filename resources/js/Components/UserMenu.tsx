import { useState } from 'react';
import { router } from '@inertiajs/react';
import { ChevronDown, LogOut } from 'lucide-react';
import type { User } from '@/types';

export function UserMenu({ user }: { user: User }) {
    const [open, setOpen] = useState(false);
    const initials = user.name
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center gap-2 rounded p-1.5 text-left transition-colors hover:bg-surface-2"
            >
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent font-mono text-[11px] font-medium text-white">
                    {initials}
                </span>
                <span className="min-w-0 flex-1 truncate text-sm text-ink-2">{user.name}</span>
                <ChevronDown size={14} className="shrink-0 text-ink-3" />
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
                    <div className="absolute bottom-full left-0 z-20 mb-1 w-full overflow-hidden rounded border border-line-strong bg-surface-3 shadow-[0_4px_16px_rgba(0,0,0,0.45)]">
                        <div className="border-b border-line px-3 py-2">
                            <div className="truncate text-sm text-ink">{user.name}</div>
                            <div className="truncate font-mono text-[11px] text-ink-3">{user.email}</div>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post(route('logout'))}
                            className="flex w-full items-center gap-2 px-3 py-2 text-sm text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink"
                        >
                            <LogOut size={14} />
                            Log out
                        </button>
                    </div>
                </>
            )}
        </div>
    );
}
