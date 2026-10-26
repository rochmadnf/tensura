import { Badge } from '@/components/ui/badge';
import { FieldError } from '@/components/ui/field';
import { Item, ItemContent, ItemTitle } from '@/components/ui/item';

export function DocumentValidationResult({ errors, names }: { errors: string[]; names: string[] }) {
    const variables = [...new Set(names)];
    return (
        <section aria-label="Hasil Pemeriksaan DOCX" className="flex min-w-0 flex-col gap-4">
            <h3 className="text-sm font-medium">Hasil Pemeriksaan DOCX</h3>
            {errors.length > 0 && (
                <FieldError>
                    <ul className="flex flex-col gap-2">
                        {[...new Set(errors)].map((error) => (
                            <li key={error}>{error}</li>
                        ))}
                    </ul>
                </FieldError>
            )}
            <Item variant="muted">
                <ItemContent>
                    <ItemTitle>Variabel dalam Dokumen · {variables.length}</ItemTitle>
                    {variables.length > 0 ? (
                        <ul aria-label="Variabel dalam Dokumen" className="flex min-w-0 flex-wrap gap-2">
                            {variables.map((name) => (
                                <li key={name} className="max-w-full min-w-0">
                                    <Badge variant="outline" className="h-auto max-w-full whitespace-normal">
                                        <code className="wrap-anywhere">{'${' + name + '}'}</code>
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Tidak ada variabel yang ditemukan dalam dokumen.
                        </p>
                    )}
                </ItemContent>
            </Item>
        </section>
    );
}
