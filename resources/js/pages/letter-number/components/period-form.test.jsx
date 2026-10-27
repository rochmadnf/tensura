import { Children, isValidElement } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { PeriodForm } from './period-form';
import { OptionSelect } from '@/components/option-select';
import { FormActions } from '@/components/form-actions';

const state = vi.hoisted(() => ({ form: null, initial: null, submit: vi.fn() }));
vi.mock('@/hooks/use-valibot-form', () => ({
    useValibotForm: (_schema, initial) => {
        state.initial = initial;
        return { form: state.form, submit: state.submit };
    },
}));
function findAll(node, predicate) {
    if (!isValidElement(node)) return [];
    return [
        ...(predicate(node) ? [node] : []),
        ...Children.toArray(node.props.children).flatMap((child) => findAll(child, predicate)),
    ];
}
const props = (legacy = false) => ({
    letterNumber: {
        id: 'number',
        name: 'Surat',
        code: '445',
        page_number: legacy ? 23 : null,
        sequence_number: legacy ? 44 : null,
        needs_initialization: legacy,
    },
    defaults: { year: 2026, month: 10 },
});
beforeEach(() => {
    state.form = {
        data: { year: '2026', month: '10', mode: 'custom', last_page_number: '0', last_sequence_number: '2' },
        errors: {},
        processing: false,
        setData: vi.fn(),
        clearErrors: vi.fn(),
        reset: vi.fn(),
    };
    state.submit.mockReset().mockReturnValue(true);
});
describe('period preparation editor', () => {
    it('copies legacy numbers on the server instead of exposing editable legacy seed inputs', () => {
        state.form.data.mode = 'legacy';
        const tree = PeriodForm(props(true));
        expect(state.initial.mode).toBe('legacy');
        expect(findAll(tree, (node) => node.props.name === 'last_page_number')).toHaveLength(0);
        expect(findAll(tree, (node) => node.type === OptionSelect && node.props.name === 'mode')).toHaveLength(0);
    });
    it('clears hidden seed errors and values when switching initialization mode', () => {
        const tree = PeriodForm(props());
        findAll(tree, (node) => node.type === OptionSelect && node.props.name === 'mode')[0].props.onValueChange(
            'empty',
        );
        expect(state.form.setData).toHaveBeenCalledWith('last_page_number', null);
        expect(state.form.setData).toHaveBeenCalledWith('last_sequence_number', null);
        expect(state.form.clearErrors).toHaveBeenCalledOnce();
    });
    it('prevents repeated requests while processing and resets locally on cancel', () => {
        state.form.processing = true;
        const tree = PeriodForm(props());
        findAll(tree, (node) => node.type === 'form')[0].props.onSubmit({ preventDefault: vi.fn() });
        expect(state.submit).not.toHaveBeenCalled();
        findAll(tree, (node) => node.type === FormActions)[0].props.onCancel();
        expect(state.form.reset).toHaveBeenCalledOnce();
        expect(state.form.clearErrors).toHaveBeenCalledOnce();
    });
});
