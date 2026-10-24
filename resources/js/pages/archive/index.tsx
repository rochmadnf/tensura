import { Head, router } from '@inertiajs/react';
import { SectionData } from '@/components/section-data';
import { DataFilterDialog } from '@/components/data-filter-dialog';
import { OptionSelect } from '@/components/option-select';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { useTableLoading } from '@/hooks/use-table-loading';
import { buildListingQuery } from '@/lib/listing-query';
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

export default function ArchiveIndex({ resources, filters, context, modules }: ArchiveIndexProps) {
    const list = context === 'reference' ? referenceArchiveIndex : index;
    const loading = useTableLoading();
    const draft = { module: context === 'reference' ? '' : filters.module, kind: filters.kind as string };
    const emptyValue = { module: '', kind: '' };

    function applyFilters(value: typeof draft) {
        router.get(
            list().url,
            buildListingQuery(window.location.search, {
                page: null,
                module: context === 'reference' ? null : value.module || null,
                kind: value.kind || null,
            }),
            {
                preserveState: true,
                preserveScroll: true,
                showProgress: false,
                only: ['resources', 'filters'],
            },
        );
    }

    return (
        <>
            <Head title={context === 'reference' ? 'Arsip Reference' : 'Archive'} />
            <div className="container mx-auto flex min-w-0 flex-col gap-6">
                <p className="text-sm text-muted-foreground">
                    {context === 'reference'
                        ? 'Grup dan item Reference yang diarsipkan.'
                        : 'Data yang diarsipkan dari seluruh modul.'}
                </p>
                <SectionData
                    searchUrl={list().url}
                    defaultKeyword={filters.keyword}
                    filterAction={
                        <DataFilterDialog
                            value={draft}
                            emptyValue={emptyValue}
                            onApply={applyFilters}
                            activeCount={Number(draft.module !== '') + Number(draft.kind !== '')}
                            disabled={loading}
                        >
                            {({ value, onValueChange, disabled }) => (
                                <FieldGroup>
                                    {context === null && (
                                        <Field>
                                            <FieldLabel htmlFor="archive-module">Modul</FieldLabel>
                                            <OptionSelect
                                                id="archive-module"
                                                name="module"
                                                items={modules.map((module) => ({
                                                    value: module,
                                                    label: moduleLabel(module),
                                                }))}
                                                value={value.module}
                                                onValueChange={(module) => onValueChange({ ...value, module })}
                                                placeholder="Semua modul"
                                                resetLabel="Reset modul"
                                                disabled={disabled}
                                            />
                                        </Field>
                                    )}
                                    <Field>
                                        <FieldLabel htmlFor="archive-kind">Jenis</FieldLabel>
                                        <OptionSelect
                                            id="archive-kind"
                                            name="kind"
                                            items={[
                                                { value: 'package', label: 'Grup' },
                                                { value: 'record', label: 'Item' },
                                            ]}
                                            value={value.kind}
                                            onValueChange={(kind) => onValueChange({ ...value, kind })}
                                            placeholder="Semua jenis"
                                            resetLabel="Reset jenis"
                                            disabled={disabled}
                                        />
                                    </Field>
                                </FieldGroup>
                            )}
                        </DataFilterDialog>
                    }
                    dataTable={<ArchiveTable resources={resources} context={context} />}
                />
            </div>
        </>
    );
}
ArchiveIndex.layout = { pageName: 'Archive' };
