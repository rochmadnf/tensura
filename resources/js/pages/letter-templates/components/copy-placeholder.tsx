import { Copy01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { gooeyToast } from '@/components/ui/goey-toaster';

export function CopyPlaceholder({ name, label }: { name: string; label?: string }) {
    const placeholder = '${' + name + '}';
    async function copy() {
        try {
            await navigator.clipboard.writeText(placeholder);
            gooeyToast.success('Berhasil', { description: 'Variabel disalin.' });
        } catch {
            gooeyToast.error('Galat', {
                description: 'Variabel gagal disalin. Pilih teks variabel untuk menyalinnya secara manual.',
            });
        }
    }
    return (
        <div className="flex flex-wrap items-center gap-2">
            <code className="text-sm break-all">{placeholder}</code>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={copy}
                disabled={!/^(?:\/?blok:)?[a-z][a-z0-9_]*$/.test(name) || name.replace(/^\/?blok:/, '').length > 100}
                aria-label={label ?? `Salin variabel ${name}`}
            >
                <HugeiconsIcon icon={Copy01Icon} data-icon="inline-start" aria-hidden="true" />
                {label ?? 'Salin'}
            </Button>
        </div>
    );
}
