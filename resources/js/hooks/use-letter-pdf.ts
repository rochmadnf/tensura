import { useCallback, useEffect, useRef, useState } from 'react';
import { router, useHttp } from '@inertiajs/react';
import { store } from '@/routes/ltr/pdfs';
import type { LetterOperation, LetterPdf } from '@/schemas/letter-preview';

export function useLetterPdf(letterId: string, pdf: LetterPdf) {
    const [operation, setOperation] = useState(pdf.operation);
    const [error, setError] = useState('');
    const latest = useRef(pdf.operation);
    const key = useRef<{ source: string; value: string } | null>(null);
    const request = useHttp({ idempotency_key: '' });
    const accept = useCallback((result: LetterOperation) => {
        if (latest.current?.id === result.id && latest.current.status_revision > result.status_revision) return;
        latest.current = result;
        setOperation(result);
        if (['succeeded', 'failed', 'stale'].includes(result.status)) key.current = null;
    }, []);
    useEffect(() => {
        if (pdf.operation) accept(pdf.operation);
        else {
            latest.current = null;
            setOperation(null);
        }
    }, [pdf.source_id, pdf.operation, accept]);
    useEffect(() => {
        function changed(event: Event) {
            const result = (event as CustomEvent<LetterOperation>).detail;
            if (result.kind !== 'pdf' || result.letter_id !== letterId || result.source_id !== pdf.source_id) return;
            accept(result);
            if (['succeeded', 'failed', 'stale'].includes(result.status))
                router.reload({ only: ['pdf', 'officialPdf', 'versions', 'audits'], showProgress: false });
        }
        window.addEventListener('letter-operation', changed);
        return () => window.removeEventListener('letter-operation', changed);
    }, [letterId, pdf.source_id, accept]);
    const working = !!operation && ['queued', 'running'].includes(operation.status);
    async function generate() {
        if (!pdf.source_id || request.processing || working) return;
        setError('');
        if (key.current?.source !== pdf.source_id) key.current = { source: pdf.source_id, value: crypto.randomUUID() };
        request.transform(() => ({ idempotency_key: key.current!.value }));
        try {
            const response = (await request.post(store({ letter: letterId, artifact: pdf.source_id }).url)) as {
                operation: LetterOperation;
            };
            accept(response.operation);
            router.reload({ only: ['pdf', 'officialPdf', 'versions'], showProgress: false });
        } catch {
            setError('PDF belum dapat diminta. Word tetap tersedia; silakan coba lagi.');
        }
    }
    return {
        operation,
        working: working || request.processing,
        generate,
        error: [...Object.values(request.errors).flat(), error].filter(Boolean).join(' '),
    };
}
