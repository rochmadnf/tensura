<?php

namespace App\Enums;

enum LetterTemplateVariableTypeEnum: int
{
    case SHORT_TEXT = 1;
    case LONG_TEXT = 2;
    case DATE = 3;
    case COMBOBOX = 4;
    case RADIO = 5;
    case NUMBER = 6;
    case MONEY = 7;
    case DATE_RANGE = 8;

    public function label(): string
    {
        return match ($this) {
            self::SHORT_TEXT => 'Teks pendek',
            self::LONG_TEXT => 'Teks panjang',
            self::DATE => 'Tanggal',
            self::COMBOBOX => 'Combobox',
            self::RADIO => 'Pilihan tunggal', self::NUMBER => 'Angka', self::MONEY => 'Nominal', self::DATE_RANGE => 'Rentang tanggal',
        };
    }

    /** @return list<array{id: int, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())->map(fn(self $item): array => ['id' => $item->value, 'label' => $item->label()])->all();
    }
}
