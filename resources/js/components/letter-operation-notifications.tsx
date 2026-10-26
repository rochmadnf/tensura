import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { connectLetterOperations } from '@/lib/letter-operation-stream';
import { events, show } from '@/routes/ltr';

export function LetterOperationNotifications() {
    const { auth } = usePage<{ auth: { user: { id: string } | null } }>().props;
    const actor = auth.user?.id;
    const [connected, setConnected] = useState(true);
    useEffect(() => {
        if (!actor) return;
        return connectLetterOperations({
            url: events().url,
            actor,
            onConnection: setConnected,
            onOperation: (operation) =>
                window.dispatchEvent(new CustomEvent('letter-operation', { detail: operation })),
            onTerminal: (operation) => {
                const label = operation.kind === 'issue' ? 'Penerbitan surat' : 'Preview surat';
                const notify = operation.status === 'succeeded' ? gooeyToast.success : gooeyToast.warning;
                notify(
                    operation.status === 'succeeded'
                        ? operation.kind === 'issue'
                            ? 'Surat berhasil diterbitkan'
                            : 'Preview surat siap'
                        : `${label}: ${operation.status_label}`,
                    {
                        id: `letter-${operation.id}-${operation.status_revision}`,
                        description: operation.subject,
                        action: { label: 'Buka hasil', onClick: () => router.visit(show(operation.letter_id).url) },
                    },
                );
            },
        });
    }, [actor]);
    if (!actor || connected) return null;
    return (
        <p role="status" className="px-4 py-2 text-sm text-muted-foreground">
            Notifikasi Surat sedang menyambungkan ulang. Status terbaru tetap tersedia pada detail surat.
        </p>
    );
}
