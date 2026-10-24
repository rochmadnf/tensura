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

    /** @return list<array{id: int, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())->map(fn(self $item): array => ['id' => $item->value, 'label' => $item->label()])->all();
    }
}
