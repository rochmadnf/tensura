import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { HugeiconsIcon } from '@hugeicons/react';
import { MailPlusIcon } from '@hugeicons/core-free-icons';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({
    items,
}: {
    items: {
        id: string;
        title: string;
        url: string;
        icon?: React.ReactNode;
    }[];
}) {
    const { page } = usePage().props;

    return (
        <SidebarGroup>
            <SidebarGroupContent className="flex flex-col gap-2">
                <SidebarMenu>
                    <SidebarMenuItem className="flex items-center gap-2">
                        <SidebarMenuButton
                            tooltip="Buat Instan"
                            className="h-10 min-w-8 bg-slate-900 text-primary-foreground duration-200 ease-linear hover:bg-slate-900/90 hover:text-primary-foreground active:bg-slate-900/90 active:text-primary-foreground"
                        >
                            <HugeiconsIcon icon={MailPlusIcon} strokeWidth={2} />
                            <span>Buat Instan</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                render={
                                    <Link href={item.url}>
                                        {item.icon}
                                        <span>{item.title}</span>
                                    </Link>
                                }
                                tooltip={item.title}
                                isActive={page.id === item.id}
                            />
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
