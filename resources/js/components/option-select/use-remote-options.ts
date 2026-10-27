import { useEffect, useRef, useState } from 'react';
import options from '@/routes/options';

export type SelectOption = { value: string; label: string; group?: number | null };
export type RemoteOptions = {
    source:
        | 'classifications'
        | 'job-titles'
        | 'reference-groups'
        | 'employees'
        | 'variable-options'
        | 'signer-candidates'
        | 'letter-templates'
        | 'letter-templates-all'
        | 'letter-creators';
    context?: string;
    preserveSelectedLabels?: boolean;
    groups?: string[];
};

export function useRemoteOptions(remote: RemoteOptions | undefined, value: string | string[], seed: SelectOption[]) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [revision, setRevision] = useState(0);
    const [result, setResult] = useState<SelectOption[]>([]);
    const [labels, setLabels] = useState<SelectOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const requestId = useRef(0);
    const selectedKey = JSON.stringify(Array.isArray(value) ? value : value ? [value] : []);
    const source = remote?.source;
    const context = remote?.context;
    const groupsKey = JSON.stringify(remote?.groups ?? []);

    useEffect(() => {
        if (!source || (!open && selectedKey === '[]')) return;
        const controller = new AbortController();
        const id = ++requestId.current;
        setLoading(true);
        setError(false);
        const timer = setTimeout(
            async () => {
                try {
                    const response = await fetch(
                        options.index.url({
                            query: {
                                source,
                                context,
                                groups: JSON.parse(groupsKey),
                                q: search,
                                selected: JSON.parse(selectedKey),
                            },
                        }),
                        { signal: controller.signal, headers: { Accept: 'application/json' }, cache: 'no-store' },
                    );
                    if (!response.ok) throw new Error('Pilihan gagal dimuat.');
                    const data: { items: SelectOption[]; selected: SelectOption[] } = await response.json();
                    if (controller.signal.aborted || id !== requestId.current) return;
                    setResult(data.items);
                    setLabels((previous) =>
                        Array.from(
                            new Map(
                                [...previous, ...data.selected, ...data.items].map((item) => [item.value, item]),
                            ).values(),
                        ),
                    );
                } catch {
                    if (!controller.signal.aborted && id === requestId.current) {
                        setResult([]);
                        setError(true);
                    }
                } finally {
                    if (!controller.signal.aborted && id === requestId.current) setLoading(false);
                }
            },
            search ? 500 : 0,
        );
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [source, context, groupsKey, open, search, selectedKey, revision]);

    const selected = resolveSelectedOptions(
        JSON.parse(selectedKey) as string[],
        seed,
        [...labels, ...result],
        remote?.preserveSelectedLabels ?? false,
    );
    return {
        items: remote ? result : seed,
        selected,
        loading: !!remote && loading,
        error: !!remote && error,
        retry: () => setRevision((previous) => previous + 1),
        comboboxProps: remote
            ? {
                  filter: null,
                  onOpenChange: (next: boolean) => {
                      setOpen(next);
                      if (next) setSearch('');
                  },
                  onInputValueChange: (next: string, details: { reason: string }) => {
                      if (details.reason === 'input-change' || details.reason === 'input-clear') setSearch(next);
                  },
              }
            : {},
    };
}

export function resolveSelectedOptions(
    ids: string[],
    seed: SelectOption[],
    loaded: SelectOption[],
    preserveSavedLabels = false,
): SelectOption[] {
    const known = new Map(
        (preserveSavedLabels ? [...loaded, ...seed] : [...seed, ...loaded]).map((item) => [item.value, item]),
    );
    return ids.map((id) => known.get(id)).filter((item): item is SelectOption => !!item);
}
