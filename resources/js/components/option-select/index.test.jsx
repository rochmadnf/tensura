import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import { OptionSelect } from './index';

const items = [
    { value: '1', label: 'Dokter' },
    { value: '2', label: 'Perawat' },
    { value: '3', label: 'Kefarmasian' },
    { value: '4', label: 'Bidan' },
    { value: '5', label: 'Dokter Gigi' },
    { value: '6', label: 'Terapis Gigi' },
];

function renderSelect(props = {}) {
    return renderToStaticMarkup(
        <OptionSelect
            id="group"
            name="group"
            items={items}
            value=""
            onValueChange={() => {}}
            placeholder="Pilih kelompok jabatan"
            resetLabel="Reset kelompok jabatan"
            {...props}
        />,
    );
}

describe('OptionSelect', () => {
    it('exposes required validation on single and multiple searchable inputs', () => {
        for (const props of [{ required: true }, { required: true, multiple: true, value: [] }]) {
            const markup = renderSelect(props);
            const input = markup.match(/<input\b[^>]*role="combobox"[^>]*>/)?.[0];
            expect(input).toContain('required=""');
        }
    });

    it('keeps a regular select for five options', () => {
        const markup = renderSelect({ items: items.slice(0, 5) });

        expect(markup).toContain('data-slot="select-trigger"');
        expect(markup).toContain('Pilih kelompok jabatan');
        expect(markup).not.toContain('Reset kelompok jabatan');
    });

    it('provides a searchable input and a keyboard-accessible reset for more than five options', () => {
        const markup = renderSelect();

        expect(markup).toMatch(/<input\b[^>]*role="combobox"/);
        expect(markup).toContain('placeholder="Pilih kelompok jabatan"');
        expect(markup).toMatch(/<button\b[^>]*tabindex="0"[^>]*aria-label="Reset kelompok jabatan"/);
        expect(markup).not.toContain('data-slot="select-trigger"');
    });

    it('displays the saved label and submits its ID when editing', () => {
        const markup = renderSelect({ value: '3' });

        expect(markup).toMatch(/<input\b[^>]*role="combobox"[^>]*value="Kefarmasian"/);
        expect(markup).toMatch(/<input\b[^>]*name="group"[^>]*value="3"/);
    });

    it('disables the search, popup trigger, and reset while processing', () => {
        const markup = renderSelect({ value: '3', disabled: true });
        const controls = markup
            .match(/<(?:input|button)\b[^>]*>/g)
            .filter((control) =>
                /role="combobox"|aria-label="(?:Tampilkan pilihan|Reset kelompok jabatan)"/.test(control),
            );

        expect(controls).toHaveLength(3);
        expect(controls.every((control) => control.includes(' disabled=""'))).toBe(true);
    });

    it('marks the searchable input invalid for an empty required selection', () => {
        const markup = renderSelect({ 'aria-invalid': true });

        expect(markup).toMatch(/<input\b[^>]*role="combobox"[^>]*aria-invalid="true"/);
        expect(markup).toMatch(/<input\b[^>]*name="group"[^>]*value=""/);
    });

    it('uses checkboxes for multiple selection with at most five options', () => {
        const markup = renderSelect({ multiple: true, value: ['1', '3'], items: items.slice(0, 5) });

        expect(markup.match(/role="checkbox"/g)).toHaveLength(5);
        expect(markup.match(/aria-checked="true"/g)).toHaveLength(2);
        expect(markup).toContain('for="group-1"');
        expect(markup).not.toContain('role="combobox"');
    });

    it('uses a searchable combobox with removable chips for more than five multiple options', () => {
        const markup = renderSelect({ multiple: true, value: ['1', '3'] });

        expect(markup).toMatch(/<input\b[^>]*role="combobox"/);
        expect(markup.match(/data-slot="combobox-chip"/g)).toHaveLength(2);
        expect(markup).toContain('aria-label="Hapus Dokter"');
        expect(markup).toContain('aria-label="Hapus Kefarmasian"');
        expect(markup).toMatch(/<button\b[^>]*tabindex="0"[^>]*aria-label="Reset kelompok jabatan"/);
        expect(markup).toMatch(/<input\b[^>]*name="group"[^>]*value="1"/);
        expect(markup).toMatch(/<input\b[^>]*name="group"[^>]*value="3"/);
    });

    it('renders no chips for an empty multiple value', () => {
        const markup = renderSelect({ multiple: true, value: [] });

        expect(markup).toContain('placeholder="Pilih kelompok jabatan"');
        expect(markup).not.toContain('data-slot="combobox-chip"');
    });

    it('forces chip comboboxes for small multiple lists while preserving the selected label', () => {
        const markup = renderSelect({ multiple: true, forceCombobox: true, value: ['2'], items: items.slice(0, 2) });
        expect(markup).toContain('role="combobox"');
        expect(markup).toContain('data-slot="combobox-chip"');
        expect(markup).toContain('aria-label="Hapus Perawat"');
        expect(markup).not.toContain('role="checkbox"');
    });

    it('keeps an empty forced combobox searchable even when there are no available options', () => {
        const markup = renderSelect({
            multiple: true,
            forceCombobox: true,
            value: [],
            items: [],
            placeholder: 'Semua jabatan',
        });
        expect(markup).toContain('role="combobox"');
        expect(markup).toContain('placeholder="Semua jabatan"');
        expect(markup).not.toContain('data-slot="combobox-chip"');
    });

    it('allows clearing a saved optional value when forcing a small single combobox', () => {
        const markup = renderSelect({ forceCombobox: true, value: '2', items: items.slice(0, 2) });
        expect(markup).toContain('role="combobox"');
        expect(markup).toContain('aria-label="Reset kelompok jabatan"');
        expect(markup).toContain('value="Perawat"');
        expect(markup).not.toContain('data-slot="select-trigger"');
    });

    it('disables the input, chip removal, and clear button for a disabled multiple combobox', () => {
        const markup = renderSelect({ multiple: true, value: ['1'], disabled: true });
        const controls = markup
            .match(/<(?:input|button)\b[^>]*>/g)
            .filter((control) => /role="combobox"|aria-label="(?:Hapus Dokter|Reset kelompok jabatan)"/.test(control));

        expect(controls).toHaveLength(3);
        expect(controls.every((control) => / disabled=""|aria-disabled="true"/.test(control))).toBe(true);
    });
});
