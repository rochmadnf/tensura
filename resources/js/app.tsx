import { createInertiaApp } from '@inertiajs/react';
import AuthLayout from './pages/layouts/auth-layout';

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
    progress: {
        color: '#2b7fff',
    },
}).catch((error: unknown) => {
    console.error('Gagal menginisialisasi aplikasi:', error);
});
