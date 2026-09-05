import { cn } from '@/lib';
import { InputHTMLAttributes, forwardRef } from 'react';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
    ({ className, ...props }, ref) => (
        <input
            ref={ref}
            className={cn(
                'h-8 w-full rounded border border-line bg-canvas px-2.5 text-sm text-ink placeholder:text-ink-ghost outline-none transition-shadow focus:border-accent focus:shadow-[0_0_0_1px_#6366f1]',
                className,
            )}
            {...props}
        />
    ),
);
Input.displayName = 'Input';

export const Textarea = forwardRef<HTMLTextAreaElement, React.TextareaHTMLAttributes<HTMLTextAreaElement>>(
    ({ className, ...props }, ref) => (
        <textarea
            ref={ref}
            className={cn(
                'w-full rounded border border-line bg-canvas px-2.5 py-2 text-sm text-ink placeholder:text-ink-ghost outline-none transition-shadow focus:border-accent focus:shadow-[0_0_0_1px_#6366f1]',
                className,
            )}
            {...props}
        />
    ),
);
Textarea.displayName = 'Textarea';

export function Label({ children, className, htmlFor }: { children: React.ReactNode; className?: string; htmlFor?: string }) {
    return (
        <label htmlFor={htmlFor} className={cn('mb-1.5 block font-mono text-[11px] uppercase tracking-wider text-ink-3', className)}>
            {children}
        </label>
    );
}
