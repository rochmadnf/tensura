import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { buildListingQuery } from '@/lib/listing-query';
import { ListingSearch } from '@/lib/listing-search';
import { InputSearchSchema } from '@/schemas/input-search';

const DEBOUNCE_MS = 500;

export function useListingSearch(url: string, defaultKeyword: string) {
    const [keyword, setKeyword] = useState(defaultKeyword);
    const [error, setError] = useState<string>();
    const [navigation, setNavigation] = useState(0);
    const state = useRef(new ListingSearch(defaultKeyword));
    const cancel = useRef<(() => void) | undefined>(undefined);

    function visit(value: string) {
        const token = state.current.begin(value);
        if (token === null) return;
        cancel.current?.();
        router.get(url, buildListingQuery(window.location.search, { page: null, keyword: value || null }), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            showProgress: false,
            only: ['resources', 'filters'],
            onCancelToken: (request) => {
                cancel.current = () => request.cancel();
            },
            onSuccess: (page) => {
                const filters = page.props.filters as { keyword?: string } | undefined;
                state.current.succeed(token, filters?.keyword ?? value);
            },
            onFinish: () => {
                if (state.current.pending?.token === token) {
                    cancel.current = undefined;
                    state.current.finish(token);
                }
            },
        });
    }

    function search(raw: string) {
        if (raw.trim() === '') {
            setError(undefined);
            visit('');
            return;
        }
        const result = InputSearchSchema.validate({ keyword: raw });
        if (!result.success) {
            setError(result.errors.keyword?.[0] ?? 'Kata kunci tidak valid.');
            return;
        }
        setError(undefined);
        visit(result.data.keyword);
    }

    const onDebounce = useEffectEvent(() => {
        if (keyword.trim() === '' || keyword.trim().length >= 2) search(keyword);
    });

    useEffect(() => {
        const timeout = setTimeout(() => onDebounce(), DEBOUNCE_MS);
        return () => clearTimeout(timeout);
    }, [keyword, navigation, url]);

    const onDefaultKeyword = useEffectEvent(() => {
        if (!state.current.pending && keyword.trim() === state.current.applied) {
            state.current.navigate(defaultKeyword);
            setKeyword(defaultKeyword);
            setError(undefined);
        }
    });

    useEffect(() => {
        onDefaultKeyword();
    }, [defaultKeyword]);

    useEffect(() => {
        const offNavigate = router.on('navigate', (event) => {
            if (event.detail.type !== 'history') return;
            const filters = event.detail.page.props.filters as { keyword?: string } | undefined;
            state.current.navigate(filters?.keyword ?? '');
            cancel.current?.();
            cancel.current = undefined;
            setKeyword(filters?.keyword ?? '');
            setError(undefined);
            setNavigation((value) => value + 1);
        });
        return () => {
            offNavigate();
            cancel.current?.();
        };
    }, []);

    return {
        keyword,
        error,
        setKeyword: (value: string) => {
            setKeyword(value);
            setError(undefined);
        },
        submit: () => search(keyword),
        reset: () => {
            setKeyword('');
            search('');
        },
    };
}
