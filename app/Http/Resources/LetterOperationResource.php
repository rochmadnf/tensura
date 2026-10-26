<?php

namespace App\Http\Resources;

use App\Models\LetterOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LetterOperation */
class LetterOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->uuid, 'letter_id' => $this->letter->uuid, 'subject' => $this->letter->subject, 'kind' => $this->kind, 'revision' => $this->draft_revision, 'status' => strtolower($this->status->name), 'status_label' => $this->status->label(), 'status_revision' => $this->status_revision, 'message' => $this->message, 'updated_at' => $this->updated_at->toIso8601String()];
    }
}
