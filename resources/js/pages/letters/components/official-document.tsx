import { CancelledDownloadLink } from './cancelled-download-link';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowDown01Icon } from '@hugeicons/core-free-icons';
import { FormCard } from '@/components/form-card';
import { DocxPreview } from '@/components/docx-preview';
import { Button } from '@/components/ui/button';
import { PdfPreview } from '@/components/pdf-preview';
import { useLetterPdf } from '@/hooks/use-letter-pdf';
import { file as pdfFile, download as pdfDownload } from '@/routes/ltr/pdfs';
import { LetterPdfStatus } from './pdf-status';
import { FieldDescription } from '@/components/ui/field';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { file, download } from '@/routes/ltr/versions';
import type { LetterOfficial, LetterPdf } from '@/schemas/letter-preview';

export function LetterOfficialDocument({
    letterId,
    official,
    pdf,
    historical = false,
    cancelled = false,
    confirmation,
}: {
    letterId: string;
    official: LetterOfficial;
    pdf: LetterPdf;
    historical?: boolean;
    cancelled?: boolean;
    confirmation?: string;
}) {
    const conversion = useLetterPdf(letterId, pdf);
    return (
        <FormCard
            sectioned
            title={historical ? 'Dokumen Historis' : cancelled ? 'Dokumen Surat Batal' : 'Dokumen Resmi'}
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
                    {pdf.artifact && (
                        <PdfPreview
                            url={pdfFile({ letter: letterId, artifact: pdf.artifact.id }).url}
                            maxBytes={pdf.max_bytes}
                        />
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger render={<Button variant="outline" />}>
                            Unduh <HugeiconsIcon icon={ArrowDown01Icon} data-icon="inline-end" aria-hidden="true" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    render={
                                        <CancelledDownloadLink
                                            cancelled={cancelled}
                                            href={
                                                download(
                                                    { letter: letterId, version: official.id },
                                                    { query: cancelled ? { confirmation } : {} },
                                                ).url
                                            }
                                        />
                                    }
                                >
                                    Word (.docx)
                                </DropdownMenuItem>
                                {pdf.artifact ? (
                                    <DropdownMenuItem
                                        render={
                                            <CancelledDownloadLink
                                                cancelled={cancelled}
                                                href={
                                                    pdfDownload(
                                                        { letter: letterId, artifact: pdf.artifact.id },
                                                        { query: cancelled ? { confirmation } : {} },
                                                    ).url
                                                }
                                            />
                                        }
                                    >
                                        PDF (.pdf)
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem
                                        disabled={conversion.working || !pdf.source_id}
                                        onClick={() => void conversion.generate()}
                                    >
                                        {conversion.working
                                            ? 'Menyiapkan PDF…'
                                            : conversion.operation?.status === 'failed' ||
                                                conversion.operation?.status === 'stale'
                                              ? 'PDF (.pdf) — coba lagi'
                                              : 'PDF (.pdf)'}
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <FieldDescription>
                    Word resmi mempertahankan font dokumen. PDF disiapkan saat diminta; font yang belum tersedia memakai
                    pengganti.
                </FieldDescription>
                <LetterPdfStatus pdf={pdf} operation={conversion.operation} error={conversion.error} />
                {official.reason && <p className="text-sm">Alasan: {official.reason}</p>}
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
