<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Http\Resources\MasterData\LetterTemplateConfigurationResource;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;

class LetterTemplateAuditChanges
{
    /** @return array<string, array{label: string, value: ?string}> */
    public function snapshot(LetterTemplate $template, LetterTemplateVersion $version): array
    {
        $template = $template->fresh(['activeVersion']);
        $version = $version->fresh(['letterNumber', 'variables.referenceGroup', 'variables.columns', 'variables.employeeAttributes', 'signerSlots.candidates.employee', 'signerSlots.employeeAttributes']);
        $fields = [];
        $put = function (string $key, string $label, mixed $value) use (&$fields): void {
            $fields[$key] = ['label' => $label, 'value' => $value === null || $value === '' ? null : (string) $value];
        };
        $put('name', 'Nama Templat', $template->name);
        $put('active', 'Status Templat', $template->is_active ? 'Aktif' : 'Nonaktif');
        $put('active_version', 'Versi terbit aktif', $template->activeVersion?->version_number);
        $put('version', 'Nomor versi', $version->version_number);
        $put('status', 'Status versi', $version->status->label());
        $put('classification', 'Klasifikasi', $version->letterNumber->classification_code . ' — ' . $version->letterNumber->classification_name);
        $put('source.name', 'Nama DOCX Sumber', $version->source_original_name);
        $put('source.hash', 'Sidik DOCX Sumber', $version->source_sha256);
        $put('validation', 'Validasi DOCX', $version->validated_at?->toIso8601String());
        $put('test', 'Generate uji DOCX', $version->tested_at?->toIso8601String());
        $put('confirmation', 'Pemeriksaan visual', $version->visually_confirmed_at?->toIso8601String());
        foreach ($version->variables as $position => $variable) {
            $key = 'variable.' . $variable->uuid;
            $label = 'Pertanyaan “' . $variable->label . '”';
            foreach ([
                'name' => ['Nama Variabel', $variable->name], 'label' => ['Pertanyaan', $variable->label],
                'order' => ['Nomor urut', $position + 1], 'type' => ['Jenis Pertanyaan', $variable->type->label()],
                'required' => ['Wajib diisi', $variable->is_required ? 'Ya' : 'Tidak'],
                'source' => ['Sumber pilihan', $variable->option_source?->label()],
                'group' => ['Grup referensi', $variable->referenceGroup?->name],
                'multiple' => ['Pilihan lebih dari satu', $variable->is_multiple ? 'Ya' : 'Tidak'],
                'fallback' => ['Teks Alternatif', $variable->default_text],
            ] as $field => [$title, $value]) {
                $put("{$key}.{$field}", "{$label} · {$title}", $value);
            }
            foreach ($variable->columns as $columnPosition => $column) {
                $columnKey = "{$key}.column.{$column->placeholder}";
                $columnLabel = "{$label} · Kolom {$column->placeholder}";
                $put("{$columnKey}.order", "{$columnLabel} · Nomor urut", $columnPosition + 1);
                $put("{$columnKey}.source", "{$columnLabel} · Atribut sumber", $this->attributeLabel($column->source_attribute));
                $put("{$columnKey}.date", "{$columnLabel} · Format tanggal", $column->date_format);
            }
            if ($variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE && !$variable->is_multiple) {
                $this->attributes($fields, $key, $label, $variable->employeeAttributes);
            }
        }
        foreach ($version->signerSlots as $slot) {
            $key = 'slot.' . $slot->uuid;
            $label = 'Penandatangan Slot ' . $slot->sort_order;
            $put("{$key}.order", "{$label} · Nomor urut", $slot->sort_order);
            $names = $slot->candidates->map(fn($candidate): string => LetterTemplateConfigurationResource::employeeName($candidate->employee))->sort()->values()->all();
            $put("{$key}.candidates", "{$label} · Kandidat Penandatangan", implode(', ', $names));
            $this->attributes($fields, $key, $label, $slot->employeeAttributes);
        }

        return $fields;
    }

    /** @param array<string, array{label: string, value: ?string}> $fields */
    private function attributes(array &$fields, string $key, string $label, iterable $settings): void
    {
        $settings = EmployeeAttributeCatalog::settings(collect($settings)->map->only(['source_attribute', 'default_text', 'date_format'])->all());
        foreach ($settings as $setting) {
            $setting = (object) $setting;
            $attribute = $this->attributeLabel($setting->source_attribute);
            foreach (['default_text' => 'Teks Alternatif', 'date_format' => 'Format tanggal'] as $field => $title) {
                $fields["{$key}.attribute.{$setting->source_attribute}.{$field}"] = [
                    'label' => "{$label} · {$attribute} · {$title}",
                    'value' => $setting->$field === null || $setting->$field === '' ? null : $setting->$field,
                ];
            }
        }
    }

    private function attributeLabel(string $attribute): string
    {
        return collect(EmployeeAttributeCatalog::options())->firstWhere('id', $attribute)['label'] ?? match ($attribute) {
            'row_number' => 'Nomor urut', 'content' => 'Isi referensi', default => $attribute,
        };
    }

    /**
     * @param  array<string, array{label: string, value: ?string}>  $before
     * @param  array<string, array{label: string, value: ?string}>  $after
     * @return list<array{field: string, label: string, before: ?string, after: ?string}>
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key]['value'] ?? null;
            $new = $after[$key]['value'] ?? null;
            if ($old !== $new) {
                $changes[] = ['field' => $key, 'label' => $after[$key]['label'] ?? $before[$key]['label'], 'before' => $old, 'after' => $new];
            }
        }

        return $changes;
    }
}
