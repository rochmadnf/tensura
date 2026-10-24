import { Head } from '@inertiajs/react';
import { useState } from 'react';
import type { ReferenceGroupDetail } from '@/schemas/reference';
import { ReferenceGroupForm } from './components/reference-group-form';
import { ItemManager } from './components/item-manager';

const pageName = 'Ubah Daftar Referensi';
interface ReferenceEditProps {
    resource: { data: ReferenceGroupDetail };
}
export default function ReferenceEdit({ resource }: ReferenceEditProps) {
    const [groupProcessing, setGroupProcessing] = useState(false);
    const [itemsProcessing, setItemsProcessing] = useState(false);
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Ubah informasi grup dan kelola urutan item referensi." />
            </Head>
            <div className="container mx-auto flex flex-col gap-6">
                <ReferenceGroupForm
                    key={resource.data.id}
                    group={resource.data}
                    disabled={itemsProcessing}
                    onProcessingChange={setGroupProcessing}
                />
                <ItemManager group={resource.data} disabled={groupProcessing} onProcessingChange={setItemsProcessing} />
            </div>
        </>
    );
}
ReferenceEdit.layout = { pageName };
