import { parseFilterQuery, serializeFilterQuery, type FilterQueryValues } from './filter-query';

type ListingQueryChanges = Record<string, string | number | string[] | null>;

export function buildListingQuery(
    search: string,
    changes: ListingQueryChanges,
    filterChanges?: FilterQueryValues,
): Record<string, string> {
    const params = new URLSearchParams(search);

    for (const [name, value] of Object.entries(changes)) {
        const keysToRemove = [...params.keys()].filter((key) => key === name || key.startsWith(`${name}[`));

        for (const key of keysToRemove) {
            params.delete(key);
        }

        if (Array.isArray(value)) {
            value.forEach((item, index) => params.set(`${name}[${index}]`, item));
        } else if (value !== null) {
            params.set(name, String(value));
        }
    }

    if (filterChanges) {
        const filter = serializeFilterQuery({ ...parseFilterQuery(params.get('filter')), ...filterChanges });

        if (filter) {
            params.set('filter', filter);
        } else {
            params.delete('filter');
        }
    }

    const query: Record<string, string> = {};
    const arrayIndexes: Record<string, number> = {};

    for (const [key, value] of params) {
        if (key.endsWith('[]')) {
            const name = key.slice(0, -2);
            const index = arrayIndexes[name] ?? 0;
            query[`${name}[${index}]`] = value;
            arrayIndexes[name] = index + 1;
        } else {
            query[key] = value;
        }
    }

    return query;
}
