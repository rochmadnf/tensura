<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\LetterSystemFields;
use App\Services\Letter\RenderLetterDocument;
use App\Services\Letter\ResolveLetterDocumentSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateLetterTemplateTest
{
    public function __construct(
        private LetterTemplateTestEvidence $evidence,
        private ResolveLetterDocumentSnapshot $snapshots,
        private RenderLetterDocument $renderer,
        private LetterSystemFields $system,
        private ValidateLetterTemplateDocx $validator,
        private RecordLetterTemplateAudit $audit,
        private DocumentFontRegistry $fonts,
    ) {}

    /** @param array{validation_token: string, values: array<string, string|list<string>|null>, signers: array<string, string>} $data */
    public function generate(LetterTemplate $template, LetterTemplateVersion $version, array $data, User $actor): void
    {
        $created = [];
        try {
            $result = DB::transaction(function () use ($template, $version, $data, $actor, &$created): array {
                $started = microtime(true);
                $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
                $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
                $before = $this->audit->snapshot($template, $version);
                $this->evidence->assertDraft($version);
                $this->evidence->assertValidated($version);
                if (!hash_equals($this->evidence->validationToken($version), $data['validation_token'])) {
                    throw ValidationException::withMessages(['test' => 'DOCX atau konfigurasi berubah sejak formulir dibuka. Muat ulang dan periksa data contoh.']);
                }
                $source = Storage::disk($version->source_disk)->path($version->source_path);
                try {
                    $inspection = $this->validator->inspectPlaceholders($source, $version);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['test' => implode(' ', array_merge(...array_values($exception->errors())))]);
                }
                if (!$inspection['valid']) {
                    throw ValidationException::withMessages(['test' => implode(' ', $inspection['errors'])]);
                }
                $asOf = CarbonImmutable::now(config('app.timezone'))->startOfDay();
                $snapshot = $this->snapshots->resolve($version, $data, $asOf, $this->system->exampleNumber($version->letterNumber->classification_code, $asOf));
                $signers = array_values($data['signers']);
                $diskName = config('letter-templates.disk');
                $disk = Storage::disk($diskName);
                $path = config('letter-templates.paths.previews') . '/' . $version->uuid . '/' . Str::uuid() . '.docx';
                $disk->makeDirectory(dirname($path));
                $created[] = ['disk' => $diskName, 'path' => $path];
                $fontUsage = $this->renderer->render($source, $snapshot, $disk->path($path));
                $requirements = $fontUsage['requirements'];
                $fontFingerprint = $this->fonts->fingerprint($requirements);
                if ($disk->size($path) > config('letter-templates.test.max_output_bytes')) {
                    throw ValidationException::withMessages(['test' => 'Ukuran hasil uji melewati batas pengujian. Kurangi data pilihan.']);
                }
                $this->checkDeadline($started);
                $this->evidence->assertValidated($version);
                $old = ['disk' => $version->test_disk, 'path' => $version->test_path];
                $version->fill([
                    'test_disk' => $diskName, 'test_path' => $path, 'test_sha256' => hash_file('sha256', $disk->path($path)),
                    'test_font_requirements' => ['schema_version' => 1, ...$fontUsage], 'test_font_fingerprint' => $fontFingerprint,
                    'test_signer_uuids' => $signers, 'tested_at' => now(), 'test_expires_at' => now()->addHours(config('letter-templates.test.expires_hours')),
                    'tested_source_sha256' => $version->source_sha256, 'tested_configuration_sha256' => $version->validated_configuration_sha256,
                    'visually_confirmed_at' => null, 'visually_confirmed_by' => null, 'visually_confirmed_by_uuid' => null, 'visually_confirmed_by_name' => null,
                ])->save();
                $this->audit->record($template, $version, $actor, 'test.generated', 'DOCX hasil uji dibuat dari data contoh; pemeriksaan visual perlu dilakukan ulang.', $before);

                return ['disk' => $diskName, 'path' => $path, 'old' => $old];
            }, 5);
        } catch (Throwable $exception) {
            foreach ($created as $file) {
                $this->cleanup($file['disk'], $file['path']);
            }
            throw $exception;
        }
        foreach ([...$created, $result['old']] as $file) {
            if ($file['path'] !== null && $file['path'] !== $result['path']) {
                $this->cleanup($file['disk'], $file['path']);
            }
        }
    }

    private function checkDeadline(float $started): void
    {
        if (microtime(true) - $started > config('letter-templates.test.timeout_seconds')) {
            throw ValidationException::withMessages(['test' => 'Generate melewati batas waktu. Kurangi data contoh dan coba lagi.']);
        }
    }

    private function cleanup(?string $disk, string $path): void
    {
        if ($disk === null || LetterTemplateVersion::query()->where('test_disk', $disk)->where('test_path', $path)->exists()) {
            return;
        }
        try {
            if (!Storage::disk($disk)->delete($path)) {
                report(new \RuntimeException('Hasil uji sementara gagal dibersihkan.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
