import { cn } from '@/lib';
import { ButtonHTMLAttributes, forwardRef } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
}

const VARIANTS: Record<Variant, string> = {
    primary:
        'bg-accent text-white hover:bg-accent-hover disabled:hover:bg-accent',
    secondary:
        'bg-surface-2 text-ink-2 border border-line hover:bg-surface-3 hover:border-line-strong',
    ghost: 'text-ink-3 hover:text-ink hover:bg-surface-2',
    danger:
        'bg-[rgba(244,63,94,0.12)] text-risk border border-[rgba(244,63,94,0.3)] hover:bg-[rgba(244,63,94,0.2)]',
};

const SIZES: Record<Size, string> = {
    sm: 'h-7 px-2.5 text-xs',
    md: 'h-8 px-3 text-sm',
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ variant = 'secondary', size = 'md', className, disabled, ...props }, ref) => (
        <button
            ref={ref}
            disabled={disabled}
            className={cn(
                'inline-flex items-center justify-center gap-1.5 rounded font-medium whitespace-nowrap transition-colors duration-150 outline-none focus-visible:ring-1 focus-visible:ring-accent disabled:cursor-not-allowed disabled:opacity-50',
                VARIANTS[variant],
                SIZES[size],
                className,
            )}
            {...props}
        />
    ),
);
Button.displayName = 'Button';
