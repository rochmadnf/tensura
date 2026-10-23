<?php

namespace App\Enums;

enum ContractStatusEnum: int
{
    case PNS = 1;
    case PPPK = 2;
    case PPPK_PARUH_WAKTU = 3;
    case KONTRAK_BLUD = 4;

    public function label(): string
    {
        return match ($this) {
            self::PNS => 'PNS',
            self::PPPK => 'PPPK',
            self::PPPK_PARUH_WAKTU => 'PPPK Paruh Waktu',
            self::KONTRAK_BLUD => 'Kontrak BLUD',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($item) => ['id' => $item->value, 'label' => $item->label()])->all();
    }
}
