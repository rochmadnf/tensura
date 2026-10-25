<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Database\Eloquent\Collection;

class LetterTemplateTestFields
{
    /** @return Collection<int, LetterTemplateVariable> */
    public function variables(LetterTemplateVersion $version): Collection
    {
        $version->loadMissing('variables.columns');
        $found = $version->validation_result['placeholders'] ?? [];

        return $version->variables->filter(function (LetterTemplateVariable $variable) use ($found): bool {
            $names = $variable->is_multiple ? $variable->columns->pluck('placeholder')->all()
                : ($variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE ? array_keys(EmployeeAttributeCatalog::tokens($variable->name)) : [$variable->name]);

            return array_intersect($found, $names) !== [];
        })->values();
    }

    /** @return array<string, string> Slot UUID => token prefix. */
    public function slots(LetterTemplateVersion $version): array
    {
        $version->loadMissing('signerSlots');
        $found = $version->validation_result['placeholders'] ?? [];
        $slots = [];
        foreach ($version->signerSlots as $index => $slot) {
            $prefix = $version->signerSlots->count() === 1 ? 'penandatangan' : 'penandatangan_' . ($index + 1);
            if (array_intersect($found, array_keys(EmployeeAttributeCatalog::tokens($prefix))) !== []) {
                $slots[$slot->uuid] = $prefix;
            }
        }

        return $slots;
    }
}
