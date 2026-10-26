<?php

namespace App\Services\Employee;

use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class ImportEmployees
{
    /**
     * @param  list<array{row: int, data: array<string, mixed>, errors: array<string, string>}>  $rows
     * @return array{total: int, processed: int, imported: int, duplicate: int, invalid: int, interrupted: bool, issues: list<array{row: int, name: string, category: string, errors: array<string, string>}>}
     */
    public function import(array $rows): array
    {
        $result = ['total' => count($rows), 'processed' => 0, 'imported' => 0, 'duplicate' => 0, 'invalid' => 0, 'interrupted' => false, 'issues' => []];
        $jobs = JobTitle::query()->get(['id', 'uuid', 'name'])->groupBy(fn(JobTitle $job): string => $this->normalize($job->name));
        $options = EmployeeImportTemplate::options();
        $rules = EmployeeDataRules::rules();

        foreach ($rows as $row) {
            try {
                $data = $row['data'];
                $errors = $row['errors'];
                $matches = $jobs->get($this->normalize($data['job_title_id'] ?? ''));
                if ($matches === null) {
                    $errors['job_title_id'] = 'Jabatan belum tersedia. Tambahkan pada Master Jabatan lalu impor ulang.';
                } elseif ($matches->count() !== 1) {
                    $errors['job_title_id'] = 'Nama jabatan cocok dengan beberapa master. Perbaiki nama pada Master Jabatan lalu impor ulang.';
                } else {
                    $data['job_title_id'] = $matches->first()->uuid;
                }
                foreach ($options as $field => $choices) {
                    if ($data[$field] === null) {
                        continue;
                    }
                    $choice = collect($choices)->first(fn(array $option): bool => $this->normalize($option['label']) === $this->normalize($data[$field]));
                    if ($choice === null) {
                        $errors[$field] = EmployeeImportTemplate::columns()[$field] . ' tidak valid. Pilih nilai dari dropdown template.';
                    } else {
                        $data[$field] = $choice['id'];
                    }
                }

                $validator = Validator::make($data, $rules, EmployeeDataRules::messages(), EmployeeDataRules::attributes());
                $validator->fails();
                $errors += array_map(fn(array $messages): string => $messages[0], $validator->errors()->messages());
                $duplicates = array_keys(array_filter($validator->failed(), fn(array $failures): bool => array_key_exists('Unique', $failures)));
                foreach ($duplicates as $field) {
                    $errors[$field] = EmployeeImportTemplate::columns()[$field] . ' sudah digunakan. Pegawai dilewati.';
                }

                if ($errors !== []) {
                    $category = $duplicates !== [] ? 'duplicate' : 'invalid';
                    $result[$category]++;
                    $result['issues'][] = ['row' => $row['row'], 'name' => (string) ($data['name'] ?? ''), 'category' => $category, 'errors' => $errors];
                } else {
                    $attributes = $validator->validated();
                    $attributes['job_title_id'] = $matches->first()->id;
                    try {
                        DB::transaction(fn(): Employee => Employee::create($attributes));
                        $result['imported']++;
                    } catch (UniqueConstraintViolationException) {
                        $result['duplicate']++;
                        $result['issues'][] = ['row' => $row['row'], 'name' => $data['name'], 'category' => 'duplicate', 'errors' => ['identity' => 'NIK, NIP, email, nomor STR, atau nomor SIP baru saja digunakan oleh data lain. Pegawai dilewati.']];
                    }
                }
                $result['processed']++;
            } catch (Throwable $exception) {
                report($exception);
                $result['interrupted'] = true;
                break;
            }
        }

        return $result;
    }

    private function normalize(string $value): string
    {
        return Str::lower(trim($value));
    }
}
