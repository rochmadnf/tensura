<?php

namespace App\Services\Letter;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\LetterQuestionConfiguration;
use App\Services\LetterTemplate\LetterTemplateTestFields;

class LetterFormDefinition
{
    public function __construct(private LetterTemplateTestFields $fields) {}

    public function make(LetterTemplateVersion $version, array $snapshot = []): array
    {
        $employees = $snapshot['masters']['employees'] ?? [];
        $references = $snapshot['masters']['references'] ?? [];
        $employeeLabels = collect($employees)->map(fn(array $stored): string => trim(implode(' ', array_filter([$stored['attributes']['front_title'] ?? '', $stored['attributes']['name'] ?? '', $stored['attributes']['back_title'] ?? ''], fn(?string $part): bool => filled($part)))))->all();

        return [
            'version' => $version->uuid, 'number' => $version->version_number,
            'date_format' => $version->letter_date_format,
            'classification' => $version->letterNumber->classification_code,
            'variables' => $this->fields->variables($version)->map(fn(LetterTemplateVariable $variable): array => [
                ...app(LetterQuestionConfiguration::class)->read($variable),
                'id' => $variable->uuid, 'name' => $variable->name, 'label' => $variable->label, 'type' => $variable->type->value, 'required' => $variable->is_required, 'multiple' => $variable->is_multiple,
                'options' => collect($variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE ? $employeeLabels : array_map(fn(array $stored): string => $stored['content'], $references))->map(fn(string $label, string $id): array => ['value' => $id, 'label' => $label])->values()->all(),
            ])->all(),
            'signers' => collect($this->fields->slots($version))->map(fn(string $prefix, string $id): array => ['id' => $id, 'label' => str_replace('_', ' ', ucfirst($prefix)), 'options' => collect($employeeLabels)->map(fn(string $label, string $id): array => ['value' => $id, 'label' => $label])->values()->all()])->values()->all(),
        ];
    }
}
