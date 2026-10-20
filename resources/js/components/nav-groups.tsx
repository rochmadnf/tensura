import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/react';

export function NavGroups({
    items,
    groupName,
}: {
    items: {
        id: string;
        name: string;
        url: string;
        icon: React.ReactNode;
    }[];
    groupName: string;
}) {
    const { page } = usePage().props;
    return (
        <SidebarGroup className="group-data-[collapsible=icon]:hidden">
            <SidebarGroupLabel>{groupName}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.name}>
                        <SidebarMenuButton
                            isActive={item.id === page.id}
                            render={
                                <Link href={item.url}>
                                    {item.icon}
                                    <span>{item.name}</span>
                                </Link>
                            }
                        />
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
