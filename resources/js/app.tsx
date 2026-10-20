import { createInertiaApp } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';
import { TooltipProvider } from '@/components/ui/tooltip';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    id: 'tensura-root',
    title: (title) => (title ? `${title} :: ${appName}` : appName),
    strictMode: true,
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;

            default:
                return null;
        }
    },
    withApp(app) {
        return <TooltipProvider>{app}</TooltipProvider>;
    },
    progress: {
        color: '#2b7fff',
    },
}).catch((error: unknown) => {
    console.error('Gagal menginisialisasi aplikasi:', error);
});
