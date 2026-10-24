<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'avatar' => ['bail', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['avatar' => 'Foto Profil'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'avatar.image' => 'Foto Profil harus berupa gambar.',
            'avatar.mimes' => 'Foto Profil harus berformat JPG, JPEG, PNG, atau WebP.',
            'avatar.max' => 'Foto Profil maksimal 2 MB.',
            'avatar.dimensions' => 'Dimensi Foto Profil maksimal 4096 × 4096 piksel.',
            'avatar.uploaded' => 'Foto Profil gagal diunggah.',
        ];
    }
}
