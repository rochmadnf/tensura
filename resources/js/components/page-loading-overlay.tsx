import { router } from '@inertiajs/react';
import { useEffect, useId, useRef } from 'react';
import { Archive02Icon, Archive02IconHandle } from '@/components/ui/archive-02';

export function PageLoadingOverlay() {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const iconRef = useRef<Archive02IconHandle>(null);
    const titleId = useId();
    const descriptionId = useId();

    useEffect(() => {
        const dialog = dialogRef.current;

        if (!dialog) {
            return;
        }

        const pendingVisits = new Set<object>();
        let animationInterval: ReturnType<typeof setInterval> | undefined;
        let previousOverflow: string | undefined;

        const unlock = () => {
            clearInterval(animationInterval);
            animationInterval = undefined;
            dialog.close();

            if (previousOverflow !== undefined) {
                document.body.style.overflow = previousOverflow;
                previousOverflow = undefined;
            }
        };

        const offStart = router.on('start', (event) => {
            const visit = event.detail.visit;

            if (visit.prefetch) {
                return;
            }

            pendingVisits.add(visit);

            if (dialog.open) {
                return;
            }

            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            dialog.showModal();
            iconRef.current?.startAnimation();
            animationInterval = setInterval(() => iconRef.current?.startAnimation(), 1200);
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
            aria-labelledby={titleId}
            aria-describedby={descriptionId}
            tabIndex={-1}
            onCancel={(event) => event.preventDefault()}
            className="fixed inset-0 m-auto w-fit max-w-[calc(100%-2rem)] cursor-wait rounded-xl bg-background p-8 text-foreground outline-none backdrop:bg-background/70 backdrop:backdrop-blur-sm"
        >
            <div role="status" aria-live="polite" className="flex flex-col items-center gap-4 text-center">
                <Archive02Icon ref={iconRef} size={56} aria-hidden="true" className="text-primary/80" />
                <div className="space-y-1">
                    <p id={titleId} className="font-medium text-primary">
                        Sedang memproses...
                    </p>
                    <p id={descriptionId} className="text-sm text-primary/60">
                        Mohon tunggu sebentar.
                    </p>
                </div>
            </div>
        </dialog>
    );
}
