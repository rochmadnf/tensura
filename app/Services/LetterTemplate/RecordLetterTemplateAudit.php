<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateAudit;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use InvalidArgumentException;

class RecordLetterTemplateAudit
{
    /** @return array<string, array{label: string, value: ?string}> */
    public function snapshot(LetterTemplate $template, LetterTemplateVersion $version): array
    {
        return app(LetterTemplateAuditChanges::class)->snapshot($template, $version);
    }

    /**
     * @param  ?array<string, array{label: string, value: ?string}>  $before
     * @param  ?array{valid: bool, source_name: ?string, errors: list<string>, placeholders: list<string>}  $validationDetails
     */
    public function record(LetterTemplate $template, ?LetterTemplateVersion $version, User $actor, string $operation, string $description, ?array $before = null, ?array $validationDetails = null): LetterTemplateAudit
    {
        if ($version !== null && $version->letter_template_id !== $template->id) {
            throw new InvalidArgumentException('Versi audit harus milik templat yang sama.');
        }

        $after = $before === null || $version === null ? null : $this->snapshot($template, $version);
        if ($operation === 'draft.copied' && $before !== null && $after !== null) {
            $metadata = fn(string $key): bool => !str_starts_with($key, 'variable.') && !str_starts_with($key, 'slot.');
            $before = array_filter($before, $metadata, ARRAY_FILTER_USE_KEY);
            $after = array_filter($after, $metadata, ARRAY_FILTER_USE_KEY);
        }

        return $template->audits()->create([
            'version_id' => $version?->id,
            'actor_id' => $actor->id,
            'actor_uuid' => $actor->uuid,
            'actor_name' => $actor->name,
            'occurred_at' => now(),
            'operation' => $operation,
            'description' => $description,
            'validation_details' => $validationDetails,
            'changes' => $after === null ? null : app(LetterTemplateAuditChanges::class)->diff($before, $after),
        ]);
    }
}
