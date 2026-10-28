<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LetterTemplateExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->routeIs('lt.exchange.estimate', 'lt.exchange.export')) {
            return [
                'include_docx' => ['required', 'boolean'], 'include_fonts' => ['required', 'boolean'],
                ...($this->routeIs('lt.exchange.export') ? ['password' => ['required', 'string', 'min:12', 'max:1024', 'confirmed'], 'password_confirmation' => ['required', 'string']] : []),
            ];
        }
        if ($this->routeIs('lt.exchange.inspect')) {
            return ['package' => ['required', 'file', 'extensions:tnex', 'max:' . (int) ceil(config('letter-templates.exchange.max_bytes') / 1024)], 'password' => ['required', 'string', 'min:12', 'max:1024']];
        }
        if ($this->routeIs('lt.exchange.store')) {
            return [
                'target' => ['nullable', 'uuid', Rule::exists('letter_templates', 'uuid')],
                'name' => [Rule::excludeIf(filled($this->input('target'))), 'required_without:target', 'nullable', 'string', 'min:3', 'max:100', Rule::unique('letter_templates')],
                'letter_number' => ['required', 'uuid', Rule::exists('letter_numbers', 'uuid')],
                'reference_groups' => ['present', 'array', 'max:200'], 'reference_groups.*' => ['required', 'uuid', Rule::exists('reference_groups', 'uuid')],
                'signers' => ['present', 'array', 'max:50'], 'signers.*' => ['required', 'array', 'list', 'min:1'], 'signers.*.*' => ['required', 'uuid', Rule::exists('employees', 'uuid')],
                'replace_draft' => ['required', 'boolean'], 'version_token' => ['nullable', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/D'],
                'confirmed' => ['required', 'accepted'],
            ];
        }

        return [];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['package' => 'Paket Tensura', 'password' => 'Kata Sandi', 'password_confirmation' => 'Konfirmasi Kata Sandi', 'name' => 'Nama Templat', 'target' => 'Templat Tujuan', 'letter_number' => 'Klasifikasi Nomor', 'confirmed' => 'Konfirmasi Impor', 'replace_draft' => 'Konfirmasi Penggantian Draft'];
    }
}
