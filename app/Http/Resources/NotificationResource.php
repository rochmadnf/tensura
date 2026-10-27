<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->data['title'],
            'subject' => $this->data['subject'],
            'message' => $this->data['message'],
            'letter_id' => $this->data['letter_id'],
            'operation_id' => $this->data['operation_id'],
            'status' => $this->data['status'],
            'created_at' => $this->created_at->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
        ];
    }
}
