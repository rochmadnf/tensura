<?php

namespace App\Services\LetterTemplate;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class FormatEmployeeDate
{
    public function format(DateTimeInterface $date, ?string $pattern = null): string
    {
        $date = CarbonImmutable::instance($date)->locale(config('app.locale'));
        $pattern = filled($pattern) ? $pattern : 'd-m-Y';
        $output = '';
        for ($index = 0; $index < strlen($pattern); $index++) {
            $character = $pattern[$index];
            if ($character === '\\' && isset($pattern[$index + 1])) {
                $output .= $pattern[++$index];
            } else {
                $output .= match ($character) {
                    'F' => $date->getTranslatedMonthName(), 'M' => $date->getTranslatedShortMonthName(),
                    'l' => $date->getTranslatedDayName(), 'D' => $date->getTranslatedShortDayName(),
                    default => $date->format($character),
                };
            }
        }

        return $output;
    }
}
