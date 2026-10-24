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
     *     gender: array{id: int, label: string},
     *     job_title: array{id: ?string, name: ?string, group: array{id: ?int, label: ?string}}|MissingValue,
     *     rank: ?string, grade: ?string, email: ?string,
     *     join_date: ?string, join_date_input: ?string, tenure_years: ?int,
     *     str: array{number: string, status: array{id: ?int, label: ?string}, published_date: ?string, published_date_input: ?string}|null,
     *     sip: array{number: string, published_date: ?string, published_date_input: ?string, expired_date: ?string, expired_date_input: ?string}|null,
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
            'gender' => [
                'id' => $this->gender,
                'label' => collect(Employee::genderOptions())->firstWhere('id', $this->gender)['label'],
            ],
            'job_title' => $this->when($this->relationLoaded('jobTitle'), function (): array {
                return [
                    'id' => $this->jobTitle?->uuid,
                    'name' => $this->jobTitle?->name,
                    'group' => [
                        'id' => $this->jobTitle?->group?->value,
                        'label' => $this->jobTitle?->group?->label(),
                    ],
                ];
            }),
            'rank' => $this->rank,
            'grade' => $this->grade,
            'email' => $this->email,
            'join_date' => $this->join_date?->format('d-m-Y'),
            'join_date_input' => $this->join_date?->toDateString(),
            'tenure_years' => $this->join_date === null ? null : max(0, $this->join_date->diff(today())->y),
            'str' => blank($this->str_number)
                ? null
                : [
                    'number' => $this->str_number,
                    'status' => ['id' => $this->str_status?->value, 'label' => $this->str_status?->label()],
                    'published_date' => $this->str_published_date?->format('d-m-Y'),
                    'published_date_input' => $this->str_published_date?->toDateString(),
                ],
            'sip' => blank($this->sip_number)
                ? null
                : [
                    'number' => $this->sip_number,
                    'published_date' => $this->sip_published_date?->format('d-m-Y'),
                    'published_date_input' => $this->sip_published_date?->toDateString(),
                    'expired_date' => $this->sip_expired_date?->format('d-m-Y'),
                    'expired_date_input' => $this->sip_expired_date?->toDateString(),
                ],
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
