<?php

namespace App\Http\Requests;

use App\Enums\AsnStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\StrStatusEnum;
use App\Models\MasterData\Employee;
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
            'gender' => ['bail', 'required', 'integer', Rule::in([Employee::LAKI_LAKI, Employee::PEREMPUAN])],
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
            'str_number' => [
                'bail',
                'nullable',
                'required_with:str_status,str_published_date',
                'string',
                'size:16',
                Rule::unique('employees', 'str_number')->ignore($this->route('employee')),
            ],
            'str_status' => [
                'bail',
                'nullable',
                'required_with:str_number,str_published_date',
                Rule::enum(StrStatusEnum::class),
            ],
            'str_published_date' => [
                'bail',
                'nullable',
                'required_with:str_number,str_status',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'sip_number' => [
                'bail',
                'nullable',
                'required_with:sip_published_date,sip_expired_date',
                'string',
                'max:30',
                Rule::unique('employees', 'sip_number')->ignore($this->route('employee')),
            ],
            'sip_published_date' => [
                'bail',
                'nullable',
                'required_with:sip_number,sip_expired_date',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'sip_expired_date' => [
                'bail',
                'nullable',
                'required_with:sip_number,sip_published_date',
                'date_format:Y-m-d',
                'after:sip_published_date',
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
            'gender' => 'Jenis Kelamin',
            'str_number' => 'Nomor STR',
            'str_status' => 'Status STR',
            'str_published_date' => 'Tanggal Terbit STR',
            'sip_number' => 'Nomor SIP',
            'sip_published_date' => 'Tanggal Terbit SIP',
            'sip_expired_date' => 'Tanggal Berakhir SIP',

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
            'gender.in' => 'Jenis Kelamin yang dipilih tidak valid.',
            'gender.integer' => 'Jenis Kelamin yang dipilih tidak valid.',
            'str_number.size' => 'Nomor STR harus terdiri dari 16 karakter.',
            'str_number.unique' => 'Nomor STR sudah digunakan.',
            'sip_number.unique' => 'Nomor SIP sudah digunakan.',
            'str_published_date.before_or_equal' => 'Tanggal Terbit STR maksimal tanggal hari ini.',
            'sip_published_date.before_or_equal' => 'Tanggal Terbit SIP maksimal tanggal hari ini.',
            'sip_expired_date.after' => 'Tanggal Berakhir SIP harus setelah Tanggal Terbit SIP.',
            'str_number.required_with' => 'Nomor STR wajib diisi bersama seluruh data STR.',
            'str_status.required_with' => 'Status STR wajib diisi bersama seluruh data STR.',
            'str_published_date.required_with' => 'Tanggal Terbit STR wajib diisi bersama seluruh data STR.',
            'sip_number.required_with' => 'Nomor SIP wajib diisi bersama seluruh data SIP.',
            'sip_published_date.required_with' => 'Tanggal Terbit SIP wajib diisi bersama seluruh data SIP.',
            'sip_expired_date.required_with' => 'Tanggal Berakhir SIP wajib diisi bersama seluruh data SIP.',
            'join_date.date_format' => 'Tanggal Bergabung harus berupa tanggal yang valid.',
            'str_published_date.date_format' => 'Tanggal Terbit STR harus berupa tanggal yang valid.',
            'sip_published_date.date_format' => 'Tanggal Terbit SIP harus berupa tanggal yang valid.',
            'sip_expired_date.date_format' => 'Tanggal Berakhir SIP harus berupa tanggal yang valid.',
            'nik.regex' => 'NIK harus terdiri dari 16 digit angka.',
            'nip.regex' => 'NIP harus terdiri dari 18 digit angka.',
            'join_date.before_or_equal' => 'Tanggal Bergabung maksimal tanggal hari ini.',
        ];
    }
}
