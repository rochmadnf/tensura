import { Children, isValidElement } from 'react';
import { beforeEach, expect, it, vi } from 'vite-plus/test';
import LetterShow from './show';
import { LetterForm } from './components/letter-form';
import { LetterIssueAction } from './components/issue-action';
const state = vi.hoisted(() => ({ values: [], index: 0 }));
vi.mock('react', async (original) => ({
    ...(await original()),
    useState(initial) {
        const index = state.index++;
        if (!(index in state.values)) state.values[index] = initial;
        return [
            state.values[index],
            (next) => {
                state.values[index] = next;
            },
        ];
    },
}));
const nodes = (node) => (!isValidElement(node) ? [] : [node, ...Children.toArray(node.props.children).flatMap(nodes)]);
const letter = {
    id: 'letter',
    subject: 'Surat',
    status: 1,
    template: { id: 'template', name: 'Templat' },
    editable: true,
    draft: { revision: 1, reasons: [], letter_date: '2026-10-26' },
    configuration: { version: 'template-version', number: 1, variables: [], signers: [] },
};
function render(
    resource = letter,
    pdf = { source_id: 'word', artifact: null, operation: null, reason: null, max_bytes: 1000 },
) {
    state.index = 0;
    return nodes(
        LetterShow({
            resource: { data: resource },
            audits: { data: [], current_page: 1, last_page: 1 },
            preview: { operation: null, artifact: null },
            issuance: { operation: null },
            pdf,
        }),
    );
}
beforeEach(() => {
    state.values = [];
});
it('keeps the opened editor mounted with its old data when another operator publishes the letter', () => {
    render()
        .find((node) => node.type === LetterForm)
        .props.onEdit();
    const editor = render().find((node) => node.type === LetterForm);
    const current = render({ ...letter, status: 2, editable: false, draft: undefined, configuration: undefined }).find(
        (node) => node.type === LetterForm,
    );
    expect(current).toBeDefined();
    expect(current.key).toBe(editor.key);
    expect(current.props.resource).toBe(letter);
    expect(current.props.configuration.version).toBe('template-version');
    current.props.onClose();
    expect(
        render({ ...letter, status: 2, editable: false, draft: undefined, configuration: undefined }).find(
            (node) => node.type === LetterForm,
        ),
    ).toBeUndefined();
});

it('uses a confirmed PDF for issuance when the DOCX viewer has not been confirmed', () => {
    const artifact = { id: 'pdf', fingerprint: 'pdf-proof', confirmed: true };
    const tree = render(letter, { source_id: 'word', artifact, max_bytes: 1000 });
    const issue = tree.find((node) => node.type === LetterIssueAction);
    expect(issue.props.preview.artifact.id).toBe('pdf');
    expect(issue.props.preview.artifact.fingerprint).toBe('pdf-proof');
});
