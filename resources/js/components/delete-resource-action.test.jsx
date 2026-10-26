import { Children, isValidElement } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { DeleteResourceAction } from './delete-resource-action';
import { Button } from '@/components/ui/button';

const state = vi.hoisted(() => ({ initial: null, destroy: vi.fn(), error: vi.fn() }));
vi.mock('react', async (original) => ({
    ...(await original()),
    useState: () => [false, vi.fn()],
    useRef: () => ({ current: false }),
}));
vi.mock('@inertiajs/react', async (original) => ({
    ...(await original()),
    useForm: (initial) => {
        state.initial = initial;
        return { delete: state.destroy, processing: false };
    },
}));
vi.mock('@/components/ui/goey-toaster', () => ({ gooeyToast: { error: state.error, success: vi.fn() } }));
const all = (node, predicate) =>
    !isValidElement(node)
        ? []
        : [
              ...(predicate(node) ? [node] : []),
              ...Children.toArray(node.props.children).flatMap((child) => all(child, predicate)),
          ];
const props = {
    url: '/letters/letter',
    name: 'Surat',
    title: 'Hapus draft?',
    description: 'Draft dihapus.',
    successMessage: 'Dihapus.',
    errorMessage: 'Gagal.',
};
beforeEach(() => {
    state.destroy.mockReset();
    state.error.mockReset();
});
describe('confirmed draft deletion through the shared action', () => {
    it('sends the revision/confirmation payload and prevents repeated clicks while the request is pending', () => {
        const tree = DeleteResourceAction({ ...props, data: { revision: 7, confirmed: true } });
        const confirm = all(tree, (node) => node.type === Button && node.props.variant === 'destructive')[0];
        confirm.props.onClick();
        confirm.props.onClick();
        expect(state.initial).toEqual({ revision: 7, confirmed: true });
        expect(state.destroy).toHaveBeenCalledOnce();
        state.destroy.mock.calls[0][1].onError({ revision: 'Draft diperbarui oleh Ani.' });
        expect(state.error).toHaveBeenCalledWith('Galat', { description: 'Draft diperbarui oleh Ani.' });
    });
    it('keeps ordinary CRUD payloads empty and blocks deletion when another editor is active', () => {
        const tree = DeleteResourceAction({ ...props, disabled: true });
        all(tree, (node) => node.type === Button && node.props.variant === 'destructive')[0].props.onClick();
        expect(state.initial).toEqual({});
        expect(state.destroy).not.toHaveBeenCalled();
    });
});
