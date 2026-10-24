import type { ComponentProps } from 'react';
import { Link } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

type TableActionButtonProps = Omit<ComponentProps<typeof Button>, 'children' | 'size' | 'render'> & {
    icon: ComponentProps<typeof HugeiconsIcon>['icon'];
    label: string;
    tooltip: string;
    href?: ComponentProps<typeof Link>['href'];
};

export function TableActionButton({ icon, label, tooltip, href, ...props }: TableActionButtonProps) {
    return (
        <Tooltip>
            <Button
                {...props}
                size="icon-sm"
                aria-label={label}
                nativeButton={!href}
                render={<TooltipTrigger render={href ? <Link href={href} /> : undefined} />}
            >
                <HugeiconsIcon icon={icon} strokeWidth={2} aria-hidden="true" />
            </Button>
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    );
}
