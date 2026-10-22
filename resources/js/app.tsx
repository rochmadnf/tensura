import { createInertiaApp } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { GooeyToaster } from '@/components/ui/goey-toaster';
import { PageLoadingOverlay } from '@/components/page-loading-overlay';

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
                return AppLayout;
        }
    },
    withApp(app) {
        return (
            <TooltipProvider delay={0}>
                {app}
                <GooeyToaster position="top-center" showTimestamp={false} />
                <PageLoadingOverlay />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#2b7fff',
    },
}).catch((error: unknown) => {
    console.error('Gagal menginisialisasi aplikasi:', error);
});
