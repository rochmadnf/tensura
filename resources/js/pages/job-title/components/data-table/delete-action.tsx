import { DeleteResourceAction } from '@/components/delete-resource-action';
import jt from '@/routes/jt';
import type { JobTitleColumns } from './column';

export function DeleteAction({ jobTitle }: { jobTitle: JobTitleColumns }) {
    return (
        <DeleteResourceAction
            url={jt.destroy(jobTitle.id).url}
            name={jobTitle.name}
            title="Hapus Jabatan?"
            description={
                <>
                    Apakah Anda yakin ingin menghapus <strong>{jobTitle.name}</strong>? Data yang dihapus tidak dapat
                    dikembalikan.
                </>
            }
            successMessage={`${jobTitle.name} berhasil dihapus.`}
            errorMessage="Jabatan gagal dihapus. Silakan coba lagi."
        />
    );
}
