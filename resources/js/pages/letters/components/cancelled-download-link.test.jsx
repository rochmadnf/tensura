import { Children, isValidElement } from 'react';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import { CancelledDownloadLink } from './cancelled-download-link';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
const state = vi.hoisted(() => ({ open: false }));
vi.mock('react', async (original) => ({
    ...(await original()),
    useState: () => [
        state.open,
        (value) => {
            state.open = value;
        },
    ],
}));
const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
beforeEach(() => {
    state.open = false;
});
it('requires acknowledgement before following a cancelled document download link', () => {
    let tree = nodes(CancelledDownloadLink({ cancelled: true, href: '/confirmed-download' }));
    const event = { preventDefault: vi.fn(), stopPropagation: vi.fn() };
    tree.find((node) => node.type === 'a').props.onClick(event);
    expect(event.preventDefault).toHaveBeenCalledOnce();
    tree = nodes(CancelledDownloadLink({ cancelled: true, href: '/confirmed-download' }));
    expect(tree.find((node) => node.type === Dialog).props.open).toBe(true);
    expect(tree.find((node) => node.type === Button && node.props.render).props.render.props.href).toBe(
        '/confirmed-download',
    );
});
it('keeps an active letter download direct', () => {
    const event = { preventDefault: vi.fn(), stopPropagation: vi.fn() };
    nodes(CancelledDownloadLink({ cancelled: false, href: '/word' }))
        .find((node) => node.type === 'a')
        .props.onClick(event);
    expect(event.preventDefault).not.toHaveBeenCalled();
    expect(state.open).toBe(false);
});
