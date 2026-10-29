<?php

namespace App\Services\Letter;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Services\LetterTemplate\EmployeeAttributeCatalog;
use App\Services\LetterTemplate\FormatEmployeeDate;
use App\Services\LetterTemplate\LetterQuestionConfiguration;
use App\Services\LetterTemplate\LetterTemplateTestFields;
use App\Services\LetterTemplate\ResolveEmployeeAttributes;
use App\Services\WorkCalendar\CalendarFailure;
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
     * @param  array{employees?: array<string, array{attributes: array, job_title: ?array}>, references?: array<string, array{content: string}>}  $frozenMasters
     * @return array{schema_version: int, locale: string, as_of: string, date_format: string, inputs: array, configuration: array, values: array<string, string>, rows: list<array{anchor: string, count: int}>, masters: array{employees: array, references: array}}
     */
    public function resolve(LetterTemplateVersion $version, array $data, CarbonImmutable $asOf, string $number, array $frozenMasters = [], array $frozenQuestions = [], bool $allowMissingCalendar = false): array
    {
        $version->loadMissing(['variables.employeeAttributes', 'signerSlots.candidates', 'signerSlots.employeeAttributes']);
        $variables = $this->fields->variables($version);
        $slotPrefixes = $this->fields->slots($version);
        [$employees, $references] = $this->choices($variables, $data);
        $signers = array_values($data['signers']);
        if (count($signers) !== count(array_unique($signers))) {
            throw ValidationException::withMessages(['signers' => 'Setiap slot yang digunakan harus diisi pegawai berbeda.']);
        }

        $masters = ['employees' => [], 'references' => []];
        $values = [];
        $questions = [];
        $missingCalendar = [];
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
                    if ($choice instanceof ReferenceItem) {
                        $this->reference($choice, $frozenMasters, $masters);
                    }
                }
            }
            if (in_array($variable->type->value, [5, 6, 7, 8], true)) {
                try {
                    $definition = app(LetterQuestionConfiguration::class)->read($variable);
                    $previous = $frozenQuestions[$variable->uuid] ?? null;
                    $resolved = $previous !== null && $previous['input'] === $input && $previous['config'] === $definition ? $previous['result'] : app(ResolveLetterQuestion::class)->resolve($variable, $input);
                    $questions[$variable->uuid] = ['input' => $input, 'config' => $definition, 'timezone' => config('app.timezone'), 'result' => $resolved];
                } catch (CalendarFailure $exception) {
                    if (in_array($exception->reason, ['calendar_year_missing', 'calendar_not_ready'], true)) {
                        if (!$allowMissingCalendar) {
                            throw $exception;
                        }
                        $missingCalendar[] = $variable->label . ': ' . $exception->getMessage();

                        continue;
                    }
                    throw ValidationException::withMessages(['values.' . $variable->uuid => $exception->getMessage()]);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['values.' . $variable->uuid => collect($exception->errors())->flatten()->all()]);
                }
                $values = [...$values, ...$resolved['tokens']];
                $data['values'][$variable->uuid] = $resolved['value'];
            } elseif ($variable->is_multiple) {
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
                        $employee = $variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE ? $this->employee($employees->get($uuid), $frozenMasters, $masters) : null;
                        $employeeValues = $employee === null ? [] : $this->employees->resolve($employee, [], $asOf);
                        foreach ($variable->columns as $column) {
                            $attribute = $column->source_attribute;
                            $value = match ($attribute) {
                                'row_number' => (string) ($index + 1),
                                'content' => $this->reference($references->get($uuid), $frozenMasters, $masters),
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
                $values = [...$values, ...$this->employees->tokens($variable->name, filled($input) ? $this->employee($employees->get($input), $frozenMasters, $masters) : null, $variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(), $asOf)];
            } else {
                $value = blank($input) ? (string) $variable->default_text : match ($variable->type) {
                    LetterTemplateVariableTypeEnum::DATE => CarbonImmutable::parse($input)->format('d-m-Y'),
                    LetterTemplateVariableTypeEnum::COMBOBOX => $this->reference($references->get($input), $frozenMasters, $masters),
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
            $values = [...$values, ...$this->employees->tokens($prefix, $this->employee($employee, $frozenMasters, $masters), $slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(), $asOf)];
        }

        $values = [...$values, ...$this->system->values($number, $asOf, $version->letter_date_format ?? 'd-m-Y')];

        return [
            'complete' => $missingCalendar === [], 'calendar_missing' => $missingCalendar !== [], 'missing' => $missingCalendar,
            'schema_version' => 1, 'locale' => config('app.locale'), 'as_of' => $asOf->toDateString(),
            'date_format' => $version->letter_date_format ?? 'd-m-Y', 'inputs' => $data,
            'configuration' => [
                'variables' => $variables->map(fn($variable): array => [
                    ...($variable->type->value >= 5 ? app(LetterQuestionConfiguration::class)->read($variable) : []),
                    ...$variable->only(['uuid', 'name', 'type', 'option_source', 'reference_group_id', 'default_text', 'is_multiple']),
                    'attributes' => $variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(),
                    'columns' => $variable->columns->map->only(['source_attribute', 'placeholder', 'date_format'])->all(),
                ])->all(),
                'signers' => $version->signerSlots->map(fn($slot): array => ['uuid' => $slot->uuid, 'attributes' => $slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all()])->all(),
            ],
            'question_results' => $questions, 'values' => $values, 'rows' => $rows, 'masters' => $masters,
        ];
    }

    /** @param array{employees?: array, references?: array} $frozenMasters */
    private function employee(Employee $employee, array $frozenMasters, array &$masters): Employee
    {
        $stored = $frozenMasters['employees'][$employee->uuid] ?? null;
        if ($stored === null) {
            $attributes = array_intersect_key($employee->getAttributes(), array_flip([...array_column(EmployeeAttributeCatalog::options(), 'id'), 'uuid', 'front_title', 'back_title', 'name']));
            $stored = ['attributes' => $attributes, 'job_title' => $employee->jobTitle?->only(['name', 'group'])];
        }
        $masters['employees'][$employee->uuid] = $stored;
        $resolved = (new Employee())->newFromBuilder($stored['attributes']);
        $resolved->setRelation('jobTitle', $stored['job_title'] === null ? null : (new JobTitle())->newFromBuilder($stored['job_title']));

        return $resolved;
    }

    private function reference(ReferenceItem $reference, array $frozenMasters, array &$masters): string
    {
        $stored = $frozenMasters['references'][$reference->uuid] ?? ['content' => $reference->content];
        $masters['references'][$reference->uuid] = $stored;

        return $stored['content'];
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
