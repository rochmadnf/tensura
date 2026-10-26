<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentFontRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['font' => ['required', 'file', 'extensions:ttf,otf', 'max:' . config('document-fonts.max_upload_kb')]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['font' => 'Berkas Font'];
    }
}
