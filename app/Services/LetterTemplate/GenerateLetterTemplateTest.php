<?php

namespace App\Services\LetterTemplate;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Models\MasterData\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateLetterTemplateTest
{
    public function __construct(
        private LetterTemplateTestEvidence $evidence,
        private LetterTemplateTestFields $fields,
        private ResolveEmployeeAttributes $employees,
        private FormatEmployeeDate $dates,
        private ValidateLetterTemplateDocx $validator,
        private RecordLetterTemplateAudit $audit,
    ) {}

    /** @param array{validation_token: string, values: array<string, string|list<string>|null>, signers: array<string, string>} $data */
    public function generate(LetterTemplate $template, LetterTemplateVersion $version, array $data, User $actor): void
    {
        $created = [];
        try {
            $result = DB::transaction(function () use ($template, $version, $data, $actor, &$created): array {
                $started = microtime(true);
                $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
                $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
                $this->evidence->assertDraft($version);
                $this->evidence->assertValidated($version);
                if (!hash_equals($this->evidence->validationToken($version), $data['validation_token'])) {
                    throw ValidationException::withMessages(['test' => 'DOCX atau konfigurasi berubah sejak formulir dibuka. Muat ulang dan periksa data contoh.']);
                }
                $source = Storage::disk($version->source_disk)->path($version->source_path);
                try {
                    $inspection = $this->validator->inspectPlaceholders($source, $version);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['test' => implode(' ', array_merge(...array_values($exception->errors())))]);
                }
                if (!$inspection['valid']) {
                    throw ValidationException::withMessages(['test' => implode(' ', $inspection['errors'])]);
                }
                $version->loadMissing(['variables.employeeAttributes', 'signerSlots.candidates', 'signerSlots.employeeAttributes']);
                $variables = $this->fields->variables($version);
                $slotPrefixes = $this->fields->slots($version);
                [$employees, $references] = $this->choices($variables, $data);
                $signers = array_values($data['signers']);
                if (count($signers) !== count(array_unique($signers))) {
                    throw ValidationException::withMessages(['signers' => 'Setiap slot yang digunakan harus diisi pegawai berbeda.']);
                }
                $asOf = CarbonImmutable::now(config('app.timezone'))->startOfDay();
                $processor = new LetterTemplateProcessor($source);
                try {
                    $values = [];
                    $totalRows = 0;
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
                            $anchor = $variable->columns->first(fn(LetterTemplateVariableColumn $column): bool => in_array($column->placeholder, $inspection['placeholders'], true))->placeholder;
                            if ($selected === []) {
                                $processor->deleteEmptyTableRow($anchor);
                            } else {
                                $processor->cloneRepeatedRow($anchor, count($selected));
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
                        $this->checkDeadline($started);
                    }
                    foreach ($slotPrefixes as $uuid => $prefix) {
                        $slot = $version->signerSlots->firstWhere('uuid', $uuid);
                        $employee = $employees->get($data['signers'][$uuid] ?? '');
                        if ($employee === null || $employee->status !== AsnStatusEnum::AKTIF || !$slot->candidates->contains('employee_id', $employee->id)) {
                            throw ValidationException::withMessages(['signers.' . $uuid => 'Pilih kandidat pegawai aktif milik slot ini.']);
                        }
                        $values = [...$values, ...$this->employees->tokens($prefix, $employee, $slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(), $asOf)];
                    }
                    $processor->fillLiteralValues($values);
                    $diskName = config('letter-templates.disk');
                    $disk = Storage::disk($diskName);
                    $path = config('letter-templates.paths.previews') . '/' . $version->uuid . '/' . Str::uuid() . '.docx';
                    $disk->makeDirectory(dirname($path));
                    $created[] = ['disk' => $diskName, 'path' => $path];
                    $processor->saveAs($disk->path($path));
                    if ($disk->size($path) > config('letter-templates.test.max_output_bytes')) {
                        throw ValidationException::withMessages(['test' => 'Ukuran hasil uji melewati batas pengujian. Kurangi data pilihan.']);
                    }
                    $this->checkDeadline($started);
                    $this->evidence->assertValidated($version);
                    $old = ['disk' => $version->test_disk, 'path' => $version->test_path];
                    $version->fill([
                        'test_disk' => $diskName, 'test_path' => $path, 'test_sha256' => hash_file('sha256', $disk->path($path)),
                        'test_signer_uuids' => $signers, 'tested_at' => now(), 'test_expires_at' => now()->addHours(config('letter-templates.test.expires_hours')),
                        'tested_source_sha256' => $version->source_sha256, 'tested_configuration_sha256' => $version->validated_configuration_sha256,
                        'visually_confirmed_at' => null, 'visually_confirmed_by' => null, 'visually_confirmed_by_uuid' => null, 'visually_confirmed_by_name' => null,
                    ])->save();
                    $this->audit->record($template, $version, $actor, 'test.generated', 'DOCX hasil uji dibuat dari data contoh; pemeriksaan visual perlu dilakukan ulang.');

                    return ['disk' => $diskName, 'path' => $path, 'old' => $old];
                } finally {
                    unset($processor);
                }
            }, 5);
        } catch (Throwable $exception) {
            foreach ($created as $file) {
                $this->cleanup($file['disk'], $file['path']);
            }
            throw $exception;
        }
        foreach ([...$created, $result['old']] as $file) {
            if ($file['path'] !== null && $file['path'] !== $result['path']) {
                $this->cleanup($file['disk'], $file['path']);
            }
        }
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

    private function checkDeadline(float $started): void
    {
        if (microtime(true) - $started > config('letter-templates.test.timeout_seconds')) {
            throw ValidationException::withMessages(['test' => 'Generate melewati batas waktu. Kurangi data contoh dan coba lagi.']);
        }
    }

    private function cleanup(?string $disk, string $path): void
    {
        if ($disk === null || LetterTemplateVersion::query()->where('test_disk', $disk)->where('test_path', $path)->exists()) {
            return;
        }
        try {
            if (!Storage::disk($disk)->delete($path)) {
                report(new \RuntimeException('Hasil uji sementara gagal dibersihkan.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
