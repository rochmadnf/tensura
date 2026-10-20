export { cn } from 'cn';

export function getInitialName(name: string) {
    return name
        .match(/(^\S\S?|\b\S)?/g)
        ?.join('')
        .match(/(^\S|\S$)?/g)
        ?.join('')
        .toUpperCase();
}
