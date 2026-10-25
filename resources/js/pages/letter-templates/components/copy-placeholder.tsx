import { Copy01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import { gooeyToast } from '@/components/ui/goey-toaster';

export function CopyPlaceholder({ name }: { name: string }) {
    const placeholder = '${' + name + '}';
    async function copy() {
        try {
            await navigator.clipboard.writeText(placeholder);
            gooeyToast.success('Berhasil', { description: 'Placeholder disalin.' });
        } catch {
            gooeyToast.error('Galat', {
                description: 'Placeholder gagal disalin. Pilih teks placeholder untuk menyalinnya secara manual.',
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
                disabled={!/^[a-z][a-z0-9_]*$/.test(name) || name.length > 100}
                aria-label={`Salin placeholder ${name}`}
            >
                <HugeiconsIcon icon={Copy01Icon} data-icon="inline-start" aria-hidden="true" />
                Salin
            </Button>
        </div>
    );
}
