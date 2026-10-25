<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLetterTemplateDocxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['docx' => ['bail', 'required', 'file', 'extensions:docx', 'max:' . config('letter-templates.max_upload_kb')]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['docx' => 'DOCX Sumber'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['docx.extensions' => 'Berkas harus menggunakan ekstensi .docx.', 'docx.max' => 'Ukuran DOCX melebihi batas upload.', 'docx.uploaded' => 'DOCX gagal diunggah; periksa ukuran berkas dan batas upload server.'];
    }
}
