<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\LetterNumber;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveLetterTemplateIdentity
{
    public function __construct(private RecordLetterTemplateAudit $audit) {}

    /** @param array{name: string, letter_number: string} $data */
    public function create(array $data, User $actor): LetterTemplate
    {
        return DB::transaction(function () use ($data, $actor): LetterTemplate {
            $classification = $this->classification($data['letter_number']);
            $template = LetterTemplate::create(['name' => $data['name']]);
            $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->create([
                'version_number' => 1,
                'letter_number_id' => $classification->id,
                'status' => LetterTemplateVersionStatusEnum::DRAFT,
            ]);
            $this->audit->record($template, $version, $actor, 'draft.created', 'Identitas dan draft awal dibuat.');

            return $template;
        }, 5);
    }

    /** @param array{name: string, letter_number: string} $data */
    public function update(LetterTemplate $template, array $data, User $actor): void
    {
        DB::transaction(function () use ($template, $data, $actor): void {
            $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $version = $template->versions()->where('status', LetterTemplateVersionStatusEnum::DRAFT)->lockForUpdate()->first();
            if ($version === null || $version->first_used_at !== null) {
                throw ValidationException::withMessages(['name' => 'Identitas hanya dapat diubah pada draft yang belum digunakan.']);
            }

            $classification = $this->classification($data['letter_number']);
            $template->fill(['name' => $data['name']]);
            $version->fill(['letter_number_id' => $classification->id]);
            if (!$template->isDirty() && !$version->isDirty()) {
                return;
            }

            $this->invalidateEvidence($version);
            $template->save();
            $version->save();
            $this->audit->record($template, $version, $actor, 'identity.updated', 'Identitas draft diperbarui; bukti pemeriksaan dibatalkan.');
        }, 5);
    }

    private function classification(string $uuid): LetterNumber
    {
        $classification = LetterNumber::query()->where('uuid', $uuid)->sharedLock()->first();
        if ($classification === null) {
            throw ValidationException::withMessages(['letter_number' => 'Klasifikasi yang dipilih tidak valid.']);
        }

        return $classification;
    }

    private function invalidateEvidence(LetterTemplateVersion $version): void
    {
        $version->fill(array_fill_keys([
            'validated_source_sha256', 'validated_configuration_sha256', 'validated_at', 'validation_result',
            'tested_source_sha256', 'tested_configuration_sha256', 'test_disk', 'test_path', 'tested_at', 'test_expires_at',
            'visually_confirmed_at', 'visually_confirmed_by', 'visually_confirmed_by_uuid', 'visually_confirmed_by_name',
        ], null));
    }
}
