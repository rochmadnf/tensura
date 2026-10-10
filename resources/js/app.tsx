import { createInertiaApp } from '@inertiajs/react';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    id: 'tensura-root',
    title: (title) => (title ? `${title} :: ${appName}` : appName),
    strictMode: true,
    progress: {
        color: '#2b7fff',
    },
}).catch((error: unknown) => {
    console.error('Gagal menginisialisasi aplikasi:', error);
});
