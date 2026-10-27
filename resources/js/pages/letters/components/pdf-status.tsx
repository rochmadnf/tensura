import { FieldDescription, FieldError } from '@/components/ui/field';
import type { LetterOperation, LetterPdf } from '@/schemas/letter-preview';

export function LetterPdfStatus({
    pdf,
    operation,
    error,
}: {
    pdf: LetterPdf;
    operation: LetterOperation | null;
    error: string;
}) {
    return (
        <>
            {operation && (
                <p role="status" className="text-sm text-muted-foreground">
                    PDF: {operation.status_label}. {operation.message}
                </p>
            )}
            {pdf.reason && <FieldDescription>{pdf.reason}</FieldDescription>}
            {error && <FieldError>{error}</FieldError>}
            {pdf.artifact && (
                <>
                    {pdf.artifact.font_report.warning && (
                        <FieldDescription>{pdf.artifact.font_report.warning}</FieldDescription>
                    )}
                    {pdf.artifact.font_report.entries
                        .filter((entry) => !entry.available)
                        .map((entry) => (
                            <FieldDescription key={`${entry.family}-${entry.style}`}>
                                Font {entry.family} ({entry.style}) belum tersedia; PDF menggunakan{' '}
                                {entry.fallback ?? pdf.artifact!.font_report.fallback}.
                            </FieldDescription>
                        ))}
                    {!!pdf.artifact.font_report.limitations?.length && (
                        <details className="text-sm text-muted-foreground">
                            <summary>Keterbatasan pemeriksaan font</summary>
                            <ul>
                                {pdf.artifact.font_report.limitations.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </details>
                    )}
                </>
            )}
        </>
    );
}
