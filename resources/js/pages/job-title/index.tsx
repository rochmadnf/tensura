import { Head, Link, router } from '@inertiajs/react';
import { JobTitleDataTable } from './components/data-table';
import type { JobTitleColumns } from './components/data-table/column';
import type { PaginationMeta } from '@/components/data-table';
import { SectionData } from '@/components/section-data';
import jt from '@/routes/jt';
import { DataFilterDialog } from '@/components/data-filter-dialog';
import { OptionSelect } from '@/components/option-select';
import { Field, FieldLabel } from '@/components/ui/field';
import { useTableLoading } from '@/hooks/use-table-loading';
import { buildListingQuery } from '@/lib/listing-query';
import type { JobTitleGroupOption } from '@/schemas/job-title';

const pageName = 'Jabatan';

interface JobTitleIndexProps {
    resources: { data: JobTitleColumns[]; meta: PaginationMeta };
    filters: { keyword: string; groups: number[] };
    groups: JobTitleGroupOption[];
}

export default function JobTitleIndex({ resources, filters, groups }: JobTitleIndexProps) {
    const isLoading = useTableLoading('resources');
    const groupItems = groups.map((group) => ({ value: String(group.id), label: group.label }));

    function applyFilters(selectedGroups: string[]) {
        const query = buildListingQuery(
            window.location.search,
            { page: null, groups: null },
            { group: selectedGroups },
        );

        router.get(jt.index().url, query, {
            preserveScroll: true,
            showProgress: false,
            preserveState: true,
            replace: true,
            only: ['resources', 'filters'],
        });
    }

    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Halaman untuk mengelola data jabatan" />
            </Head>

            <div className="container mx-auto">
                <SectionData
                    renderButton={<Link href={jt.create().url} />}
                    textButton="Jabatan Baru"
                    searchUrl={jt.index().url}
                    defaultKeyword={filters.keyword}
                    filterAction={
                        <DataFilterDialog
                            title="Filter Jabatan"
                            value={filters.groups.map(String)}
                            emptyValue={[]}
                            activeCount={filters.groups.length}
                            onApply={applyFilters}
                            disabled={isLoading}
                        >
                            {({ value, onValueChange, disabled }) => (
                                <Field data-disabled={disabled}>
                                    <FieldLabel htmlFor="filter-groups">Kelompok Jabatan</FieldLabel>
                                    <OptionSelect
                                        multiple
                                        id="filter-groups"
                                        name="groups"
                                        items={groupItems}
                                        value={value}
                                        onValueChange={onValueChange}
                                        placeholder="Cari kelompok jabatan..."
                                        resetLabel="Reset kelompok jabatan"
                                        disabled={disabled}
                                    />
                                </Field>
                            )}
                        </DataFilterDialog>
                    }
                    dataTable={<JobTitleDataTable data={resources.data} meta={resources.meta} />}
                />
            </div>
        </>
    );
}

JobTitleIndex.layout = {
    pageName: pageName,
};
