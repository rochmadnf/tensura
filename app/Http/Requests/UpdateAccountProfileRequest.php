<?php

namespace App\Http\Requests;

use App\Models\MasterData\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAccountProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100'],
            'username' => [
                'bail', 'required', 'string', 'min:6', 'max:100',
                'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/D',
                Rule::unique('users', 'username')->ignore($this->user()),
            ],
            'email' => [
                'bail', 'required', 'string', 'email', 'max:100',
                Rule::unique('users', 'email')->ignore($this->user()),
            ],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('email')) {
                    return;
                }

                if (User::query()->whereKeyNot($this->user()->getKey())
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$this->input('email')])->exists()) {
                    $validator->errors()->add('email', 'Email sudah digunakan.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'Nama', 'username' => 'Nama Pengguna', 'email' => 'Email'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.regex' => 'Nama Pengguna harus diawali huruf atau garis bawah dan hanya berisi huruf, angka, atau garis bawah.',
            'username.unique' => 'Nama Pengguna sudah digunakan.',
            'email.unique' => 'Email sudah digunakan.',
        ];
    }
}
