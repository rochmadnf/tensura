import { Children, isValidElement } from 'react';
import { describe, expect, it, vi } from 'vite-plus/test';
import LetterTemplateEdit from './edit';
import { LetterTemplateTestForm } from './components/test-form';

const state = vi.hoisted(() => ({ activeCard: null }));

vi.mock('react', async (importOriginal) => {
    const react = await importOriginal();
    return {
        ...react,
        useState: (initial) => [initial === null ? state.activeCard : initial, vi.fn()],
    };
});

function checkSiblingKeys(element) {
    if (!isValidElement(element)) return;
    const children = Children.toArray(element.props.children);
    const keys = children.filter(isValidElement).map((child) => child.key);
    expect(new Set(keys).size).toBe(keys.length);
    children.forEach(checkSiblingKeys);
}

function createPage(testOverrides = {}) {
    const versionId = '01911111-1111-7111-8111-111111111111';
    return LetterTemplateEdit({
        classifications: [],
        resource: {
            data: {
                id: '01922222-2222-7222-8222-222222222222',
                name: 'Surat Tugas',
                is_active: true,
                classification: null,
                draft: { id: versionId, number: 1 },
            },
        },
        configuration: { data: { id: versionId } },
        configurationOptions: {},
        document: { data: { id: versionId } },
        test: { data: { id: versionId, validation_token: 'validation-token', can_generate: false, ...testOverrides } },
        documentUploadLimitKb: 10240,
        documentVersions: [],
    });
}

function findTestCard(element) {
    if (!isValidElement(element)) return null;
    if (element.type === LetterTemplateTestForm) return element;
    for (const child of Children.toArray(element.props.children)) {
        const found = findTestCard(child);
        if (found) return found;
    }
    return null;
}

describe('Letter template card identity', () => {
    it.each([null, 'identity', 'configuration', 'document'])(
        'keeps sibling cards distinct when all cards share a version UUID and the active card is %s',
        (activeCard) => {
            state.activeCard = activeCard;
            const page = createPage();

            checkSiblingKeys(page);
        },
    );
    it('reinitializes sample inputs when first validation makes the form available', () => {
        state.activeCard = null;
        const before = findTestCard(createPage({ can_generate: false, variables: [] }));
        const after = findTestCard(
            createPage({ can_generate: true, variables: [{ id: 'variable', is_multiple: true }] }),
        );
        expect(after.key).not.toBe(before.key);
    });

    it('keeps sample inputs when regeneration replaces only the result token', () => {
        state.activeCard = null;
        const before = findTestCard(createPage({ can_generate: true, test_token: 'old-result' }));
        const after = findTestCard(createPage({ can_generate: true, test_token: 'new-result' }));
        expect(after.key).toBe(before.key);
    });
});
