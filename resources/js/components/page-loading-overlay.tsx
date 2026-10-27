import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export function PageLoadingOverlay() {
    const dialogRef = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = dialogRef.current;

        if (!dialog) {
            return;
        }

        const pendingVisits = new Set<object>();

        const unlock = () => {
            dialog.close();
        };

        const offStart = router.on('start', (event) => {
            const visit = event.detail.visit;

            if (visit.prefetch || !visit.showProgress) {
                return;
            }

            pendingVisits.add(visit);

            if (dialog.open) {
                return;
            }

            dialog.showModal();
        });

        const offFinish = router.on('finish', (event) => {
            if (pendingVisits.delete(event.detail.visit) && pendingVisits.size === 0) {
                unlock();
            }
        });

        return () => {
            offStart();
            offFinish();
            unlock();
        };
    }, []);

    return (
        <dialog
            ref={dialogRef}
            data-slot="page-loading-overlay"
            aria-label="Sedang memproses, mohon tunggu."
            tabIndex={-1}
            onCancel={(event) => event.preventDefault()}
            className="fixed inset-0 m-0 size-full max-h-none max-w-none cursor-wait border-0 bg-transparent p-0 outline-none backdrop:bg-transparent"
        />
    );
}
