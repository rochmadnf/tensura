import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vite-plus/test';
import { RepeatedTableGuide } from './repeated-table-guide';
vi.mock('@/components/ui/goey-toaster', () => ({ gooeyToast: { success: vi.fn(), error: vi.fn() } }));

describe('Repeated table instructions', () => {
    it('uses the current group and column names for both layouts and explains the final separator', () => {
        const variable = {
            name: 'petugas',
            option_source: 2,
            columns: [
                { placeholder: 'nama_petugas', source_attribute: 'name' },
                { placeholder: 'posisi_petugas', source_attribute: 'job_title' },
            ],
        };
        const options = {
            columns: {
                2: [
                    { id: 'name', label: 'Nama' },
                    { id: 'job_title', label: 'Jabatan' },
                ],
            },
        };
        const html = renderToStaticMarkup(<RepeatedTableGuide variable={variable} options={options} />);
        expect(html).toContain('${blok:petugas}');
        expect(html).toContain('${/blok:petugas}');
        expect(html).toContain('${nama_petugas}');
        expect(html).toContain('${posisi_petugas}');
        expect(html).toContain('Salin penanda awal');
        expect(html).toContain('Salin penanda akhir');
        expect(html).toContain('dihapus pada orang terakhir');
        expect(html).toContain('row_number');
        expect(html).not.toContain('pemohon');
    });
});
