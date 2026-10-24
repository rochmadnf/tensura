import { Head, Link, router } from '@inertiajs/react';
import { LetterTemplateDataTable } from './components/data-table';
import type { PaginationMeta } from '@/components/data-table';
import { SectionData } from '@/components/section-data';
import { DataFilterDialog } from '@/components/data-filter-dialog';
import { OptionSelect } from '@/components/option-select';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { useTableLoading } from '@/hooks/use-table-loading';
import { buildListingQuery } from '@/lib/listing-query';
import lt from '@/routes/lt';
import type { ClassificationOption, LetterTemplateResource } from '@/schemas/letter-template';

const pageName = 'Templat Surat';
interface LetterTemplateIndexProps {
    resources: { data: LetterTemplateResource[]; meta: PaginationMeta };
    filters: { keyword: string; status: string | null; classification: string | null };
    classifications: ClassificationOption[];
}

export default function LetterTemplateIndex({ resources, filters, classifications }: LetterTemplateIndexProps) {
    const isLoading = useTableLoading('resources');
    const draft = { status: filters.status ?? '', classification: filters.classification ?? '' };
    function applyFilters(value: typeof draft) {
        const query = buildListingQuery(
            window.location.search,
            { page: null },
            {
                status: value.status ? [value.status] : [],
                classification: value.classification ? [value.classification] : [],
            },
        );
        router.get(lt.index().url, query, {
            preserveScroll: true,
            preserveState: true,
            showProgress: false,
            replace: true,
            only: ['resources', 'filters'],
        });
    }
    return (
        <>
            <Head title={pageName}>
                <meta name="description" content="Kelola identitas dan daftar templat surat" />
            </Head>
            <div className="container mx-auto">
                <SectionData
                    renderButton={<Link href={lt.create().url} />}
                    textButton="Templat Surat Baru"
                    searchUrl={lt.index().url}
                    defaultKeyword={filters.keyword}
                    filterAction={
                        <DataFilterDialog
                            title="Filter Templat Surat"
                            value={draft}
                            emptyValue={{ status: '', classification: '' }}
                            activeCount={Number(!!draft.status) + Number(!!draft.classification)}
                            onApply={applyFilters}
                            disabled={isLoading}
                        >
                            {({ value, onValueChange, disabled }) => (
                                <FieldGroup>
                                    <Field data-disabled={disabled}>
                                        <FieldLabel htmlFor="filter-status">Status</FieldLabel>
                                        <OptionSelect
                                            id="filter-status"
                                            name="status"
                                            forceCombobox
                                            items={[
                                                { value: 'active', label: 'Aktif' },
                                                { value: 'inactive', label: 'Nonaktif' },
                                            ]}
                                            value={value.status}
                                            onValueChange={(status) => onValueChange({ ...value, status })}
                                            placeholder="Semua status"
                                            resetLabel="Reset status"
                                            disabled={disabled}
                                        />
                                    </Field>
                                    <Field data-disabled={disabled}>
                                        <FieldLabel htmlFor="filter-classification">Klasifikasi</FieldLabel>
                                        <OptionSelect
                                            id="filter-classification"
                                            name="classification"
                                            forceCombobox
                                            items={classifications.map((item) => ({
                                                value: item.id,
                                                label: item.label,
                                            }))}
                                            value={value.classification}
                                            onValueChange={(classification) =>
                                                onValueChange({ ...value, classification })
                                            }
                                            placeholder="Semua klasifikasi"
                                            resetLabel="Reset klasifikasi"
                                            disabled={disabled}
                                        />
                                    </Field>
                                </FieldGroup>
                            )}
                        </DataFilterDialog>
                    }
                    dataTable={<LetterTemplateDataTable data={resources.data} meta={resources.meta} />}
                />
            </div>
        </>
    );
}
LetterTemplateIndex.layout = { pageName };
