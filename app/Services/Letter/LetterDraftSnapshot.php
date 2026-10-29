<?php

namespace App\Services\Letter;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\LetterTemplateTestFields;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

class LetterDraftSnapshot
{
    public function __construct(private LetterDraftRules $rules, private ResolveLetterDocumentSnapshot $resolver, private LetterSystemFields $system, private LetterTemplateTestFields $fields) {}

    /** @param array{values: array, signers: array} $data */
    public function build(LetterTemplateVersion $version, array $data, CarbonImmutable $date, array $previous = [], bool $allowIncomplete = false): array
    {
        $employeeIds = array_values(array_filter($data['signers']));
        $referenceIds = [];
        foreach ($this->fields->variables($version) as $variable) {
            $selected = (array) ($data['values'][$variable->uuid] ?? []);
            if ($variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE) {
                $employeeIds = [...$employeeIds, ...$selected];
            } elseif ($variable->option_source === LetterTemplateOptionSourceEnum::REFERENCE) {
                $referenceIds = [...$referenceIds, ...$selected];
            }
        }
        $previous['masters'] = [
            'employees' => array_intersect_key($previous['masters']['employees'] ?? [], array_fill_keys($employeeIds, true)),
            'references' => array_intersect_key($previous['masters']['references'] ?? [], array_fill_keys($referenceIds, true)),
        ];
        $validation = Validator::make($data, $this->rules->fields($version), [], $this->rules->attributes($version));
        if ($allowIncomplete && $validation->fails()) {
            return ['schema_version' => 1, 'complete' => false, 'as_of' => $date->toDateString(), 'inputs' => $data, 'masters' => $previous['masters'] ?? [], 'values' => [], 'rows' => [], 'missing' => $validation->errors()->all()];
        }
        $validation->validate();

        return ['complete' => true, ...$this->resolver->resolve($version, $data, $date, $this->system->exampleNumber($version->letterNumber->classification_code, $date), $previous['masters'] ?? [], $previous['question_results'] ?? [])];
    }
}
