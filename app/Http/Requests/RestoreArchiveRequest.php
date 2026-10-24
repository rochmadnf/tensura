<?php

namespace App\Http\Requests;

use App\Services\Archive\ArchiveSelection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestoreArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...ArchiveSelection::rules('restore'),
            'action' => ['required', Rule::in(['restore'])],
            'mode' => ['required', Rule::in(ArchiveSelection::PARENT_MODES)],
            'record_uuids' => ['prohibited'],
            'target_group_uuid' => ['prohibited'],
            'confirmation_token' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['action' => 'Aksi arsip', 'mode' => 'Mode pemulihan', 'record_uuids' => 'Pilihan item', 'target_group_uuid' => 'Grup tujuan', 'confirmation_token' => 'Token konfirmasi'];
    }
}
