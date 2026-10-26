import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowDown01Icon } from '@hugeicons/core-free-icons';
import { FormCard } from '@/components/form-card';
import { DocxPreview } from '@/components/docx-preview';
import { Button } from '@/components/ui/button';
import { FieldDescription } from '@/components/ui/field';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { file, download } from '@/routes/ltr/versions';
import type { LetterOfficial } from '@/schemas/letter-preview';

export function LetterOfficialDocument({ letterId, official }: { letterId: string; official: LetterOfficial }) {
    return (
        <FormCard
            sectioned
            title="Dokumen Resmi"
            description={`Versi ${official.version} · diterbitkan oleh ${official.issued_by} pada ${new Date(official.issued_at).toLocaleString('id-ID')}`}
        >
            <div className="flex flex-col gap-4">
                <p className="text-sm font-medium break-words">{official.number}</p>
                <div className="flex flex-wrap gap-2">
                    <DocxPreview
                        url={file({ letter: letterId, version: official.id }).url}
                        maxBytes={official.max_bytes}
                        fontReport={official.font_report}
                        title="Preview Surat Terbit"
                    />
                    <DropdownMenu>
                        <DropdownMenuTrigger render={<Button variant="outline" />}>
                            Unduh <HugeiconsIcon icon={ArrowDown01Icon} data-icon="inline-end" aria-hidden="true" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    render={<a href={download({ letter: letterId, version: official.id }).url} />}
                                >
                                    Word (.docx)
                                </DropdownMenuItem>
                                <DropdownMenuItem disabled>PDF (.pdf) — belum tersedia</DropdownMenuItem>
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <FieldDescription>
                    Word resmi mempertahankan font dokumen. Konversi PDF belum tersedia.
                </FieldDescription>
                <details>
                    <summary className="cursor-pointer text-sm">Lihat nilai yang diterbitkan</summary>
                    <dl className="flex flex-col gap-2">
                        {Object.entries(official.resolved).map(([name, value]) => (
                            <div key={name}>
                                <dt className="text-sm font-medium">{name}</dt>
                                <dd className="text-sm break-words whitespace-pre-wrap">{value || '—'}</dd>
                            </div>
                        ))}
                    </dl>
                </details>
            </div>
        </FormCard>
    );
}
