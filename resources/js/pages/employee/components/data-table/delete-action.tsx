import { DeleteResourceAction } from '@/components/delete-resource-action';
import empl from '@/routes/empl';
import type { EmployeeResource } from '@/schemas/employee';

export function DeleteAction({ employee }: { employee: EmployeeResource }) {
    return (
        <DeleteResourceAction
            url={empl.destroy(employee.id).url}
            name={employee.name.full}
            title="Hapus Pegawai?"
            description={
                <>
                    Apakah Anda yakin ingin menghapus <strong>{employee.name.full}</strong>? Data yang dihapus tidak
                    dapat dikembalikan.
                </>
            }
            successMessage={`${employee.name.full} berhasil dihapus.`}
            errorMessage="Pegawai gagal dihapus. Silakan coba lagi."
        />
    );
}
