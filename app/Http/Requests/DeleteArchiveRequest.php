<?php

namespace App\Http\Requests;

use App\Services\Archive\ArchiveSelection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...ArchiveSelection::rules('delete_forever'),
            'action' => ['required', Rule::in(['delete_forever'])],
            'confirmation_token' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['action' => 'Aksi arsip', 'mode' => 'Cakupan penghapusan', 'record_uuids' => 'Pilihan item', 'confirmation_token' => 'Token konfirmasi'];
    }
}
