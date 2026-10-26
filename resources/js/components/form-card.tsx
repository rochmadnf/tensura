import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { XIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export function FormCard({
    title,
    step,
    sectioned = false,
    description,
    cancelUrl,
    disabled = false,
    children,
    action,
}: {
    title: string;
    step?: number;
    sectioned?: boolean;
    description: ReactNode;
    cancelUrl?: string;
    action?: ReactNode;
    disabled?: boolean;
    children: ReactNode;
}) {
    return (
        <Card className={sectioned || step ? 'w-full pt-0' : 'w-full'}>
            <CardHeader className={sectioned || step ? 'border-b bg-muted/50 py-4' : undefined}>
                <CardTitle className="col-start-1 row-start-1 flex min-w-0 items-center gap-3">
                    {step && (
                        <span className="text-muted-foreground tabular-nums">{String(step).padStart(2, '0')}</span>
                    )}
                    {title}
                </CardTitle>
                <CardDescription className="col-start-1 row-start-2 min-w-0">{description}</CardDescription>
                {action ??
                    (cancelUrl && (
                        <CardAction>
                            <Button
                                type="button"
                                nativeButton={false}
                                variant="secondary"
                                size="icon"
                                disabled={disabled}
                                aria-label="Tutup"
                                render={<Link href={cancelUrl} />}
                            >
                                <HugeiconsIcon icon={XIcon} strokeWidth={2} aria-hidden="true" />
                            </Button>
                        </CardAction>
                    ))}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}
