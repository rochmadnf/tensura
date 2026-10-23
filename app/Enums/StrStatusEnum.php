<?php

namespace App\Enums;

enum StrStatusEnum: int
{
    case SEUMUR_HIDUP = 1;
    case DEFINITIF = 2;

    public function label(): string
    {
        return match ($this) {
            self::SEUMUR_HIDUP => 'Seumur Hidup',
            self::DEFINITIF => 'Definitif',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($item) => ['id' => $item->value, 'label' => $item->label()])->all();
    }
}
