<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class ReorderReferenceItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array{ids: string, 'ids.*': string} */
    public function attributes(): array
    {
        return ['ids' => 'Urutan Item', 'ids.*' => 'UUID Item'];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'ids' => ['bail', 'required', 'array', 'list'],
            'ids.*' => ['bail', 'required', 'string', 'uuid', 'distinct:strict'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (!$validator->errors()->isEmpty()) {
                return;
            }

            $this->assertCurrentItems();
        }];
    }

    public function assertCurrentItems(): void
    {
        $expected = $this->route('referenceGroup')->items()->pluck('uuid')->sort()->values()->all();
        $provided = collect($this->input('ids'))->sort()->values()->all();

        if ($provided !== $expected) {
            throw ValidationException::withMessages([
                'ids' => 'Daftar item sudah berubah karena pengguna lain. Muat ulang data lalu coba urutkan kembali.',
            ]);
        }
    }
}
