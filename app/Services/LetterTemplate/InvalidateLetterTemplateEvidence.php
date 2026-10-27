<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplateVersion;

class InvalidateLetterTemplateEvidence
{
    public function invalidate(LetterTemplateVersion $version): void
    {
        $version->fill(array_fill_keys([
            'validated_source_sha256', 'validated_configuration_sha256', 'validated_at', 'validation_result',
            'test_font_requirements', 'test_font_fingerprint', 'tested_source_sha256', 'tested_configuration_sha256', 'test_disk', 'test_path', 'test_sha256', 'test_signer_uuids', 'tested_at', 'test_expires_at',
            'visually_confirmed_at', 'visually_confirmed_by', 'visually_confirmed_by_uuid', 'visually_confirmed_by_name',
        ], null));
    }
}
