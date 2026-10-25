<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;

class LetterTemplateConfigurationHash
{
    public function hash(LetterTemplateVersion $version): string
    {
        $version->loadMissing(['template', 'variables.columns', 'signerSlots.candidates']);
        $data = [
            'name' => $version->template->name, 'letter_number_id' => $version->letter_number_id,
            'variables' => $version->variables->map(fn(LetterTemplateVariable $variable): array => [
                ...$variable->only(['name', 'label', 'type', 'is_required', 'option_source', 'reference_group_id', 'is_multiple']),
                'columns' => $variable->columns->map->only(['source_attribute', 'placeholder'])->all(),
            ])->all(),
            'slots' => $version->signerSlots->map(fn(LetterTemplateSignerSlot $slot): array => $slot->candidates->pluck('employee_id')->sort()->values()->all())->all(),
        ];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
