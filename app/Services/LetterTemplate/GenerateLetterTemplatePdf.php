<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\ConvertLetterPdf;
use App\Services\Letter\DocumentFontRegistry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateLetterTemplatePdf
{
    public function __construct(private ConvertLetterPdf $converter, private DocumentFontRegistry $fonts, private LetterTemplateTestEvidence $evidence) {}

    public function generate(LetterTemplateVersion $version, string $token): string
    {
        $disk = Storage::disk(config('letters.disk'));
        $directory = config('letters.paths.staging') . '/template-' . Str::uuid();
        $path = null;
        try {
            $this->evidence->assertCurrent($version, $token);
            $profile = $this->fonts->conversionProfile($version->test_font_requirements['requirements'] ?? []);
            $output = $this->converter->convertSource($directory, Storage::disk($version->test_disk)->get($version->test_path), $version->test_sha256, true, $profile);
            $this->evidence->assertCurrent($version->fresh(), $token);
            if (!hash_equals($profile['fingerprint'], $this->fonts->conversionProfile($version->test_font_requirements['requirements'] ?? [])['fingerprint'])) {
                throw ValidationException::withMessages(['pdf' => 'Profil font berubah. Buat PDF kembali.']);
            }
            $contents = $disk->get($output['path']);
            $path = tempnam(sys_get_temp_dir(), 'tensura-template-pdf-');
            if ($path === false || file_put_contents($path, $contents) === false) {
                if (is_string($path) && is_file($path)) {
                    unlink($path);
                }
                throw new \RuntimeException('PDF sementara gagal disimpan.');
            }

            return $path;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['pdf' => 'PDF hasil uji belum dapat dibuat. Periksa konverter dan font, lalu coba lagi. DOCX tetap dapat diunduh.']);
        } finally {
            $disk->deleteDirectory($directory);
        }
    }
}
