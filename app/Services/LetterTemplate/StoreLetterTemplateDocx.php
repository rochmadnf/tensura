<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreLetterTemplateDocx
{
    public function __construct(private InspectLetterTemplateDocx $inspector, private InvalidateLetterTemplateEvidence $evidence, private RecordLetterTemplateAudit $audit) {}

    public function store(LetterTemplate $template, LetterTemplateVersion $version, UploadedFile $file, User $actor): void
    {
        $this->inspector->read($file->getPathname());
        $diskName = config('letter-templates.disk');
        $disk = Storage::disk($diskName);
        $path = $file->storeAs(config('letter-templates.paths.sources') . '/' . $version->uuid, Str::uuid() . '.docx', $diskName);
        if ($path === false) {
            throw ValidationException::withMessages(['docx' => 'DOCX gagal disimpan. Silakan coba lagi.']);
        }
        try {
            $old = DB::transaction(function () use ($template, $version, $file, $actor, $path, $diskName): array {
                $template = LetterTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
                $version = $template->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
                if ($version->status !== LetterTemplateVersionStatusEnum::DRAFT || $version->first_used_at !== null) {
                    throw ValidationException::withMessages(['docx' => 'DOCX hanya dapat diganti pada draft yang belum digunakan.']);
                }
                $old = ['disk' => $version->source_disk, 'path' => $version->source_path];
                $this->evidence->invalidate($version);
                $version->fill([
                    'source_disk' => $diskName, 'source_path' => $path,
                    'source_original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 255),
                    'source_size' => $file->getSize(), 'source_sha256' => hash_file('sha256', $file->getPathname()),
                ])->save();
                $this->audit->record($template, $version, $actor, 'source.uploaded', 'DOCX sumber disimpan; bukti pemeriksaan dibatalkan.');

                return $old;
            }, 5);
        } catch (Throwable $exception) {
            try {
                if (!$disk->delete($path)) {
                    report(new RuntimeException('Berkas DOCX yang gagal disimpan ke database tidak dapat dibersihkan.'));
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }
            throw $exception;
        }
        if ($old['path'] !== null && $old['disk'] !== null && !LetterTemplateVersion::query()->where('source_disk', $old['disk'])->where('source_path', $old['path'])->exists()) {
            try {
                if (!Storage::disk($old['disk'])->delete($old['path'])) {
                    report(new RuntimeException('Berkas DOCX draft lama gagal dihapus.'));
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
