import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { NotificationCenter } from '@/components/notification-center';

export function SiteHeader({ pageName }: { pageName: string }) {
    return (
        <header className="flex h-(--header-height) shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)">
            <div className="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
                <SidebarTrigger className="-ml-1" />
                <Separator orientation="vertical" className="mx-2 h-4 data-vertical:self-auto" />
                <h1 className="min-w-0 flex-1 truncate text-base font-medium">{pageName}</h1>
                <NotificationCenter />
            </div>
        </header>
    );
}
