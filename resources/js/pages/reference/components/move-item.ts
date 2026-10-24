export function moveItem<T>(list: readonly T[], from: number, to: number): T[] {
    const result = [...list];
    if (
        !Number.isInteger(from) ||
        !Number.isInteger(to) ||
        from < 0 ||
        to < 0 ||
        from >= list.length ||
        to >= list.length ||
        from === to
    ) {
        return result;
    }
    const [item] = result.splice(from, 1);
    result.splice(to, 0, item);
    return result;
}
