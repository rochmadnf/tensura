import { AppSidebar } from '@/components/app-sidebar';
import { SiteHeader } from '@/components/site-header';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import { NotificationProvider } from '@/components/letter-operation-notifications';

export default function AppLayout({ children, pageName }: { children: ReactNode; pageName: string }) {
    const { auth } = usePage<{ auth: { user: { id: string | number } | null } }>().props;
    const userId = auth.user?.id ?? null;
    return (
        <NotificationProvider key={userId ?? 'guest'} userId={userId}>
            <SidebarProvider
                style={
                    {
                        '--sidebar-width': 'calc(var(--spacing) * 72)',
                        '--header-height': 'calc(var(--spacing) * 12)',
                    } as React.CSSProperties
                }
            >
                <AppSidebar variant="inset" />
                <SidebarInset>
                    <SiteHeader pageName={pageName} />
                    <div className="flex flex-1 flex-col">
                        <div className="@container/main flex flex-1 flex-col gap-2">
                            <div className="flex flex-col gap-4 px-4 py-4 md:gap-6 md:px-6 md:py-6">{children}</div>
                        </div>
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </NotificationProvider>
    );
}
