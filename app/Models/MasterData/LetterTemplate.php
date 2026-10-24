<?php

namespace App\Models\MasterData;

use App\Enums\LetterTemplateVersionStatusEnum;
use Database\Factories\LetterTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[UseFactory(LetterTemplateFactory::class)]
class LetterTemplate extends Model
{
    /** @use HasFactory<LetterTemplateFactory> */
    use HasFactory, HasUuids;

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
