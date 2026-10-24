<?php

namespace App\Http\Requests;

use App\Services\Archive\RenameArchivedReferenceGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateArchivedReferenceGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return RenameArchivedReferenceGroup::rules();
    }

    /** @return array{name: string} */
    public function attributes(): array
    {
        return ['name' => 'Nama Grup'];
    }
}
