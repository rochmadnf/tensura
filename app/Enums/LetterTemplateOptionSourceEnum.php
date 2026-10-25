<?php

namespace App\Enums;

enum LetterTemplateOptionSourceEnum: int
{
    case REFERENCE = 1;
    case EMPLOYEE = 2;

    public function label(): string
    {
        return match ($this) {
            self::REFERENCE => 'Referensi',
            self::EMPLOYEE => 'Pegawai',
        };
    }

    /** @return list<array{id: string, label: string}> */
    public function columnOptions(): array
    {
        return match ($this) {
            self::REFERENCE => [['id' => 'row_number', 'label' => 'Nomor urut'], ['id' => 'content', 'label' => 'Isi item']],
            self::EMPLOYEE => [
                ['id' => 'row_number', 'label' => 'Nomor urut'], ['id' => 'name', 'label' => 'Nama beserta gelar'],
                ['id' => 'nip', 'label' => 'NIP'], ['id' => 'job_title', 'label' => 'Jabatan'],
            ],
        };
    }

    /** @return list<array{id: int, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())->map(fn(self $item): array => ['id' => $item->value, 'label' => $item->label()])->all();
    }
}
