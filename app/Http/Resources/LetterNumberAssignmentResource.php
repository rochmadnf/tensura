<?php

namespace App\Http\Resources;

use App\Models\LetterNumberAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LetterNumberAssignment */
class LetterNumberAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->uuid, 'letter_id' => $this->letter->uuid, 'subject' => $this->versions->first()?->subject ?? $this->letter->subject, 'number' => $this->versions->first()?->number ?? sprintf('%02d.%02d', $this->page_number, $this->sequence_number), 'mode' => $this->mode === 1 ? 'Otomatis' : ($this->mode === 2 ? 'Manual' : 'Pemulihan'), 'claimed_at' => $this->claimed_at->toIso8601String(), 'active' => $this->released_at === null, 'status' => $this->letter->status->label()];
    }
}
