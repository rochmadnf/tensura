<?php

namespace App\Http\Resources\MasterData;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'nik' => $this->nik,
            'nip' => $this->nip,
            'name' => [
                'first' => $this->front_title,
                'raw' => $this->name,
                'full' =>
                    (is_null($this->front_title) ? '' : "{$this->front_title} ") .
                    "{$this->name}" .
                    (is_null($this->back_title) ? '' : " {$this->back_title}"),
                'last' => $this->back_title,
            ],
            'job_title' => $this->whenLoaded('jobTitle', function () {
                return [
                    'id' => $this->jobTitle?->uuid,
                    'name' => $this->jobTitle?->name,
                ];
            }),
            'rank' => $this->rank,
            'grade' => $this->grade,
            'email' => $this->email,
            'join_date' => $this->join_date?->format('d-m-Y'),
            'contract_status' => [
                'id' => $this->contract_status?->value,
                'label' => $this->contract_status?->label(),
            ],
            'status' => [
                'id' => $this->status?->value,
                'label' => $this->status?->label(),
            ],
        ];
    }
}
