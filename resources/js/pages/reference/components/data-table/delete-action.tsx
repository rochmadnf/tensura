import { DeleteResourceAction } from '@/components/delete-resource-action';
import ref from '@/routes/ref';
import type { ReferenceGroupColumns } from './column';

export function DeleteAction({ referenceGroup }: { referenceGroup: ReferenceGroupColumns }) {
    return (
        <DeleteResourceAction
            url={ref.destroy(referenceGroup.id).url}
            name={referenceGroup.name}
            title="Hapus Grup Referensi?"
            description={
                <>
                    Apakah Anda yakin ingin menghapus <strong>{referenceGroup.name}</strong>? Grup beserta{' '}
                    {referenceGroup.items_count} item akan dipindahkan ke arsip dan dapat dipulihkan.
                </>
            }
            successMessage={`${referenceGroup.name} berhasil dipindahkan ke arsip.`}
            errorMessage="Grup Referensi gagal dihapus. Silakan coba lagi."
        />
    );
}
