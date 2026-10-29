<?php

namespace App\Services\LetterTemplate;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChangeLetterTemplateConfigurationItem
{
    public function __construct(private LetterTemplateConfigurationRules $rules, private RecordLetterTemplateAudit $audit, private InvalidateLetterTemplateEvidence $evidence) {}

    /** @param array<string, mixed> $data */
    public function change(LetterTemplate $template, LetterTemplateVersion $version, string $kind, string $operation, ?string $uuid, ?string $attribute, array $data, User $actor): void
    {
        DB::transaction(function () use ($template, $version, $kind, $operation, $uuid, $attribute, $data, $actor): void {
            $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $before = $this->audit->snapshot($template, $version);
            if ($version->status !== LetterTemplateVersionStatusEnum::DRAFT || $version->first_used_at !== null) {
                throw ValidationException::withMessages(['configuration' => 'Konfigurasi hanya dapat diubah pada draft yang belum digunakan.']);
            }
            $version->load(['variables.radioOptions', 'variables.columns', 'variables.employeeAttributes', 'variables.referenceGroup', 'signerSlots.candidates.employee', 'signerSlots.employeeAttributes']);
            $models = $kind === 'variable' ? $version->variables : $version->signerSlots;
            $owner = $uuid === null ? null : $models->firstWhere('uuid', $uuid);
            abort_if($uuid !== null && $owner === null, 404);
            $key = $kind === 'variable' ? 'variables' : 'signer_slots';
            $snapshot = $this->snapshot($version);
            $index = $owner === null ? count($snapshot[$key]) : $models->search(fn(LetterTemplateVariable|LetterTemplateSignerSlot $model): bool => $model->uuid === $uuid);

            if ($operation === 'attribute') {
                abort_unless(in_array($attribute, array_column(EmployeeAttributeCatalog::options(), 'id'), true), 404);
                if ($kind === 'variable' && ($owner->option_source !== LetterTemplateOptionSourceEnum::EMPLOYEE || $owner->is_multiple)) {
                    throw ValidationException::withMessages(['configuration' => 'Pengaturan atribut hanya berlaku untuk Pegawai tunggal.']);
                }
                if (filled($data['date_format']) && !EmployeeAttributeCatalog::isDate($attribute)) {
                    throw ValidationException::withMessages(['date_format' => 'Format tanggal hanya berlaku untuk atribut tanggal Pegawai.']);
                }
                $settings = $snapshot[$key][$index]['employee_attributes'];
                $settings = array_values(array_filter($settings, fn(array $setting): bool => $setting['source_attribute'] !== $attribute));
                $settings[] = ['source_attribute' => $attribute, ...$data];
                $snapshot[$key][$index]['employee_attributes'] = $settings;
            } elseif ($operation === 'delete') {
                array_splice($snapshot[$key], $index, 1);
            } elseif ($operation === 'move') {
                $neighbor = $index + ($data['direction'] === 'up' ? -1 : 1);
                if (!isset($snapshot[$key][$neighbor])) {
                    return;
                }
                [$snapshot[$key][$index], $snapshot[$key][$neighbor]] = [$snapshot[$key][$neighbor], $snapshot[$key][$index]];
            } else {
                if (isset($data['item']['id']) && $data['item']['id'] !== $uuid) {
                    throw ValidationException::withMessages(['item.id' => 'Identitas item tidak sesuai dengan item yang diubah.']);
                }
                $item = $data['item'];
                $item['id'] = $uuid;
                $existing = $snapshot[$key][$index] ?? null;
                $sameSource = $kind === 'slot' || ($existing !== null && (int) $existing['type'] === (int) $item['type'] && (int) ($existing['option_source'] ?? 0) === (int) ($item['option_source'] ?? 0) && (bool) $existing['is_multiple'] === (bool) $item['is_multiple']);
                $item['employee_attributes'] = $sameSource ? ($existing['employee_attributes'] ?? []) : [];

                if ($kind === 'variable') {
                    foreach ($item['columns'] as $columnIndex => $column) {
                        if (isset($column['id']) && !($owner?->columns->contains('uuid', $column['id']) ?? false)) {
                            throw ValidationException::withMessages(["item.columns.{$columnIndex}.id" => 'Kolom bukan milik variabel ini.']);
                        }
                    }
                }
                $snapshot[$key][$index] = $item;
            }
            $this->validateSnapshot($snapshot, $key, $index, $operation);

            if ($operation === 'attribute') {
                $settings = $this->settings($snapshot[$key][$index]['employee_attributes']);
                $setting = collect($settings)->firstWhere('source_attribute', $attribute);
                if ($setting === null) {
                    $owner->employeeAttributes()->where('source_attribute', $attribute)->delete();
                } else {
                    $owner->employeeAttributes()->updateOrCreate(['source_attribute' => $attribute], $setting);
                }
            } elseif ($operation === 'delete') {
                $owner->employeeAttributes()->delete();
                if ($kind === 'variable') {
                    $owner->columns()->delete();
                } else {
                    $owner->candidates()->delete();
                }
                $owner->delete();
            } elseif ($operation === 'save') {
                if ($kind === 'variable') {
                    $this->saveVariable($version, $owner, $snapshot[$key][$index], $index);
                } else {
                    $employees = Employee::query()->whereIn('uuid', $data['item']['candidates'])->orderBy('id')->sharedLock()->get()->keyBy('uuid');
                    foreach ($data['item']['candidates'] as $candidateIndex => $candidate) {
                        if ($employees->get($candidate)?->status !== AsnStatusEnum::AKTIF) {
                            throw ValidationException::withMessages(["item.candidates.{$candidateIndex}" => 'Kandidat penandatangan harus pegawai aktif yang masih tersedia.']);
                        }
                    }
                    $slot = $owner ?? $version->signerSlots()->create(['sort_order' => $index + 1]);
                    $slot->candidates()->delete();
                    foreach ($data['item']['candidates'] as $candidate) {
                        $slot->candidates()->create(['employee_id' => $employees[$candidate]->id]);
                    }
                }
            }
            if (in_array($operation, ['move', 'delete'], true)) {
                foreach ($snapshot[$key] as $position => $item) {
                    $models->firstWhere('uuid', $item['id'])->update(['sort_order' => $position + ($kind === 'slot' ? 1 : 0)]);
                }
            }
            $this->evidence->invalidate($version);
            $version->save();
            $this->audit->record($template, $version, $actor, 'configuration.updated', match ($operation) {
                'delete' => $kind === 'variable' ? 'Pertanyaan dihapus.' : 'Slot penandatangan dihapus.',
                'move' => 'Urutan pertanyaan diubah.',
                'attribute' => 'Teks alternatif atau format atribut Pegawai diubah.',
                default => $kind === 'variable' ? ($owner === null ? 'Pertanyaan ditambahkan.' : 'Pertanyaan diubah.') : ($owner === null ? 'Slot penandatangan ditambahkan.' : 'Kandidat penandatangan diubah.'),
            }, $before);
        }, 5);
    }

