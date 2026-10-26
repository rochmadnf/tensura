<?php

namespace App\Enums;

enum LetterOperationStatusEnum: int
{
    case QUEUED = 1;
    case RUNNING = 2;
    case SUCCEEDED = 3;
    case FAILED = 4;
    case STALE = 5;

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Menunggu', self::RUNNING => 'Diproses', self::SUCCEEDED => 'Selesai', self::FAILED => 'Gagal', self::STALE => 'Usang',
        };
    }

    public function terminal(): bool
    {
        return !in_array($this, [self::QUEUED, self::RUNNING], true);
    }
}
