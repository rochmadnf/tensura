import ref from '@/routes/ref';
import { index as letterIndex } from '@/routes/ltr';
import * as React from 'react';

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    DashboardSquare01Icon,
    MailboxIcon,
    MailsIcon,
    InputNumericIcon,
    AiMail01Icon,
    FolderDetailsReferenceIcon,
    BookUserIcon,
    UserDollarIcon,
    Archive01Icon,
} from '@hugeicons/core-free-icons';
import { usePage } from '@inertiajs/react';
import { NavMain } from './nav-main';
import { NavUser } from './nav-user';
import { dashboard } from '@/routes';
import { NavGroups } from './nav-groups';
import ln from '@/routes/ln';
import lt from '@/routes/lt';
import jt from '@/routes/jt';
import empl from '@/routes/empl';
import { index as archiveIndex } from '@/routes/archives';
import { NavSecondary } from './nav-secondary';

const navigations = {
    navMain: [
        {
            id: 'a3062fa9-4422-44a7-834c-1e5471ed1f07',
            title: 'Dashboard',
            url: dashboard().url,
            icon: <HugeiconsIcon icon={DashboardSquare01Icon} strokeWidth={2} />,
        },
        {
            id: 'a306324d-b2b7-401c-a2be-7b8ed37fdf62',
            title: 'Surat',
            url: letterIndex().url,
            icon: <HugeiconsIcon icon={MailsIcon} strokeWidth={2} />,
        },
    ],
    navSecondary: [
        {
            id: 'archive',
            title: 'Arsip',
            url: archiveIndex().url,
            icon: <HugeiconsIcon icon={Archive01Icon} strokeWidth={2} />,
        },
    ],
    masterData: {
        name: 'Data Master',
        items: [
            {
                id: 'a3063dab-44a6-4de2-9c93-8fa84742e3da',
                name: 'Penomoran Surat',
                url: ln.index().url,
                icon: <HugeiconsIcon icon={InputNumericIcon} strokeWidth={2} />,
            },
            {
                id: 'a3063dc3-7414-4385-bf3f-0cf0742e3980',
                name: 'Templat Surat',
                url: lt.index().url,
                icon: <HugeiconsIcon icon={AiMail01Icon} strokeWidth={2} />,
            },
            {
                id: 'a3063dcc-2750-45e0-8ea7-c859c50bd3fb',
                name: 'Daftar Referensi',
                url: ref.index().url,
                icon: <HugeiconsIcon icon={FolderDetailsReferenceIcon} strokeWidth={2} />,
            },
        ],
    },
    employee: {
        name: 'Pegawai',
        items: [
            {
                id: 'a30c21b9-15ef-4dc0-97ef-524ead13be55',
                name: 'Daftar Pegawai',
                url: empl.index().url,
                icon: <HugeiconsIcon icon={BookUserIcon} strokeWidth={2} />,
            },
            {
                id: 'a30c2273-d730-4e1a-af2c-479e149cf54b',
                name: 'Jabatan',
                url: jt.index().url,
                icon: <HugeiconsIcon icon={UserDollarIcon} strokeWidth={2} />,
            },
        ],
    },
};
export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { app, auth } = usePage().props;

    return (
        <Sidebar collapsible="offcanvas" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton className="data-[slot=sidebar-menu-button]:p-1.5!" render={<a href="#" />}>
                            <HugeiconsIcon icon={MailboxIcon} strokeWidth={2} className="size-5!" />
                            <span className="text-base font-semibold">{app.name}</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={navigations.navMain} />
                <NavGroups items={navigations.masterData.items} groupName={navigations.masterData.name} />
                <NavGroups items={navigations.employee.items} groupName={navigations.employee.name} />
                <NavSecondary items={navigations.navSecondary} className="mt-auto" />
            </SidebarContent>
            <SidebarFooter>
                <NavUser user={auth.user} />
            </SidebarFooter>
        </Sidebar>
    );
}
