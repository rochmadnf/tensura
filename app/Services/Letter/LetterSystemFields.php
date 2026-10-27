<?php

namespace App\Services\Letter;

use App\Services\LetterTemplate\FormatEmployeeDate;
use Carbon\CarbonImmutable;

class LetterSystemFields
{
    public const array NAMES = ['nomor_surat', 'tanggal_surat'];

    public function __construct(private FormatEmployeeDate $dates) {}

    public function exampleNumber(string $classification, CarbonImmutable $date): string
    {
        $month = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$date->month - 1];

        return $classification . '/XX.XX/PKM-KMJ/' . $month . '/' . $date->year;
    }

    public function number(string $classification, CarbonImmutable $date, int $page, int $sequence): string
    {
        return str_replace('/XX.XX/', '/' . sprintf('%02d.%02d', $page, $sequence) . '/', $this->exampleNumber($classification, $date));
    }

    /** @return array{nomor_surat: string, tanggal_surat: string} */
    public function values(string $number, CarbonImmutable $date, string $format): array
    {
        return ['nomor_surat' => $number, 'tanggal_surat' => $this->dates->format($date, $format)];
    }
}
