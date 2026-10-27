import { Head, Link, router } from '@inertiajs/react';
import { SectionData } from '@/components/section-data';
import type { PaginationMeta } from '@/components/data-table';
import { DataFilterDialog } from '@/components/data-filter-dialog';
import { OptionSelect } from '@/components/option-select';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { buildListingQuery } from '@/lib/listing-query';
import { useTableLoading } from '@/hooks/use-table-loading';
import type { LetterResource } from '@/schemas/letter';
import { create, index } from '@/routes/ltr';
import { LetterDataTable } from './components/data-table';

type FilterValues = { status: string; template: string; creator: string; year: string; month: string };
export default function LetterIndex({
    resources,
    filters,
    selectedOptions,
}: {
    resources: { data: LetterResource[]; meta: PaginationMeta };
    filters: { keyword: string } & { [K in keyof FilterValues]: string | null };
    selectedOptions: { templates: { value: string; label: string }[]; creators: { value: string; label: string }[] };
}) {
    const disabled = useTableLoading('resources');
    const value: FilterValues = {
        status: filters.status ?? '',
        template: filters.template ?? '',
        creator: filters.creator ?? '',
        year: filters.year ?? '',
        month: filters.month ?? '',
    };
    const empty: FilterValues = { status: '', template: '', creator: '', year: '', month: '' };
    const apply = (values: FilterValues) =>
        router.get(
            index().url,
            buildListingQuery(
                window.location.search,
                { page: null },
                Object.fromEntries(Object.entries(values).map(([key, value]) => [key, value ? [value] : []])),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                showProgress: false,
                replace: true,
                only: ['resources', 'filters'],
            },
        );
    return (
        <>
            <Head title="Surat">
                <meta name="description" content="Kelola draft surat bersama dari templat aktif" />
            </Head>
            <div className="container mx-auto min-w-0">
                <SectionData
                    renderButton={<Link href={create().url} />}
                    textButton="Surat Baru"
                    searchUrl={index().url}
                    defaultKeyword={filters.keyword}
                    dataTable={<LetterDataTable data={resources.data} meta={resources.meta} />}
                    filterAction={
                        <DataFilterDialog
                            title="Filter Surat"
                            value={value}
                            emptyValue={empty}
                            activeCount={Object.values(value).filter(Boolean).length}
                            onApply={apply}
                            disabled={disabled}
                        >
                            {({ value, onValueChange, disabled }) => (
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="letter-filter-status">Status</FieldLabel>
                                        <OptionSelect
                                            id="letter-filter-status"
                                            name="status"
                                            value={value.status}
                                            items={[
                                                { value: '1', label: 'Draft' },
                                                { value: '2', label: 'Terbit' },
                                                { value: '3', label: 'Batal' },
                                            ]}
                                            onValueChange={(status) => onValueChange({ ...value, status })}
                                            placeholder="Semua status"
                                            resetLabel="Reset status"
                                            disabled={disabled}
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="letter-filter-template">Templat</FieldLabel>
                                        <OptionSelect
                                            id="letter-filter-template"
                                            name="template"
                                            value={value.template}
                                            items={selectedOptions.templates}
                                            remote={{ source: 'letter-templates-all' }}
                                            onValueChange={(template) => onValueChange({ ...value, template })}
                                            placeholder="Semua templat"
                                            resetLabel="Reset templat"
                                            disabled={disabled}
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="letter-filter-creator">Pembuat</FieldLabel>
                                        <OptionSelect
                                            id="letter-filter-creator"
                                            name="creator"
                                            value={value.creator}
                                            items={selectedOptions.creators}
                                            remote={{ source: 'letter-creators' }}
                                            onValueChange={(creator) => onValueChange({ ...value, creator })}
                                            placeholder="Semua pembuat"
                                            resetLabel="Reset pembuat"
                                            disabled={disabled}
                                        />
                                    </Field>
                                    <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                        <Field>
                                            <FieldLabel htmlFor="letter-filter-year">Tahun</FieldLabel>
                                            <Input
                                                id="letter-filter-year"
                                                type="number"
                                                min={1}
                                                max={9999}
                                                value={value.year}
                                                disabled={disabled}
                                                onChange={(event) =>
                                                    onValueChange({ ...value, year: event.target.value })
                                                }
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor="letter-filter-month">Bulan</FieldLabel>
                                            <OptionSelect
                                                id="letter-filter-month"
                                                name="month"
                                                value={value.month}
                                                items={Array.from({ length: 12 }, (_, index) => ({
                                                    value: String(index + 1),
                                                    label: new Date(2026, index, 1).toLocaleDateString('id-ID', {
                                                        month: 'long',
                                                    }),
                                                }))}
                                                onValueChange={(month) => onValueChange({ ...value, month })}
                                                placeholder="Semua bulan"
                                                resetLabel="Reset bulan"
                                                disabled={disabled}
                                            />
                                        </Field>
                                    </FieldGroup>
                                </FieldGroup>
                            )}
                        </DataFilterDialog>
                    }
                />
            </div>
        </>
    );
}
LetterIndex.layout = { pageName: 'Surat' };
