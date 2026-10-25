import { Children, isValidElement } from 'react';
import { describe, expect, it, vi } from 'vite-plus/test';
import LetterTemplateEdit from './edit';

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

describe('Letter template card identity', () => {
    it.each([null, 'identity', 'configuration', 'document'])(
        'keeps sibling cards distinct when all cards share a version UUID and the active card is %s',
        (activeCard) => {
            state.activeCard = activeCard;
            const versionId = '01911111-1111-7111-8111-111111111111';
            const page = LetterTemplateEdit({
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
                test: { data: { id: versionId, validation_token: 'validation-token' } },
                documentUploadLimitKb: 10240,
                documentVersions: [],
            });

            checkSiblingKeys(page);
        },
    );
});
