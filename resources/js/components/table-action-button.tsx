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
    loading?: boolean;
};

export function TableActionButton({ icon, label, tooltip, href, loading = false, ...props }: TableActionButtonProps) {
    return (
        <Tooltip>
            <TooltipTrigger
                disabled={props.disabled}
                render={
                    <Button
                        {...props}
                        size="icon-sm"
                        aria-label={label}
                        aria-busy={loading || undefined}
                        nativeButton={!href}
                        render={href ? <Link href={href} /> : undefined}
                    />
                }
            >
                <HugeiconsIcon
                    icon={icon}
                    strokeWidth={2}
                    className={loading ? 'animate-spin' : undefined}
                    aria-hidden="true"
                />
            </TooltipTrigger>
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    );
}
