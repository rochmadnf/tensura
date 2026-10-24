<?php

namespace App\Models\MasterData;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\Searchable;
use Database\Factories\LetterTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

#[UseFactory(LetterTemplateFactory::class)]
class LetterTemplate extends Model
{
    /** @use HasFactory<LetterTemplateFactory> */
    use HasFactory, HasUuids, Searchable;

    protected $fillable = ['name', 'is_active', 'active_version_id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return non-empty-list<string> */
    protected function searchableColumns(): array
    {
        return ['name', 'displayVersion.letterNumber.classification_code', 'displayVersion.letterNumber.classification_name'];
    }

    public function draftVersion(): HasOne
    {
        return $this->hasOne(LetterTemplateVersion::class, 'letter_template_id')
            ->ofMany(['version_number' => 'max', 'id' => 'max'], function (Builder $query): void {
                $query->where('status', LetterTemplateVersionStatusEnum::DRAFT);
            });
    }

    /** Versi rujukan yang sama untuk kolom daftar, pencarian, dan filter klasifikasi. */
    public function displayVersion(): HasOne
    {
        $selectedVersion = LetterTemplateVersion::query()
            ->from('letter_template_versions', 'display_versions')
            ->select('display_versions.id')
            ->whereColumn('display_versions.letter_template_id', 'letter_template_versions.letter_template_id')
            ->orderByRaw(
                'CASE WHEN display_versions.id = (SELECT active_version_id FROM letter_templates WHERE id = display_versions.letter_template_id) THEN 0 WHEN display_versions.status = ? THEN 1 ELSE 2 END',
                [LetterTemplateVersionStatusEnum::DRAFT->value],
            )
            ->orderByDesc('display_versions.version_number')
            ->orderByDesc('display_versions.id')
            ->limit(1);

        return $this->hasOne(LetterTemplateVersion::class, 'letter_template_id')
            ->where('letter_template_versions.id', $selectedVersion);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LetterTemplateVersion::class, 'letter_template_id');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(LetterTemplateVersion::class, 'active_version_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(LetterTemplateAudit::class, 'letter_template_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            if ($template->active_version_id !== null && $template->isDirty('active_version_id')) {
                $valid = $template->versions()->whereKey($template->active_version_id)
                    ->where('status', LetterTemplateVersionStatusEnum::PUBLISHED)->exists();
                if (!$valid) {
                    throw ValidationException::withMessages(['active_version_id' => 'Versi aktif harus merupakan versi terbit milik templat ini.']);
                }
            }
        });
    }
}
