import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { index } from '@/routes/archives';
import { index as referenceArchiveIndex } from '@/routes/ref/archive';
import type { ArchiveContext, ArchiveFilters, ArchivePage, ArchiveResource } from '@/types/archive';
import { ArchiveTable } from './components/archive-table';
import { moduleLabel } from './components/presentation';

interface ArchiveIndexProps {
    resources: ArchivePage<ArchiveResource>;
    filters: ArchiveFilters;
    context: ArchiveContext;
    modules: string[];
}

function FilterSelect({
    name,
    label,
    value,
    items,
    disabled,
}: {
    name: string;
    label: string;
    value: string;
    items: { value: string; label: string }[];
    disabled: boolean;
}) {
    return (
        <Field className="w-full sm:w-44">
            <FieldLabel htmlFor={`archive-${name}`}>{label}</FieldLabel>
            <Select name={name} defaultValue={value} items={items} disabled={disabled}>
                <SelectTrigger id={`archive-${name}`} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        {items.map((item) => (
                            <SelectItem key={item.value} value={item.value}>
                                {item.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
        </Field>
    );
}

export default function ArchiveIndex({ resources, filters, context, modules }: ArchiveIndexProps) {
    const list = context === 'reference' ? referenceArchiveIndex : index;
    return (
        <>
            <Head title={context === 'reference' ? 'Arsip Reference' : 'Archive'} />
            <div className="container mx-auto flex min-w-0 flex-col gap-6">
                <p className="text-sm text-muted-foreground">
                    {context === 'reference'
                        ? 'Grup dan item Reference yang diarsipkan.'
                        : 'Data yang diarsipkan dari seluruh modul.'}
                </p>
                <Form
                    key={JSON.stringify(filters)}
                    action={list().url}
                    method="get"
                    options={{ preserveState: true, preserveScroll: true, only: ['resources', 'filters'] }}
                >
                    {({ processing }) => (
                        <FieldGroup className="flex flex-col items-end gap-4 sm:flex-row sm:flex-wrap">
                            <Field className="w-full min-w-0 sm:min-w-64 sm:flex-1">
                                <FieldLabel htmlFor="archive-keyword">Cari nama atau konten</FieldLabel>
                                <Input
                                    id="archive-keyword"
                                    name="keyword"
                                    defaultValue={filters.keyword}
                                    placeholder="Masukkan minimal 2 karakter"
                                    maxLength={255}
                                    disabled={processing}
                                />
                            </Field>
                            {context === null && (
                                <FilterSelect
                                    name="module"
                                    label="Modul"
                                    value={filters.module}
                                    items={[
                                        { value: '', label: 'Semua modul' },
                                        ...modules.map((module) => ({ value: module, label: moduleLabel(module) })),
                                    ]}
                                    disabled={processing}
                                />
                            )}
                            <FilterSelect
                                name="kind"
                                label="Jenis"
                                value={filters.kind}
                                items={[
                                    { value: '', label: 'Semua jenis' },
                                    { value: 'package', label: 'Grup' },
                                    { value: 'record', label: 'Item' },
                                ]}
                                disabled={processing}
                            />
                            <input type="hidden" name="per_page" value={resources.meta.per_page} />
                            <div className="flex items-center gap-2">
                                <Button type="submit" disabled={processing}>
                                    Cari
                                </Button>
                                <Button
                                    variant="outline"
                                    nativeButton={false}
                                    disabled={processing}
                                    render={<Link href={list({ query: { per_page: resources.meta.per_page } })} />}
                                >
                                    Reset filter
                                </Button>
                            </div>
                        </FieldGroup>
                    )}
                </Form>
                <ArchiveTable resources={resources} context={context} />
            </div>
        </>
    );
}
ArchiveIndex.layout = { pageName: 'Archive' };
