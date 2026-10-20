import type { Auth } from '@/types/auth';
import type { Page } from '@/types/page';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            app: {
                name: string;
                version: string;
            };
            page: Page;
            auth: Auth;
            [key: string]: unknown;
        };
    }
}
