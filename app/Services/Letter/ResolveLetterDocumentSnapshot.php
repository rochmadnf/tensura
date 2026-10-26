<?php

namespace App\Services\Letter;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Services\LetterTemplate\EmployeeAttributeCatalog;
use App\Services\LetterTemplate\FormatEmployeeDate;
use App\Services\LetterTemplate\LetterTemplateTestFields;
use App\Services\LetterTemplate\ResolveEmployeeAttributes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ResolveLetterDocumentSnapshot
{
    public function __construct(private LetterTemplateTestFields $fields, private ResolveEmployeeAttributes $employees, private FormatEmployeeDate $dates, private LetterSystemFields $system) {}

    /**
     * Resolve live choices once. Rendering this snapshot never reads master data again.
     *
     * @param  array{values: array<string, string|list<string>|null>, signers: array<string, string>}  $data
     * @return array{schema_version: int, locale: string, as_of: string, date_format: string, inputs: array, configuration: array, values: array<string, string>, rows: list<array{anchor: string, count: int}>}
     */
    public function resolve(LetterTemplateVersion $version, array $data, CarbonImmutable $asOf, string $number): array
    {
        $version->loadMissing(['variables.employeeAttributes', 'signerSlots.candidates', 'signerSlots.employeeAttributes']);
        $variables = $this->fields->variables($version);
        $slotPrefixes = $this->fields->slots($version);
        [$employees, $references] = $this->choices($variables, $data);
        $signers = array_values($data['signers']);
        if (count($signers) !== count(array_unique($signers))) {
            throw ValidationException::withMessages(['signers' => 'Setiap slot yang digunakan harus diisi pegawai berbeda.']);
        }

        $values = [];
        $totalRows = 0;
        $rows = [];
        foreach ($variables as $variable) {
            $input = $data['values'][$variable->uuid] ?? null;
            $selected = $variable->is_multiple ? ($input ?? []) : (filled($input) ? [$input] : []);
            if ($variable->type === LetterTemplateVariableTypeEnum::COMBOBOX) {
                foreach ($selected as $uuid) {
                    $choice = $variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE ? $employees->get($uuid) : $references->get($uuid);
                    if ($choice === null || ($choice instanceof Employee && $choice->status !== AsnStatusEnum::AKTIF)
                        || ($choice instanceof ReferenceItem && $choice->reference_group_id !== $variable->reference_group_id)) {
                        throw ValidationException::withMessages(['values.' . $variable->uuid => 'Pilihan tidak aktif, tidak tersedia, atau bukan milik sumber variabel ini.']);
                    }
                }
            }
            if ($variable->is_multiple) {
                $totalRows += count($selected);
                if ($totalRows > config('letter-templates.test.max_total_rows')) {
                    throw ValidationException::withMessages(['test' => 'Jumlah keseluruhan baris pilihan melewati batas pengujian.']);
                }
                $anchor = $variable->columns->first(fn(LetterTemplateVariableColumn $column): bool => in_array($column->placeholder, $version->validation_result['placeholders'], true))->placeholder;
                if ($selected === []) {
                    $rows[] = ['anchor' => $anchor, 'count' => 0];
                } else {
                    $rows[] = ['anchor' => $anchor, 'count' => count($selected)];
                    foreach ($selected as $index => $uuid) {
                        $employee = $variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE ? $employees->get($uuid) : null;
                        $employeeValues = $employee === null ? [] : $this->employees->resolve($employee, [], $asOf);
                        foreach ($variable->columns as $column) {
                            $attribute = $column->source_attribute;
                            $value = match ($attribute) {
                                'row_number' => (string) ($index + 1),
                                'content' => $references->get($uuid)->content,
                                default => $employeeValues[$attribute] ?? '',
                            };
                            if ($employee !== null && EmployeeAttributeCatalog::isDate($attribute) && $employee->getAttribute($attribute) !== null) {
                                $value = $this->dates->format($employee->getAttribute($attribute), $column->date_format);
                            }
                            $values[$column->placeholder . '#' . ($index + 1)] = $value;
                        }
                    }
                }
            } elseif ($variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE) {
                $values = [...$values, ...$this->employees->tokens($variable->name, filled($input) ? $employees->get($input) : null, $variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(), $asOf)];
            } else {
                $value = blank($input) ? (string) $variable->default_text : match ($variable->type) {
                    LetterTemplateVariableTypeEnum::DATE => CarbonImmutable::parse($input)->format('d-m-Y'),
                    LetterTemplateVariableTypeEnum::COMBOBOX => $references->get($input)->content,
                    default => $input,
                };
                $values[$variable->name] = $value;
            }

        }
        foreach ($slotPrefixes as $uuid => $prefix) {
            $slot = $version->signerSlots->firstWhere('uuid', $uuid);
            $employee = $employees->get($data['signers'][$uuid] ?? '');
            if ($employee === null || $employee->status !== AsnStatusEnum::AKTIF || !$slot->candidates->contains('employee_id', $employee->id)) {
                throw ValidationException::withMessages(['signers.' . $uuid => 'Pilih kandidat pegawai aktif milik slot ini.']);
            }
            $values = [...$values, ...$this->employees->tokens($prefix, $employee, $slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(), $asOf)];
        }

        $values = [...$values, ...$this->system->values($number, $asOf, $version->letter_date_format ?? 'd-m-Y')];

        return [
            'schema_version' => 1, 'locale' => config('app.locale'), 'as_of' => $asOf->toDateString(),
            'date_format' => $version->letter_date_format ?? 'd-m-Y', 'inputs' => $data,
            'configuration' => [
                'variables' => $variables->map(fn($variable): array => [
                    ...$variable->only(['uuid', 'name', 'type', 'option_source', 'reference_group_id', 'default_text', 'is_multiple']),
                    'attributes' => $variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(),
                    'columns' => $variable->columns->map->only(['source_attribute', 'placeholder', 'date_format'])->all(),
                ])->all(),
                'signers' => $version->signerSlots->map(fn($slot): array => ['uuid' => $slot->uuid, 'attributes' => $slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all()])->all(),
            ],
            'values' => $values, 'rows' => $rows,
        ];
    }

    /**
     * @param  iterable<LetterTemplateVariable>  $variables
     * @param  array{values: array<string, string|list<string>|null>, signers: array<string, string>}  $data
     * @return array{Collection<string, Employee>, Collection<string, ReferenceItem>}
     */
    private function choices(iterable $variables, array $data): array
    {
        $employeeIds = array_values($data['signers']);
        $referenceIds = [];
        $groupIds = [];
        foreach ($variables as $variable) {
            if ($variable->type !== LetterTemplateVariableTypeEnum::COMBOBOX) {
                continue;
            }
            $input = $data['values'][$variable->uuid] ?? null;
            $ids = $variable->is_multiple ? ($input ?? []) : (filled($input) ? [$input] : []);
            if ($variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE) {
                $employeeIds = [...$employeeIds, ...$ids];
            } else {
                $referenceIds = [...$referenceIds, ...$ids];
                $groupIds[] = $variable->reference_group_id;
            }
        }
        $groups = ReferenceGroup::query()->whereIn('id', array_unique($groupIds))->orderBy('id')->sharedLock()->get();
        if ($groups->count() !== count(array_unique($groupIds))) {
            throw ValidationException::withMessages(['test' => 'Grup referensi sudah tidak tersedia. Periksa kembali konfigurasi.']);
        }
        $references = ReferenceItem::query()->whereIn('uuid', array_unique($referenceIds))->orderBy('id')->sharedLock()->get()->keyBy('uuid');
        $employees = Employee::query()->with('jobTitle')->whereIn('uuid', array_unique($employeeIds))->orderBy('id')->sharedLock()->get()->keyBy('uuid');

        return [$employees, $references];
    }
}
