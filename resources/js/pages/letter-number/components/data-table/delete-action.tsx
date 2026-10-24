import { DeleteResourceAction } from '@/components/delete-resource-action';
import ln from '@/routes/ln';
import type { LetterNumberColumns } from './column';

export function DeleteAction({ letterNumber }: { letterNumber: LetterNumberColumns }) {
    return (
        <DeleteResourceAction
            url={ln.destroy(letterNumber.id).url}
            name={letterNumber.name}
            title="Hapus Penomoran Surat?"
            description={
                <>
                    Apakah Anda yakin ingin menghapus <strong>{letterNumber.name}</strong> ({letterNumber.code})? Data
                    yang dihapus tidak dapat dikembalikan.
                </>
            }
            successMessage={`${letterNumber.name} berhasil dihapus.`}
            errorMessage="Penomoran Surat gagal dihapus. Silakan coba lagi."
        />
    );
}
