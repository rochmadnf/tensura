import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { XIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export function FormCard({
    title,
    description,
    cancelUrl,
    disabled = false,
    children,
    action,
}: {
    title: string;
    description: ReactNode;
    cancelUrl?: string;
    action?: ReactNode;
    disabled?: boolean;
    children: ReactNode;
}) {
    return (
        <Card className="w-full">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
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
