<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateAudit;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use InvalidArgumentException;

class RecordLetterTemplateAudit
{
    public function record(LetterTemplate $template, ?LetterTemplateVersion $version, User $actor, string $operation, string $description): LetterTemplateAudit
    {
        if ($version !== null && $version->letter_template_id !== $template->id) {
            throw new InvalidArgumentException('Versi audit harus milik templat yang sama.');
        }

        return $template->audits()->create([
            'version_id' => $version?->id,
            'actor_id' => $actor->id,
            'actor_uuid' => $actor->uuid,
            'actor_name' => $actor->name,
            'occurred_at' => now(),
            'operation' => $operation,
            'description' => $description,
        ]);
    }
}
