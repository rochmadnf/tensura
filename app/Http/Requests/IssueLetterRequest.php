<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'between:1,2147483647'], 'artifact' => ['required', 'uuid'], 'fingerprint' => ['required', 'string', 'size:64'], 'idempotency_key' => ['required', 'uuid'], 'publication_month' => ['required', 'date_format:Y-m'], 'keep_period' => ['accepted'], 'confirmed' => ['accepted']];
    }

    public function attributes(): array
    {
        return ['revision' => 'Versi Draft', 'artifact' => 'Preview', 'publication_month' => 'Bulan Penerbitan', 'keep_period' => 'Konfirmasi Tanggal/Periode', 'confirmed' => 'Konfirmasi Penerbitan'];
    }
}