    /** @return array{variables: list<array<string, mixed>>, signer_slots: list<array<string, mixed>>} */
    private function snapshot(LetterTemplateVersion $version): array
    {
        return [
            'variables' => $version->variables->map(fn(LetterTemplateVariable $variable): array => [
                ...app(LetterQuestionConfiguration::class)->read($variable),
                'id' => $variable->uuid, ...$variable->only(['name', 'label', 'is_required', 'is_multiple', 'default_text']),
                'type' => $variable->type->value, 'option_source' => $variable->option_source?->value, 'reference_group' => $variable->referenceGroup?->uuid,
                'employee_attributes' => $variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(),
                'columns' => $variable->columns->map(fn(LetterTemplateVariableColumn $column): array => ['id' => $column->uuid, ...$column->only(['source_attribute', 'placeholder', 'date_format'])])->all(),
            ])->all(),
            'signer_slots' => $version->signerSlots->map(fn(LetterTemplateSignerSlot $slot): array => [
                'id' => $slot->uuid, 'candidates' => $slot->candidates->map(fn(LetterTemplateSignerCandidate $candidate): string => $candidate->employee->uuid)->all(),
                'employee_attributes' => $slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all(),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function validateSnapshot(array $snapshot, string $key, int $index, string $operation): void
    {
        $validator = Validator::make($snapshot, $this->rules->rules(), $this->rules->messages(), $this->rules->attributes());
        foreach ($this->rules->after($snapshot) as $callback) {
            $validator->after($callback);
        }
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $path => $messages) {
                $target = str_starts_with($path, "{$key}.{$index}.") && $operation === 'save' ? 'item.' . substr($path, strlen("{$key}.{$index}.")) : 'configuration';
                $errors[$target] = array_merge($errors[$target] ?? [], $messages);
            }
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $item */
    private function saveVariable(LetterTemplateVersion $version, ?LetterTemplateVariable $owner, array $item, int $index): void
    {
        $group = isset($item['reference_group']) ? ReferenceGroup::query()->where('uuid', $item['reference_group'])->sharedLock()->first() : null;
        if (isset($item['reference_group']) && $group === null) {
            throw ValidationException::withMessages(['item.reference_group' => 'Grup referensi sudah tidak tersedia.']);
        }
        $sameSource = $owner !== null && $owner->type->value === (int) $item['type'] && (int) ($owner->option_source?->value ?? 0) === (int) ($item['option_source'] ?? 0) && $owner->is_multiple === (bool) $item['is_multiple'];
        $employeeSingle = (int) ($item['option_source'] ?? 0) === LetterTemplateOptionSourceEnum::EMPLOYEE->value && !$item['is_multiple'];
        $variable = $owner ?? $version->variables()->make();
        $variable->fill([
            ...array_intersect_key($item, array_flip(['name', 'label', 'type', 'is_required', 'option_source', 'is_multiple', 'default_text'])),
            'reference_group_id' => $group?->id, 'sort_order' => $index,
            'default_text' => $item['is_multiple'] || $employeeSingle ? null : (array_key_exists('default_text', $item) ? $item['default_text'] : ($sameSource ? $owner?->default_text : null)),
        ])->save();
        app(LetterQuestionConfiguration::class)->save($variable, $item, $owner);
        $variable->columns()->delete();
        foreach ($item['columns'] as $position => $column) {
            $model = $variable->columns()->make([...array_intersect_key($column, array_flip(['source_attribute', 'placeholder', 'date_format'])), 'sort_order' => $position]);
            if (isset($column['id'])) {
                $model->uuid = $column['id'];
            }
            $model->save();
        }
        if (!$sameSource) {
            $variable->employeeAttributes()->delete();
        }
    }

    /** @param list<array<string, mixed>> $settings
     * @return list<array<string, mixed>>
     */
    private function settings(array $settings): array
    {
        return array_values(array_filter(EmployeeAttributeCatalog::settings($settings), fn(array $setting): bool => filled($setting['default_text']) || ($setting['date_format'] !== null && $setting['date_format'] !== 'd-m-Y')));
    }
}
