<?php

namespace App\Enums;

enum JobTitleGroupEnum: int
{
    case DOKTER = 1;
    case PERAWAT = 2;
    case KEFARMASIAN = 3;
    case BIDAN = 4;
    case DOKTER_GIGI = 5;
    case TERAPIS_GIGI = 6;
    case GIZI = 7;
    case KESEHATAN_LINGKUNGAN = 8;
    case KESEHATAN_MASYARAKAT = 9;
    case LABORATORIUM = 10;
    case ADMINISTRASI_PENUNJANG = 11;

    public function label(): string
    {
        return match ($this) {
            self::DOKTER => 'Dokter',
            self::DOKTER_GIGI => 'Dokter Gigi',
            self::PERAWAT => 'Perawat',
            self::TERAPIS_GIGI => 'Perawat Gigi & Terapis Gigi dan Mulut',
            self::BIDAN => 'Bidan',
            self::KEFARMASIAN => 'Kefarmasian',
            self::GIZI => 'Gizi',
            self::KESEHATAN_LINGKUNGAN => 'Kesehatan Lingkungan',
            self::KESEHATAN_MASYARAKAT => 'Kesehatan Masyarakat',
            self::LABORATORIUM => 'Laboratorium',
            self::ADMINISTRASI_PENUNJANG => 'Administrasi & Penunjang',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($item) => ['id' => $item->value, 'label' => $item->label()])->all();
    }
}
