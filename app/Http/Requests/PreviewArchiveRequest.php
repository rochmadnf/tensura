<?php

namespace App\Http\Requests;

use App\Services\Archive\ArchiveSelection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ArchiveSelection::rules(is_string($this->input('action')) ? $this->input('action') : null);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['action' => 'Aksi arsip', 'mode' => 'Mode pemulihan', 'record_uuids' => 'Pilihan item', 'record_uuids.*' => 'UUID item arsip', 'target_group_uuid' => 'Grup tujuan'];
    }
}
