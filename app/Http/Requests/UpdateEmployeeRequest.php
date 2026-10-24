<?php

namespace App\Http\Requests;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'nik' => [
                'bail',
                'required',
                'string',
                'regex:/^[0-9]{16}$/D',
                Rule::unique('employees', 'nik')->ignore($this->route('employee')),
            ],
            'nip' => [
                'bail',
                'nullable',
                'string',
                'regex:/^[0-9]{18}$/D',
                Rule::unique('employees', 'nip')->ignore($this->route('employee')),
            ],
            'name' => ['bail', 'required', 'string', 'max:100'],
            'front_title' => ['bail', 'nullable', 'string', 'max:50'],
            'back_title' => ['bail', 'nullable', 'string', 'max:50'],
            'job_title_id' => ['bail', 'required', 'string', 'uuid', Rule::exists('job_titles', 'uuid')],
            'rank' => ['bail', 'nullable', 'string', 'max:50'],
            'grade' => ['bail', 'nullable', 'string', 'max:50'],
            'join_date' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'email' => [
                'bail',
                'nullable',
                'string',
                'email',
                'max:100',
                Rule::unique('employees', 'email')->ignore($this->route('employee')),
            ],
            'contract_status' => ['bail', 'required', Rule::enum(ContractStatusEnum::class)],
            'status' => ['bail', 'required', Rule::enum(AsnStatusEnum::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'nip' => 'NIP',
            'name' => 'Nama',
            'front_title' => 'Gelar Depan',
            'back_title' => 'Gelar Belakang',
            'job_title_id' => 'Jabatan',
            'rank' => 'Pangkat',
            'grade' => 'Golongan',
            'join_date' => 'Tanggal Bergabung',
            'email' => 'Email',
            'contract_status' => 'Status Kontrak',
            'status' => 'Status Pegawai',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nik.regex' => 'NIK harus terdiri dari 16 digit angka.',
            'nip.regex' => 'NIP harus terdiri dari 18 digit angka.',
            'join_date.before_or_equal' => 'Tanggal Bergabung maksimal tanggal hari ini.',
        ];
    }
}
