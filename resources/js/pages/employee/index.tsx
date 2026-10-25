import { useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { EmployeeDataTable } from './components/data-table';
import type { PaginationMeta } from '@/components/data-table';
import { SectionData } from '@/components/section-data';
import empl from '@/routes/empl';
import { DataFilterDialog } from '@/components/data-filter-dialog';
import { OptionSelect } from '@/components/option-select';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { gooeyToast } from '@/components/ui/goey-toaster';
import { useTableLoading } from '@/hooks/use-table-loading';
import { buildListingQuery } from '@/lib/listing-query';
import type { EmployeeFormOptions, EmployeeOption, EmployeeResource } from '@/schemas/employee';

const pageName = 'Pegawai';
interface EmployeeFilters {
    keyword: string;
    job_title: string[];
    job_group: number[];
    gender: number[];
    contract_status: number[];
    status: number[];
    tenure_min: number | null;
    tenure_max: number | null;
}
interface EmployeeFilterDraft {
    job_title: string[];
    job_group: string[];
    gender: string[];
    contract_status: string[];
    status: string[];
    tenure_min: string;
    tenure_max: string;
}
interface EmployeeIndexProps extends EmployeeFormOptions {
    resources: { data: EmployeeResource[]; meta: PaginationMeta };
    filters: EmployeeFilters;
    jobTitleGroups: EmployeeOption[];
}

function rangeError(value: EmployeeFilterDraft): string | null {
    if (
        [value.tenure_min, value.tenure_max].some(
            (bound) => bound !== '' && (!/^\d+$/.test(bound) || !Number.isSafeInteger(Number(bound))),
        )
    ) {
        return 'Masa kerja harus berupa bilangan bulat minimal 0.';
    }
    return value.tenure_min !== '' && value.tenure_max !== '' && Number(value.tenure_min) > Number(value.tenure_max)
        ? 'Masa kerja minimum tidak boleh melebihi maksimum.'
        : null;
}

export default function EmployeeIndex({
    resources,
    filters,
    jobTitles,
    jobTitleGroups,
    genders,
    contractStatuses,
    statuses,
}: EmployeeIndexProps) {
    const isLoading = useTableLoading('resources');
    const selectedJobGroups = useRef(new Map<string, number | null>());
    const draft: EmployeeFilterDraft = {
        job_title: filters.job_title,
        job_group: filters.job_group.map(String),
        gender: filters.gender.map(String),
        contract_status: filters.contract_status.map(String),
        status: filters.status.map(String),
        tenure_min: filters.tenure_min === null ? '' : String(filters.tenure_min),
        tenure_max: filters.tenure_max === null ? '' : String(filters.tenure_max),
    };
    const emptyValue: EmployeeFilterDraft = {
        job_title: [],
        job_group: [],
        gender: [],
        contract_status: [],
        status: [],
        tenure_min: '',
        tenure_max: '',
    };
    const selections = [
        { name: 'job_group', label: 'Kelompok Jabatan', items: jobTitleGroups },
        { name: 'job_title', label: 'Jabatan', items: jobTitles },
        { name: 'gender', label: 'Jenis Kelamin', items: genders },
        { name: 'contract_status', label: 'Status Kontrak', items: contractStatuses },
        { name: 'status', label: 'Status Pegawai', items: statuses },
    ] as const;

    function applyFilters(value: EmployeeFilterDraft) {
        const error = rangeError(value);
        if (error) {
            gooeyToast.error('Galat', { description: error });
            return;
        }
        const query = buildListingQuery(
            window.location.search,
            { page: null },
            {
                job_title: value.job_title,
                job_group: value.job_group,
                gender: value.gender,
                contract_status: value.contract_status,
                status: value.status,
                tenure_min: value.tenure_min === '' ? [] : [String(Number(value.tenure_min))],
                tenure_max: value.tenure_max === '' ? [] : [String(Number(value.tenure_max))],
            },
        );
        router.get(empl.index().url, query, {
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
                <meta name="description" content="Halaman untuk mengelola data pegawai" />
            </Head>
            <div className="container mx-auto">
                <SectionData
                    renderButton={<Link href={empl.create().url} />}
                    textButton="Pegawai Baru"
                    searchUrl={empl.index().url}
                    defaultKeyword={filters.keyword}
                    filterAction={
                        <DataFilterDialog
                            title="Filter Pegawai"
                            value={draft}
                            emptyValue={emptyValue}
                            activeCount={
                                draft.job_title.length +
                                draft.job_group.length +
                                draft.gender.length +
                                draft.contract_status.length +
                                draft.status.length +
                                Number(draft.tenure_min !== '') +
                                Number(draft.tenure_max !== '')
                            }
                            onApply={applyFilters}
                            disabled={isLoading}
                        >
                            {({ value, onValueChange, disabled }) => (
                                <div className="flex flex-col gap-6">
                                    {selections.map(({ name, label, items }) => (
                                        <Field key={name} data-disabled={disabled}>
                                            <FieldLabel htmlFor={`filter-${name}`}>{label}</FieldLabel>
                                            <OptionSelect
                                                multiple
                                                forceCombobox
                                                id={`filter-${name}`}
                                                name={name}
                                                remote={
                                                    name === 'job_title'
                                                        ? { source: 'job-titles', groups: value.job_group }
                                                        : undefined
                                                }
                                                items={items
                                                    .filter(
                                                        (item) =>
                                                            name !== 'job_title' ||
                                                            value.job_group.length === 0 ||
                                                            ('group' in item &&
                                                                item.group !== null &&
                                                                value.job_group.includes(String(item.group))),
                                                    )
                                                    .map((item) => ({
                                                        value: String(item.id),
                                                        label: item.label,
                                                    }))}
                                                value={value[name]}
                                                onValueChange={(selected, selectedItems) => {
                                                    if (name === 'job_title') {
                                                        for (const item of selectedItems ?? []) {
                                                            selectedJobGroups.current.set(
                                                                item.value,
                                                                item.group ?? null,
                                                            );
                                                        }
                                                    }
                                                    if (name === 'job_group') {
                                                        onValueChange({
                                                            ...value,
                                                            job_group: selected,
                                                            job_title: value.job_title.filter((id) => {
                                                                if (selected.length === 0) return true;
                                                                const group =
                                                                    selectedJobGroups.current.get(id) ??
                                                                    jobTitles.find((item) => item.id === id)?.group;
                                                                return (
                                                                    group !== null &&
                                                                    group !== undefined &&
                                                                    selected.includes(String(group))
                                                                );
                                                            }),
                                                        });
                                                    } else {
                                                        onValueChange({ ...value, [name]: selected });
                                                    }
                                                }}
                                                placeholder={`Semua ${label.toLowerCase()}`}
                                                resetLabel={`Reset ${label.toLowerCase()}`}
                                                disabled={disabled}
                                            />
                                        </Field>
                                    ))}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {(['tenure_min', 'tenure_max'] as const).map((name) => (
                                            <Field
                                                key={name}
                                                data-invalid={!!rangeError(value)}
                                                data-disabled={disabled}
                                            >
                                                <FieldLabel htmlFor={`filter-${name}`}>
                                                    Masa Kerja {name === 'tenure_min' ? 'Minimum' : 'Maksimum'} (Tahun)
                                                </FieldLabel>
                                                <Input
                                                    ref={(input) => {
                                                        input?.setCustomValidity(rangeError(value) ?? '');
                                                    }}
                                                    id={`filter-${name}`}
                                                    name={name}
                                                    type="number"
                                                    min={0}
                                                    step={1}
                                                    value={value[name]}
                                                    placeholder="Tanpa batas"
                                                    aria-invalid={!!rangeError(value)}
                                                    aria-describedby={rangeError(value) ? 'tenure-error' : undefined}
                                                    disabled={disabled}
                                                    onChange={(event) =>
                                                        onValueChange({ ...value, [name]: event.target.value })
                                                    }
                                                />
                                            </Field>
                                        ))}
                                    </div>
                                    {rangeError(value) && (
                                        <FieldError id="tenure-error">{rangeError(value)}</FieldError>
                                    )}
                                </div>
                            )}
                        </DataFilterDialog>
                    }
                    dataTable={<EmployeeDataTable data={resources.data} meta={resources.meta} />}
                />
            </div>
        </>
    );
}
EmployeeIndex.layout = { pageName };
