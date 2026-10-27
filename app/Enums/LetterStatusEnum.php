<?php

namespace App\Enums;

enum LetterStatusEnum: int
{
    case DRAFT = 1;
    case PUBLISHED = 2;
    case CANCELLED = 3;

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft', self::PUBLISHED => 'Terbit', self::CANCELLED => 'Batal'
        };
    }
}
