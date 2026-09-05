import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { renderToString } from 'react-dom/server';

const appName = import.meta.env.VITE_APP_NAME ?? 'PRDForge';

export default function render(page: never) {
    return createInertiaApp({
        page,
        title: (title) => `${title} — ${appName}`,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.tsx`,
                import.meta.glob('./Pages/**/*.tsx'),
            ) as never,
        setup: ({ App, props }) => renderToString(<App {...props} />),
    });
}
