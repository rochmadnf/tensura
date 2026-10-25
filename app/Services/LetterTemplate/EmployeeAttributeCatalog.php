<?php

namespace App\Services\LetterTemplate;

class EmployeeAttributeCatalog
{
    public const string REVISION = 'employee-attributes-v1';

    /** @return list<array{id: string, label: string, suffix: string, type: string}> */
    public static function options(): array
    {
        $fields = [
            ['name', 'Nama beserta gelar', 'nama'], ['raw_name', 'Nama tanpa gelar', 'nama_tanpa_gelar'],
            ['front_title', 'Gelar depan', 'gelar_depan'], ['back_title', 'Gelar belakang', 'gelar_belakang'],
            ['nik', 'NIK', 'nik'], ['nip', 'NIP', 'nip'], ['gender', 'Jenis kelamin', 'jenis_kelamin'], ['email', 'Email', 'email'],
            ['job_title', 'Jabatan', 'jabatan'], ['job_title_group', 'Kelompok jabatan', 'kelompok_jabatan'],
            ['rank', 'Pangkat', 'pangkat'], ['grade', 'Golongan', 'golongan'], ['join_date', 'Tanggal bergabung', 'tanggal_bergabung', 'date'],
            ['contract_status', 'Status kontrak', 'status_kontrak'], ['status', 'Status pegawai', 'status_pegawai'],
            ['tenure', 'Masa kerja', 'masa_kerja'], ['tenure_years', 'Masa kerja: tahun', 'masa_kerja_tahun'],
            ['tenure_months', 'Masa kerja: sisa bulan', 'masa_kerja_bulan'], ['tenure_days', 'Masa kerja: sisa hari', 'masa_kerja_hari'],
            ['str_number', 'Nomor STR', 'nomor_str'], ['str_status', 'Status STR', 'status_str'],
            ['str_published_date', 'Tanggal terbit STR', 'tanggal_terbit_str', 'date'],
            ['sip_number', 'Nomor SIP', 'nomor_sip'], ['sip_published_date', 'Tanggal terbit SIP', 'tanggal_terbit_sip', 'date'],
            ['sip_expired_date', 'Tanggal kedaluwarsa SIP', 'tanggal_kedaluwarsa_sip', 'date'],
        ];

        return array_map(fn(array $field): array => ['id' => $field[0], 'label' => $field[1], 'suffix' => $field[2], 'type' => $field[3] ?? 'text'], $fields);
    }

    public static function isDate(string $attribute): bool
    {
        return in_array($attribute, array_column(array_filter(self::options(), fn(array $field): bool => $field['type'] === 'date'), 'id'), true);
    }

    /** @return array<string, string> Token name => source attribute, including the legacy name alias. */
    public static function tokens(string $prefix): array
    {
        $tokens = [$prefix => 'name'];
        foreach (self::options() as $field) {
            $tokens[$prefix . '_' . $field['suffix']] = $field['id'];
        }

        return $tokens;
    }

    /**
     * @param  iterable<array{source_attribute: string, default_text?: ?string, date_format?: ?string}>  $settings
     * @return list<array{source_attribute: string, default_text: ?string, date_format: ?string}>
     */
    public static function settings(iterable $settings): array
    {
        $saved = collect($settings)->keyBy('source_attribute');

        return array_map(fn(array $field): array => [
            'source_attribute' => $field['id'],
            'default_text' => $saved->get($field['id'])['default_text'] ?? null,
            'date_format' => $field['type'] === 'date' ? ($saved->get($field['id'])['date_format'] ?? 'd-m-Y') : null,
        ], self::options());
    }
}
