<?php

namespace App\Models\MasterData;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\LetterNumber;
use Database\Factories\LetterTemplateVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(LetterTemplateVersionFactory::class)]
class LetterTemplateVersion extends Model
{
    /** @use HasFactory<LetterTemplateVersionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['letter_template_id', 'version_number', 'letter_number_id', 'status', 'source_disk', 'source_path', 'source_original_name', 'source_size', 'source_sha256', 'validated_source_sha256', 'validated_configuration_sha256', 'validated_at', 'validation_result', 'tested_source_sha256', 'tested_configuration_sha256', 'test_disk', 'test_path', 'tested_at', 'test_expires_at', 'visually_confirmed_at', 'visually_confirmed_by', 'visually_confirmed_by_uuid', 'visually_confirmed_by_name', 'published_at', 'first_used_at'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'status' => LetterTemplateVersionStatusEnum::class,
            'version_number' => 'integer',
            'source_size' => 'integer',
            'validation_result' => 'array',
            'validated_at' => 'datetime',
            'tested_at' => 'datetime',
            'test_expires_at' => 'datetime',
            'visually_confirmed_at' => 'datetime',
            'published_at' => 'datetime',
            'first_used_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LetterTemplate::class, 'letter_template_id');
    }

    public function letterNumber(): BelongsTo
    {
        return $this->belongsTo(LetterNumber::class, 'letter_number_id');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(LetterTemplateVariable::class, 'version_id')->orderBy('sort_order')->orderBy('id');
    }

    public function signerSlots(): HasMany
    {
        return $this->hasMany(LetterTemplateSignerSlot::class, 'version_id')->orderBy('sort_order')->orderBy('id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(LetterTemplateAudit::class, 'version_id');
    }

    public function visuallyConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visually_confirmed_by');
    }
}
