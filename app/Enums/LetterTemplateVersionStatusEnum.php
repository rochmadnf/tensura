<?php

namespace App\Enums;

enum LetterTemplateVersionStatusEnum: int
{
    case DRAFT = 1;
    case PUBLISHED = 2;
    case ARCHIVED = 3;

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Terbit',
            self::ARCHIVED => 'Diarsipkan',
        };
    }

    /** @return list<array{id: int, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())->map(fn(self $item): array => ['id' => $item->value, 'label' => $item->label()])->all();
    }
}
