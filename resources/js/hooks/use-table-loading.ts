import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function useTableLoading(prop = 'resources') {
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        const offStart = router.on('start', (event) => {
            if (event.detail.visit.only.includes(prop)) {
                setIsLoading(true);
            }
        });

        const offFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            offStart();
            offFinish();
        };
    }, [prop]);

    return isLoading;
}
