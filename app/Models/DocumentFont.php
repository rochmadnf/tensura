<?php

namespace App\Models;

use App\Models\MasterData\User;
use Database\Factories\DocumentFontFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(DocumentFontFactory::class)]
class DocumentFont extends Model
{
    /** @use HasFactory<DocumentFontFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['family', 'style', 'face_key', 'is_bold', 'is_italic', 'sha256', 'status', 'disk', 'path', 'extension', 'size', 'uploaded_by'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['is_bold' => 'boolean', 'is_italic' => 'boolean', 'size' => 'integer'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
