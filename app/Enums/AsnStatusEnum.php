<?php

namespace App\Enums;

enum AsnStatusEnum: int
{
    case AKTIF = 1;
    case MUTASI = 2;
    case CLTN = 3;
    case DIBERHENTIKAN_SEMENTARA = 4;
    case TUGAS_BELAJAR = 5;
    case IZIN_BELAJAR = 6;
    case BKO = 7;
    case MPP = 8;
    case PENSIUN = 9;
    case PEMBERHENTIAN_DENGAN_HORMAT = 10;
    case PTDH = 11;

    public function label(): string
    {
        return match ($this) {
            self::AKTIF => 'Aktif',
            self::MUTASI => 'Mutasi',
            self::CLTN => 'Cuti di Luar Tanggungan Negara',
            self::DIBERHENTIKAN_SEMENTARA => 'Diberhentikan Sementara',
            self::TUGAS_BELAJAR => 'Tugas Belajar',
            self::IZIN_BELAJAR => 'Izin Belajar',
            self::BKO => 'Diperbantukan ke Instansi Lain',
            self::MPP => 'Masa Persiapan Pensiun',
            self::PENSIUN => 'Pensiun',
            self::PEMBERHENTIAN_DENGAN_HORMAT => 'Pemberhentian Dengan Hormat',
            self::PTDH => 'Pemberhentian Tidak Dengan Hormat',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($item) => ['id' => $item->value, 'label' => $item->label()])->all();
    }
}
