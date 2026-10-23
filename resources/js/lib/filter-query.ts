export type FilterQueryValues = Record<string, readonly string[]>;

const FILTER_NAME = /^[a-z][a-z0-9_]*$/;

export function parseFilterQuery(filter: unknown): Record<string, string[]> {
    if (typeof filter !== 'string') {
        return {};
    }

    const filters = new Map<string, string[]>();

    for (const segment of filter.split('|')) {
        const parts = segment.split(':');

        if (parts.length !== 2) {
            continue;
        }

        const name = parts[0].trim();

        if (!FILTER_NAME.test(name)) {
            continue;
        }

        const values = [
            ...new Set([
                ...(filters.get(name) ?? []),
                ...parts[1]
                    .split(',')
                    .map((value) => value.trim())
                    .filter(Boolean),
            ]),
        ];

        if (values.length > 0) {
            filters.set(name, values);
        }
    }

    return Object.fromEntries(filters);
}

export function serializeFilterQuery(filters: FilterQueryValues): string | null {
    const segments: string[] = [];

    for (const [name, rawValues] of Object.entries(filters)) {
        if (!FILTER_NAME.test(name)) {
            continue;
        }

        const values = [
            ...new Set(rawValues.map((value) => value.trim()).filter((value) => value !== '' && !/[|:,]/.test(value))),
        ];

        if (values.length > 0) {
            segments.push(`${name}:${values.join(',')}`);
        }
    }

    return segments.length > 0 ? segments.join('|') : null;
}
