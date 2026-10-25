<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Validation\ValidationException;

class LetterTemplateVersionState
{
    public function __construct(private LetterTemplateConfigurationHash $configurationHash) {}

    public function token(LetterTemplateVersion $version): string
    {
        return hash('sha256', json_encode([
            $version->uuid, $version->status->value, $version->published_at?->toIso8601String(), $version->first_used_at?->toIso8601String(),
            $version->source_disk, $version->source_path, $version->source_sha256,
            $version->validated_at?->toIso8601String(), $version->validated_source_sha256, $version->validated_configuration_sha256,
            $version->test_path, $version->test_sha256, $version->test_expires_at?->toIso8601String(),
            $version->visually_confirmed_at?->toIso8601String(), $this->configurationHash->hash($version),
        ], JSON_THROW_ON_ERROR));
    }

    public function assertCurrent(LetterTemplateVersion $version, string $token): void
    {
        if (!hash_equals($this->token($version), $token)) {
            throw ValidationException::withMessages(['version' => 'Versi atau bukti pemeriksaannya berubah sejak halaman dibuka. Muat ulang sebelum melanjutkan.']);
        }
    }
}
