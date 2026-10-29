<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;

class LetterTemplateConfigurationHash
{
    public function hash(LetterTemplateVersion $version): string
    {
        $version->loadMissing(['template', 'variables.radioOptions', 'variables.columns', 'variables.employeeAttributes', 'signerSlots.candidates', 'signerSlots.employeeAttributes']);
        $data = [
            'letter_date_format' => $version->letter_date_format ?? 'd-m-Y', 'system_fields' => 1,
            'contract' => EmployeeAttributeCatalog::REVISION, 'locale' => config('app.locale'),
            'name' => $version->template->name, 'letter_number_id' => $version->letter_number_id,
            'variables' => $version->variables->map(fn(LetterTemplateVariable $variable): array => [
                ...($variable->type->value >= 5 ? app(LetterQuestionConfiguration::class)->read($variable) : []),
                ...$variable->only(['name', 'label', 'type', 'is_required', 'option_source', 'reference_group_id', 'is_multiple', 'default_text']),
                'employee_attributes' => EmployeeAttributeCatalog::settings($variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all()),
                'columns' => $variable->columns->map->only(['source_attribute', 'placeholder', 'date_format'])->all(),
            ])->all(),
            'slots' => $version->signerSlots->map(fn(LetterTemplateSignerSlot $slot): array => ['candidates' => $slot->candidates->pluck('employee_id')->sort()->values()->all(), 'employee_attributes' => EmployeeAttributeCatalog::settings($slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all())])->all(),
        ];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
