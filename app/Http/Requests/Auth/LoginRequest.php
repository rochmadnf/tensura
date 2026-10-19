<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function attributes()
    {
        return [
            'username' => 'Nama Pengguna',
            'password' => 'Katasandi',
            'remember_me' => 'Ingat Saya',
        ];
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:6'],
            'password' => ['required', 'string', 'min:8'],
            'remember_me' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate()
    {
        $validData = collect($this->validated());

        if (
            !Auth::guard('web')->attempt(
                $validData->except('remember_me')->toArray(),
                $validData->get('remember_me', false),
            )
        ) {
            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }
    }
}
