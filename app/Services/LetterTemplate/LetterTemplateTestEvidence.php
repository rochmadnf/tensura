<?php

namespace App\Services\LetterTemplate;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\DocumentFontRegistry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LetterTemplateTestEvidence
{
    public function __construct(private LetterTemplateConfigurationHash $configurationHash) {}

    public function validationToken(LetterTemplateVersion $version): string
    {
        return hash('sha256', $version->uuid . '|' . $version->source_sha256 . '|' . $this->configurationHash->hash($version));
    }

    public function validated(LetterTemplateVersion $version): bool
    {
        return $version->validated_at !== null && ($version->validation_result['valid'] ?? false)
            && $version->source_path !== null && $version->source_sha256 !== null
            && $version->validated_source_sha256 === $version->source_sha256
            && $version->validated_configuration_sha256 === $this->configurationHash->hash($version);
    }

    public function assertDraft(LetterTemplateVersion $version): void
    {
        if ($version->status !== LetterTemplateVersionStatusEnum::DRAFT || $version->first_used_at !== null) {
            throw ValidationException::withMessages(['test' => 'Pengujian hanya dapat dijalankan pada draft yang belum digunakan.']);
        }
    }

    public function assertValidated(LetterTemplateVersion $version): void
    {
        if (!$this->validated($version)) {
            throw ValidationException::withMessages(['test' => 'Validasi ulang DOCX dan konfigurasi sebelum generate hasil uji.']);
        }
        $disk = Storage::disk($version->source_disk);
        if (!$disk->exists($version->source_path) || hash_file('sha256', $disk->path($version->source_path)) !== $version->source_sha256) {
            throw ValidationException::withMessages(['test' => 'DOCX sumber hilang atau berubah. Unggah dan validasi ulang.']);
        }
    }

    public function token(LetterTemplateVersion $version): ?string
    {
        return $version->test_path === null || $version->test_sha256 === null ? null : hash('sha256', $version->uuid . '|' . $version->test_path . '|' . $version->test_sha256);
    }

    public function state(LetterTemplateVersion $version): string
    {
        if ($version->test_path === null || $version->tested_at === null) {
            return 'missing';
        }
        if (!$this->validated($version) || $version->tested_source_sha256 !== $version->source_sha256 || $version->tested_configuration_sha256 !== $version->validated_configuration_sha256) {
            return 'stale';
        }
        if ($version->test_expires_at === null || $version->test_expires_at->lessThanOrEqualTo(now())) {
            return 'expired';
        }
        if ($version->test_disk === null || $version->test_sha256 === null) {
            return 'stale';
        }
        $disk = Storage::disk($version->test_disk);
        if (!$disk->exists($version->test_path) || $disk->size($version->test_path) > config('letter-templates.test.max_output_bytes') || hash_file('sha256', $disk->path($version->test_path)) !== $version->test_sha256) {
            return 'stale';
        }

        if ($version->test_font_fingerprint !== null && (($version->test_font_requirements['schema_version'] ?? null) !== 1 || !hash_equals($version->test_font_fingerprint, app(DocumentFontRegistry::class)->fingerprint($version->test_font_requirements['requirements'] ?? [])))) {
            return 'stale';
        }

        $signers = Employee::query()->whereIn('uuid', $version->test_signer_uuids ?? [])->orderBy('id')->sharedLock()->get();
        if ($signers->count() !== count($version->test_signer_uuids ?? []) || $signers->contains(fn(Employee $employee): bool => $employee->status !== AsnStatusEnum::AKTIF)) {
            return 'stale';
        }

        return $version->visually_confirmed_at === null ? 'ready' : 'confirmed';
    }

    public function assertCurrent(LetterTemplateVersion $version, string $token): void
    {
        $this->assertValidated($version);
        if (!in_array($this->state($version), ['ready', 'confirmed'], true) || !hash_equals($this->token($version) ?? '', $token)) {
            throw ValidationException::withMessages(['test' => 'Hasil uji hilang, berubah, kedaluwarsa, sudah diganti, atau font terkait diperbarui. Generate ulang dan periksa hasil terbaru.']);
        }
    }
}
