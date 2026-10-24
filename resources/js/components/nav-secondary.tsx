import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';

import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

export function NavSecondary({
    items,
    ...props
}: {
    items: {
        id: string;
        title: string;
        url: string;
        icon: React.ReactNode;
    }[];
} & React.ComponentPropsWithoutRef<typeof SidebarGroup>) {
    const { page } = usePage().props;
    return (
        <SidebarGroup {...props}>
            <SidebarGroupContent>
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                isActive={item.id === page.id}
                                render={
                                    <Link href={item.url} aria-current={item.id === page.id ? 'page' : undefined} />
                                }
                            >
                                {item.icon}
                                <span>{item.title}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
