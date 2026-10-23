<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string, nik: string, nip: ?string,
     *     name: array{first: ?string, raw: string, full: string, last: ?string},
     *     job_title: array{id: ?string, name: ?string}|MissingValue,
     *     rank: ?string, grade: ?string, email: ?string,
     *     join_date: ?string, join_date_input: ?string, tenure_years: ?int,
     *     contract_status: array{id: ?int, label: ?string}, status: array{id: ?int, label: ?string}
     * }
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
            'job_title' => $this->when($this->relationLoaded('jobTitle'), function (): array {
                return [
                    'id' => $this->jobTitle?->uuid,
                    'name' => $this->jobTitle?->name,
                ];
            }),
            'rank' => $this->rank,
            'grade' => $this->grade,
            'email' => $this->email,
            'join_date' => $this->join_date?->format('d-m-Y'),
            'join_date_input' => $this->join_date?->toDateString(),
            'tenure_years' => $this->join_date === null ? null : max(0, $this->join_date->diff(today())->y),
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
