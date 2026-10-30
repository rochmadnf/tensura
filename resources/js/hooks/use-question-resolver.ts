import { useCallback, useEffect, useRef, useState } from 'react';
import { http } from '@inertiajs/react';
import { resolve } from '@/routes/lt/values';
import type { ResolvedQuestionField, ResolvedQuestions } from '@/schemas/work-calendar';

export type QuestionResolverState = {
    fields: Record<string, ResolvedQuestionField>;
    isResolving: boolean;
    errors: Record<string, string>;
};

export function useQuestionResolver(templateVersion: string | null | undefined) {
    const [state, setState] = useState<QuestionResolverState>({
        fields: {},
        isResolving: false,
        errors: {},
    });

    const activeRequestIdRef = useRef(0);
    const abortControllerRef = useRef<AbortController | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const resolveValues = useCallback(
        (values: Record<string, unknown>, immediate = false) => {
            if (!templateVersion) return;

            if (timerRef.current) {
                clearTimeout(timerRef.current);
                timerRef.current = null;
            }

            const execute = async () => {
                const requestId = ++activeRequestIdRef.current;

                if (abortControllerRef.current) {
                    abortControllerRef.current.abort();
                }
                const abortController = new AbortController();
                abortControllerRef.current = abortController;

                setState((prev) => ({ ...prev, isResolving: true }));

                try {
                    const response = await http.getClient().request({
                        url: resolve().url,
                        method: 'post',
                        data: {
                            template_version: templateVersion,
                            values,
                        },
                        signal: abortController.signal,
                        headers: { Accept: 'application/json' },
                    });

                    if (requestId !== activeRequestIdRef.current) return;

                    const rawData = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                    const payload = (rawData?.data ?? rawData) as ResolvedQuestions;

                    const fieldsMap: Record<string, ResolvedQuestionField> = {};
                    if (Array.isArray(payload?.fields)) {
                        for (const field of payload.fields) {
                            fieldsMap[field.id] = field;
                        }
                    }

                    setState({
                        fields: fieldsMap,
                        isResolving: false,
                        errors: {},
                    });
                } catch (err: unknown) {
                    if (requestId !== activeRequestIdRef.current) return;

                    let errorMessages: Record<string, string> = {};
                    const maybeHttpError = err as {
                        response?: { data?: string | { errors?: Record<string, string[]> } };
                    };

                    if (maybeHttpError.response?.data) {
                        const parsed =
                            typeof maybeHttpError.response.data === 'string'
                                ? JSON.parse(maybeHttpError.response.data)
                                : maybeHttpError.response.data;

                        if (parsed.errors && typeof parsed.errors === 'object') {
                            for (const [key, msgs] of Object.entries(parsed.errors)) {
                                if (Array.isArray(msgs) && msgs.length > 0) {
                                    errorMessages[key] = msgs[0]!;
                                }
                            }
                        }
                    }

                    setState((prev) => ({
                        ...prev,
                        isResolving: false,
                        errors: errorMessages,
                    }));
                }
            };

            if (immediate) {
                execute();
            } else {
                timerRef.current = setTimeout(execute, 300);
            }
        },
        [templateVersion],
    );

    useEffect(() => {
        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
            if (abortControllerRef.current) abortControllerRef.current.abort();
        };
    }, []);

    return {
        resolvedFields: state.fields,
        isResolving: state.isResolving,
        resolverErrors: state.errors,
        resolveValues,
    };
}
