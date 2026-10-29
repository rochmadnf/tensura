<?php

namespace App\Services\LetterTemplate;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CopyLetterTemplateVersion
{
    /**
     * Pemanggil mengunci templat/versi dan membersihkan berkas baru jika transaksi gagal atau diulang.
     *
     * @param  list<array{disk: string, path: string}>  $createdFiles
     */
    public function copy(LetterTemplate $template, LetterTemplateVersion $source, array &$createdFiles): LetterTemplateVersion
    {
        $source->loadMissing(['variables.radioOptions', 'variables.columns', 'variables.employeeAttributes', 'signerSlots.candidates', 'signerSlots.employeeAttributes']);
        $draft = $template->versions()->create([
            'letter_date_format' => $source->letter_date_format,
            'version_number' => $template->versions()->max('version_number') + 1,
            'letter_number_id' => $source->letter_number_id, 'status' => LetterTemplateVersionStatusEnum::DRAFT,
        ]);
        foreach ($source->variables as $variable) {
            $copy = $draft->variables()->create($variable->only(['name', 'label', 'type', 'is_required', 'sort_order', 'option_source', 'reference_group_id', 'is_multiple', 'default_text', 'radio', 'numeric', 'date_range']));
            $copy->radioOptions()->createMany($variable->radioOptions->map->only(['code', 'label', 'sort_order'])->all());
            $copy->columns()->createMany($variable->columns->map->only(['source_attribute', 'placeholder', 'sort_order', 'date_format'])->all());
            $copy->employeeAttributes()->createMany($variable->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all());
        }
        foreach ($source->signerSlots as $slot) {
            $copy = $draft->signerSlots()->create(['sort_order' => $slot->sort_order]);
            $copy->candidates()->createMany($slot->candidates->map->only(['employee_id'])->all());
            $copy->employeeAttributes()->createMany($slot->employeeAttributes->map->only(['source_attribute', 'default_text', 'date_format'])->all());
        }
        if ($source->source_path !== null) {
            if ($source->source_disk === null || $source->source_sha256 === null) {
                throw ValidationException::withMessages(['version' => 'Metadata DOCX sumber tidak lengkap; salinan draft tidak dibuat.']);
            }
            $disk = Storage::disk($source->source_disk);
            if (!$disk->exists($source->source_path) || hash_file('sha256', $disk->path($source->source_path)) !== $source->source_sha256) {
                throw ValidationException::withMessages(['version' => 'DOCX sumber hilang atau berubah; salinan draft tidak dibuat.']);
            }
            $path = config('letter-templates.paths.sources') . '/' . $draft->uuid . '/' . Str::uuid() . '.docx';
            $createdFiles[] = ['disk' => $source->source_disk, 'path' => $path];
            if (!$disk->copy($source->source_path, $path) || hash_file('sha256', $disk->path($path)) !== $source->source_sha256) {
                throw ValidationException::withMessages(['version' => 'DOCX gagal disalin. Silakan coba lagi.']);
            }
            $draft->fill([
                'source_disk' => $source->source_disk, 'source_path' => $path,
                'source_original_name' => $source->source_original_name, 'source_size' => $disk->size($path), 'source_sha256' => $source->source_sha256,
            ])->save();
        }

        return $draft;
    }
}
