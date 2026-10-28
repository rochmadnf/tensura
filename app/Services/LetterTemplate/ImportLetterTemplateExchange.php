<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\LetterNumber;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\InstallDocumentFont;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportLetterTemplateExchange
{
    public function __construct(
        private LetterTemplateExchangeStage $stages,
        private LetterTemplateExchangeConfiguration $configuration,
        private SaveLetterTemplateConfiguration $saveConfiguration,
        private LetterTemplateConfigurationRules $rules,
        private LetterTemplateVersionState $state,
        private InvalidateLetterTemplateEvidence $evidence,
        private RecordLetterTemplateAudit $audit,
        private InstallDocumentFont $installer,
        private DocumentFontRegistry $registry,
    ) {}

    /** @return list<string> */
    public function fontConflicts(array $manifest, array $files): array
    {
        $errors = [];
        $fonts = array_filter($files, fn(string $name): bool => str_starts_with($name, 'fonts/'), ARRAY_FILTER_USE_KEY);
        if ($fonts === []) {
            return [];
        }
        $inventory = $this->registry->inventory(true);
        if (!$inventory['available']) {
            return ['Daftar font tujuan belum dapat diperiksa.'];
        }
        foreach ($fonts as $name => $path) {
            $face = $this->installer->inspectFile($path, $name);
            $key = $this->registry->faceKey($face['family'], $face['style']);
            foreach ($inventory['faces'] as $installed) {
                if ($this->registry->faceKey($installed['family'], $installed['style']) === $key && hash_file('sha256', $installed['path']) !== $manifest['files'][$name]['sha256']) {
                    $errors[] = 'Konflik font ' . $face['family'] . ' ' . $face['style'] . ': isi berkas tujuan berbeda.';
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /** @param array<string, mixed> $data */
    public function save(string $token, array $data, User $actor): LetterTemplateVersion
    {
        return $this->stages->use($token, $actor, function (array $manifest, array $files) use ($token, $data, $actor): LetterTemplateVersion {
            $errors = $this->configuration->incompatibilities($manifest['configuration'], $manifest['employee_contract']);
            if ($errors !== []) {
                throw ValidationException::withMessages(['package' => $errors]);
            }
            $configuration = $this->mappedConfiguration($manifest['configuration'], $data);
            $validator = Validator::make($configuration, $this->rules->rules(), $this->rules->messages(), $this->rules->attributes());
            foreach ($this->rules->after($configuration) as $after) {
                $validator->after($after);
            }
            $configuration = $validator->validate();
            $createdFiles = [];
            $oldSource = null;
            try {
                $fontFiles = [];
                foreach ($files as $name => $path) {
                    if (str_starts_with($name, 'fonts/')) {
                        $fontFiles[] = ['path' => $path, 'filename' => $name];
                    }
                }
                $version = $this->installer->atomic($fontFiles, $actor, function () use ($manifest, $files, $configuration, $data, $actor, &$createdFiles, &$oldSource): LetterTemplateVersion {
                    $conflicts = $this->fontConflicts($manifest, $files);
                    if ($conflicts !== []) {
                        throw ValidationException::withMessages(['font' => $conflicts]);
                    }

                    return DB::transaction(function () use ($manifest, $files, $configuration, $data, $actor, &$createdFiles, &$oldSource): LetterTemplateVersion {
                        $template = filled($data['target'] ?? null) ? LetterTemplate::query()->where('uuid', $data['target'])->lockForUpdate()->firstOrFail() : LetterTemplate::query()->create(['name' => $data['name']]);
                        $version = $template->versions()->where('status', LetterTemplateVersionStatusEnum::DRAFT)->lockForUpdate()->first();
                        $before = $version === null ? [] : $this->audit->snapshot($template, $version);
                        if ($version !== null) {
                            if (!($data['replace_draft'] ?? false) || blank($data['version_token'] ?? null)) {
                                throw ValidationException::withMessages(['replace_draft' => 'Konfirmasikan penggantian draft yang ada terlebih dahulu.']);
                            }
                            $this->state->assertCurrent($version, $data['version_token']);
                            if ($version->first_used_at !== null) {
                                throw ValidationException::withMessages(['version' => 'Draft yang sudah digunakan tidak dapat diganti.']);
                            }
                            $oldSource = ['disk' => $version->source_disk, 'path' => $version->source_path];
                        } else {
                            if (filled($data['version_token'] ?? null)) {
                                throw ValidationException::withMessages(['version' => 'Draft tujuan berubah sejak pemeriksaan. Pilih ulang tujuan impor.']);
                            }
                            $version = $template->versions()->make(['version_number' => ($template->versions()->max('version_number') ?? 0) + 1, 'status' => LetterTemplateVersionStatusEnum::DRAFT]);
                        }
                        $number = LetterNumber::query()->where('uuid', $data['letter_number'])->sharedLock()->first();
                        if ($number === null) {
                            throw ValidationException::withMessages(['letter_number' => 'Klasifikasi tujuan sudah tidak tersedia.']);
                        }
                        $this->evidence->invalidate($version);
                        $version->fill([
                            'letter_number_id' => $number->id, 'letter_date_format' => $manifest['template']['letter_date_format'],
                            'source_disk' => null, 'source_path' => null, 'source_original_name' => null, 'source_size' => null, 'source_sha256' => null,
                        ])->save();
                        $this->saveConfiguration->save($template, $version, $configuration, $actor);
                        if (isset($files['source.docx'])) {
                            $diskName = config('letter-templates.disk');
                            $path = config('letter-templates.paths.sources') . '/' . $version->uuid . '/' . Str::uuid() . '.docx';
                            $createdFiles[] = ['disk' => $diskName, 'path' => $path];
                            $bytes = file_get_contents($files['source.docx']);
                            if (!Storage::disk($diskName)->put($path, $bytes, 'private')) {
                                throw ValidationException::withMessages(['package' => 'DOCX impor gagal disimpan.']);
                            }
                            $version->fill(['source_disk' => $diskName, 'source_path' => $path, 'source_original_name' => 'source.docx', 'source_size' => strlen($bytes), 'source_sha256' => hash('sha256', $bytes)])->save();
                        }
                        $this->audit->record($template, $version, $actor, 'exchange.imported', 'Paket templat diimpor sebagai draft; validasi dan pemeriksaan lokal wajib dilakukan.', $before);

                        return $version;
                    });
                });
            } catch (Throwable $exception) {
                foreach ($createdFiles as $file) {
                    Storage::disk($file['disk'])->delete($file['path']);
                }
                throw $exception;
            }
            if ($oldSource !== null && $oldSource['disk'] !== null && $oldSource['path'] !== null && !LetterTemplateVersion::query()->where('source_disk', $oldSource['disk'])->where('source_path', $oldSource['path'])->exists()) {
                Storage::disk($oldSource['disk'])->delete($oldSource['path']);
            }
            $this->stages->delete($token);

            return $version;
        });
    }

    private function mappedConfiguration(array $configuration, array $data): array
    {
        foreach ($configuration['variables'] as &$variable) {
            $variable['reference_group'] = ($variable['option_source'] ?? null) === LetterTemplateOptionSourceEnum::REFERENCE->value ? ($data['reference_groups'][$variable['name']] ?? null) : null;
        }
        unset($variable);
        foreach ($configuration['signer_slots'] as $index => &$slot) {
            $slot['candidates'] = $data['signers'][$index] ?? [];
        }

        return $configuration;
    }
}
