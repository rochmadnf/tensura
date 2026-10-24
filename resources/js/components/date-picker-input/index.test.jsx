import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import { Calendar } from '@/components/ui/calendar';
import { id as indonesian } from 'react-day-picker/locale';
import { toLocalDate } from '@/lib/date-input';
import { DatePickerInput } from './index';

function renderInput(props = {}) {
    return renderToStaticMarkup(
        <DatePickerInput id="join-date" name="join_date" value="" onValueChange={() => {}} {...props} />,
    );
}
describe('DatePickerInput', () => {
    it('renders an editable Indonesian text input and accessible calendar button', () => {
        const markup = renderInput({ value: '2026-10-24' });
        expect(markup).toContain('data-slot="input-group"');
        expect(markup).toMatch(/<input\b(?=[^>]*id="join-date")(?=[^>]*name="join_date")(?=[^>]*type="text")[^>]*>/);
        expect(markup).toContain('value="24-10-2026"');
        expect(markup).toContain('placeholder="hh-bb-tttt"');
        expect(markup).toMatch(/<button\b[^>]*aria-label="Pilih tanggal"/);
        expect(markup).not.toContain('type="date"');
    });
    it('preserves partial and invalid text and forwards accessibility props', () => {
        const markup = renderInput({ value: '31-02-2026', 'aria-invalid': true, 'aria-describedby': 'date-error' });
        expect(markup).toContain('value="31-02-2026"');
        expect(markup).toContain('aria-invalid="true"');
        expect(markup).toContain('aria-describedby="date-error"');
        expect(renderInput({ value: '24-' })).toContain('value="24-"');
    });
    it('disables both editing and opening the calendar', () => {
        const controls = renderInput({ disabled: true }).match(/<(?:input|button)\b[^>]*>/g);
        expect(controls).toHaveLength(2);
        expect(controls.every((control) => control.includes('disabled=""'))).toBe(true);
    });
    it('supports the installed calendar locale with Indonesian months, days and navigation labels', () => {
        const markup = renderToStaticMarkup(
            <Calendar
                mode="single"
                locale={indonesian}
                captionLayout="dropdown"
                defaultMonth={toLocalDate('2026-10-01')}
                startMonth={toLocalDate('1900-01-01')}
                endMonth={toLocalDate('2026-12-31')}
            />,
        );
        expect(markup).toContain('Oktober');
        expect(markup).toContain('Senin');
        expect(markup).toContain('Ke bulan sebelumnya');
        expect(markup).toContain('Ke bulan berikutnya');
        expect(markup).toContain('Pilih bulan');
        expect(markup).toContain('Pilih tahun');
    });
});
