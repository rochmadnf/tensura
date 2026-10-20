import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            app: {
                name: string;
                version: string;
            };
            auth: Auth;
            [key: string]: unknown;
        };
    }
}
