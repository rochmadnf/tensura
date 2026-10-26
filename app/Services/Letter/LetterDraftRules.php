<?php

namespace App\Services\Letter;

use App\Enums\LetterNumberingModeEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\LetterTemplateTestFields;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LetterDraftRules
{
    public function __construct(private LetterTemplateTestFields $fields) {}

    /** @return array<string, array<mixed>> */
    public function identity(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F]/'],
            'template_version' => ['required', 'uuid'],
            'letter_date' => ['required', 'date_format:Y-m-d', 'not_regex:/^0000-/'],
            'numbering_mode' => ['required', 'integer', Rule::enum(LetterNumberingModeEnum::class)],
            'number_year' => ['required', 'integer', 'between:1,9999'],
            'number_month' => ['required', 'integer', 'between:1,12'],
            'page_number' => ['exclude_unless:numbering_mode,2', 'required', 'integer', 'between:1,2147483647'],
            'sequence_number' => ['exclude_unless:numbering_mode,2', 'required', 'integer', 'between:1,99'],
            'values' => ['present', 'array'], 'signers' => ['present', 'array'],
        ];
    }

    /** @return array<string, array<mixed>> */
    public function fields(LetterTemplateVersion $version): array
    {
        $variables = $this->fields->variables($version);
        $slots = $this->fields->slots($version);
        $rules = ['values' => ['present', $variables->isEmpty() ? 'array' : 'array:' . $variables->pluck('uuid')->implode(',')], 'signers' => ['present', $slots === [] ? 'array' : 'array:' . implode(',', array_keys($slots))]];
        if ($variables->isEmpty()) {
            $rules['values'][] = 'max:0';
        }
        if ($slots === []) {
            $rules['signers'][] = 'max:0';
        }
        foreach ($variables as $variable) {
            $path = 'values.' . $variable->uuid;
            $rules[$path] = [$variable->is_required ? 'required' : 'nullable'];
            if ($variable->is_multiple) {
                $rules[$path] = [...$rules[$path], 'array', 'list', 'max:' . config('letter-templates.test.max_rows')];
                if ($variable->is_required) {
                    $rules[$path][] = 'min:1';
                }
                $rules[$path . '.*'] = ['required', 'uuid', 'distinct'];
            } elseif ($variable->type === LetterTemplateVariableTypeEnum::COMBOBOX) {
                $rules[$path][] = 'uuid';
            } elseif ($variable->type === LetterTemplateVariableTypeEnum::DATE) {
                $rules[$path][] = 'date_format:Y-m-d';
            } else {
                $rules[$path] = [...$rules[$path], 'string', 'max:' . config('letter-templates.test.' . ($variable->type === LetterTemplateVariableTypeEnum::LONG_TEXT ? 'long_text_max' : 'short_text_max')), 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F]/'];
            }
        }
        foreach ($slots as $uuid => $prefix) {
            $rules['signers.' . $uuid] = ['required', 'uuid', 'distinct'];
        }

        return $rules;
    }

    public function attributes(?LetterTemplateVersion $version = null): array
    {
        $labels = ['subject' => 'Perihal', 'template' => 'Templat', 'template_version' => 'Versi Templat', 'letter_date' => 'Tanggal Surat', 'numbering_mode' => 'Mode Nomor', 'number_year' => 'Tahun Nomor', 'number_month' => 'Bulan Nomor', 'page_number' => 'Halaman', 'sequence_number' => 'Nomor Urut', 'values' => 'Isian Surat', 'signers' => 'Penandatangan', 'revision' => 'Versi Draft'];
        if ($version !== null) {
            foreach ($this->fields->variables($version) as $variable) {
                $labels['values.' . $variable->uuid] = $variable->label;
            }
            foreach ($this->fields->slots($version) as $uuid => $prefix) {
                $labels['signers.' . $uuid] = str_replace('_', ' ', ucfirst($prefix));
            }
        }

        return $labels;
    }

    public function checkPeriod(Validator $validator, array $data): void
    {
        if ($validator->errors()->hasAny(['letter_date', 'number_year', 'number_month'])) {
            return;
        }
        $date = CarbonImmutable::parse($data['letter_date']);
        if ($date->year !== (int) $data['number_year'] || $date->month !== (int) $data['number_month']) {
            $validator->errors()->add('number_month', 'Bulan dan tahun nomor harus sama dengan tanggal surat. Sesuaikan tanggal atau periode nomor.');
        }
    }
}
