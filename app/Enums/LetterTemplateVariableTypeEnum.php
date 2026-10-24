<?php

namespace App\Enums;

enum LetterTemplateVariableTypeEnum: int
{
    case SHORT_TEXT = 1;
    case LONG_TEXT = 2;
    case DATE = 3;
    case COMBOBOX = 4;

    public function label(): string
    {
        return match ($this) {
            self::SHORT_TEXT => 'Teks pendek',
            self::LONG_TEXT => 'Teks panjang',
            self::DATE => 'Tanggal',
            self::COMBOBOX => 'Combobox',
        };
    }

    /** @return list<array{id: int, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())->map(fn(self $item): array => ['id' => $item->value, 'label' => $item->label()])->all();
    }
}
