import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function useTableLoading(prop = 'resources') {
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        const pendingVisits = new Set<object>();
        const offStart = router.on('start', (event) => {
            if (event.detail.visit.only.includes(prop)) {
                pendingVisits.add(event.detail.visit);
                setIsLoading(true);
            }
        });

        const offFinish = router.on('finish', (event) => {
            pendingVisits.delete(event.detail.visit);
            setIsLoading(pendingVisits.size > 0);
        });

        return () => {
            offStart();
            offFinish();
        };
    }, [prop]);

    return isLoading;
}
