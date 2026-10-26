import { createDataTableColumnHelper } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { TableActionButton } from '@/components/table-action-button';
import { InformationCircleIcon } from '@hugeicons/core-free-icons';
import type { LetterResource } from '@/schemas/letter';
import { show } from '@/routes/ltr';

const helper = createDataTableColumnHelper<LetterResource>();
export const columns = helper.columns([
    helper.accessor('subject', {
        header: 'Perihal',
        cell: ({ cell }) => <span className="font-medium">{cell.getValue()}</span>,
    }),
    helper.accessor('number', { header: 'Nomor', cell: ({ cell }) => cell.getValue() ?? 'Belum terbit' }),
    helper.accessor('template', { header: 'Templat', cell: ({ cell }) => cell.getValue().name }),
    helper.accessor('letter_date', { header: 'Tanggal Surat' }),
    helper.accessor('status_label', {
        header: 'Status',
        cell: ({ cell }) => <Badge variant="secondary">{cell.getValue()}</Badge>,
    }),
    helper.accessor('creator', { header: 'Pembuat' }),
    helper.display({
        id: 'actions',
        header: 'Aksi',
        cell: ({ row }) => (
            <TableActionButton
                icon={InformationCircleIcon}
                variant="info"
                href={show(row.original.id).url}
                label={`Detail surat ${row.original.subject}`}
                tooltip="Lihat surat"
            />
        ),
    }),
]);
