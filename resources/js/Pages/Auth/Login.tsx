import { Head, useForm } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Input, Label } from '@/Components/Input';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <div className="flex min-h-dvh items-center justify-center bg-canvas p-6">
            <Head title="Log in" />

            <div className="w-full max-w-sm">
                <div className="mb-8 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-accent">
                        <Sparkles size={18} className="text-white" />
                    </div>
                    <div>
                        <div className="text-lg font-semibold text-ink">PRDForge</div>
                        <div className="font-mono text-[11px] tracking-wider text-ink-3">AI PRD STUDIO</div>
                    </div>
                </div>

                <div className="rounded-lg border border-line bg-surface p-6">
                    <h1 className="text-base font-semibold text-ink">Welcome back</h1>
                    <p className="mt-1 mb-5 text-sm text-ink-3">
                        Log in untuk lanjut menempa PRD.
                    </p>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="nama@email.com"
                                autoComplete="email"
                                required
                            />
                            {errors.email && <p className="mt-1.5 text-xs text-risk">{errors.email}</p>}
                        </div>

                        <div>
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                autoComplete="current-password"
                                required
                            />
                            {errors.password && <p className="mt-1.5 text-xs text-risk">{errors.password}</p>}
                        </div>

                        <Button type="submit" variant="primary" className="w-full" disabled={processing}>
                            {processing ? 'Logging in…' : 'Log in'}
                        </Button>
                    </form>
                </div>

                <p className="mt-4 text-center text-sm text-ink-3">
                    Belum punya akun?{' '}
                    <a href={route('register')} className="text-accent hover:underline">
                        Register
                    </a>
                </p>
            </div>
        </div>
    );
}
