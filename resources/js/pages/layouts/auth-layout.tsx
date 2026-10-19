import { AuthLayoutProps } from '@/types/ui';
import { HugeiconsIcon } from '@hugeicons/react';
import { MailboxIcon } from '@hugeicons/core-free-icons';
import { usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function AuthLayout({ children, title, description }: AuthLayoutProps) {
    const { app } = usePage().props;
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
            <div className="flex w-full max-w-sm flex-col gap-6">
                <div className="flex items-center gap-2 self-center font-medium">
                    <div className="flex size-10 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <HugeiconsIcon icon={MailboxIcon} className="size-6" />
                    </div>
                    <span className="text-lg">{app.name}</span>
                </div>
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader className="text-center">
                            <CardTitle className="text-xl">{title}</CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </CardHeader>
                        <CardContent>{children}</CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
