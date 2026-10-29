<?php

namespace App\Services\Letter;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Riskihajar\Terbilang\Terbilang;

class SpellLetterNumber
{
    public function __construct(private Terbilang $terbilang) {}

    /**
     * @param  array{mode: string, scale: int, allow_negative: bool}  $settings
     * @return array{value: string, display: string, words: string}
     */
    public function resolve(string $value, array $settings, bool $money = false): array
    {
        if (!preg_match('/\A(-?)([0-9]{1,12})(?:\.([0-9]{1,6}))?\z/D', $value, $parts)) {
            throw ValidationException::withMessages(['value' => 'Angka harus berupa string desimal tanpa pemisah ribuan, maksimal 12 digit bulat.']);
        }
        $fraction = $parts[3] ?? '';
        if (($parts[1] === '-' && !$settings['allow_negative']) || strlen($fraction) > $settings['scale'] || ($settings['mode'] === 'integer' && $fraction !== '')) {
            throw ValidationException::withMessages(['value' => 'Tanda atau presisi angka tidak sesuai pengaturan pertanyaan.']);
        }
        $integer = ltrim($parts[2], '0') ?: '0';
        $fraction = $money && $settings['mode'] === 'decimal' ? str_pad($fraction, 2, '0') : rtrim($fraction, '0');
        $negative = $parts[1] === '-' && ($integer !== '0' || trim($fraction, '0') !== '');
        $words = (string) $this->terbilang->make((int) $integer);
        if ($money) {
            $words .= ' rupiah';
            if ((int) $fraction > 0) {
                $words .= ' dan ' . $this->terbilang->make((int) $fraction) . ' sen';
            }
        } elseif ($fraction !== '') {
            $words .= ' koma ' . implode(' ', array_map(fn(string $digit): string => (string) $this->terbilang->make((int) $digit), str_split($fraction)));
        }
        $canonical = ($negative ? '-' : '') . $integer . ($fraction === '' ? '' : '.' . $fraction);
        $display = $money ? 'Rp' . preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer) : $integer;
        $display = ($negative ? '-' : '') . $display . ($fraction === '' ? '' : ',' . $fraction);

        return ['value' => $canonical, 'display' => $display, 'words' => Str::title(($negative ? 'minus ' : '') . $words)];
    }
}
