<?php

namespace Database\Seeders;

use App\Enums\JobTitleGroupEnum;
use App\Models\MasterData\JobTitle;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            // Dokter
            ['name' => 'Dokter Ahli Pertama', 'group' => JobTitleGroupEnum::DOKTER],
            ['name' => 'Dokter Non-ASN', 'group' => JobTitleGroupEnum::DOKTER],

            // Dokter Gigi
            ['name' => 'Dokter Gigi Ahli Madya', 'group' => JobTitleGroupEnum::DOKTER_GIGI],
            ['name' => 'Dokter Gigi Ahli Muda', 'group' => JobTitleGroupEnum::DOKTER_GIGI],
            ['name' => 'Dokter Gigi Ahli Pertama', 'group' => JobTitleGroupEnum::DOKTER_GIGI],

            // Perawat
            ['name' => 'Perawat Ahli Muda', 'group' => JobTitleGroupEnum::PERAWAT],
            ['name' => 'Perawat Ahli Pertama', 'group' => JobTitleGroupEnum::PERAWAT],
            ['name' => 'Perawat Mahir', 'group' => JobTitleGroupEnum::PERAWAT],
            ['name' => 'Perawat Penyelia', 'group' => JobTitleGroupEnum::PERAWAT],
            ['name' => 'Perawat Terampil', 'group' => JobTitleGroupEnum::PERAWAT],
            ['name' => 'Perawat Non-ASN', 'group' => JobTitleGroupEnum::PERAWAT],

            // Perawat Gigi & Terapis Gigi dan Mulut
            ['name' => 'Perawat Gigi Terampil', 'group' => JobTitleGroupEnum::TERAPIS_GIGI],
            ['name' => 'Terapis Gigi dan Mulut Terampil', 'group' => JobTitleGroupEnum::TERAPIS_GIGI],

            // Bidan
            ['name' => 'Bidan Ahli Madya', 'group' => JobTitleGroupEnum::BIDAN],
            ['name' => 'Bidan Ahli Muda', 'group' => JobTitleGroupEnum::BIDAN],
            ['name' => 'Bidan Ahli Pertama', 'group' => JobTitleGroupEnum::BIDAN],
            ['name' => 'Bidan Mahir', 'group' => JobTitleGroupEnum::BIDAN],
            ['name' => 'Bidan Penyelia', 'group' => JobTitleGroupEnum::BIDAN],
            ['name' => 'Bidan Terampil', 'group' => JobTitleGroupEnum::BIDAN],
            ['name' => 'Bidan Non-ASN', 'group' => JobTitleGroupEnum::BIDAN],

            // Kefarmasian
            ['name' => 'Apoteker Ahli Madya', 'group' => JobTitleGroupEnum::KEFARMASIAN],
            ['name' => 'Asisten Apoteker Penyelia', 'group' => JobTitleGroupEnum::KEFARMASIAN],
            ['name' => 'Asisten Apoteker Terampil', 'group' => JobTitleGroupEnum::KEFARMASIAN],

            // Gizi
            ['name' => 'Nutrisionis Penyelia', 'group' => JobTitleGroupEnum::GIZI],
            ['name' => 'Nutrisionis Terampil', 'group' => JobTitleGroupEnum::GIZI],

            // Kesehatan Lingkungan
            ['name' => 'Sanitarian Terampil', 'group' => JobTitleGroupEnum::KESEHATAN_LINGKUNGAN],
            ['name' => 'Tenaga Sanitasi Lingkungan Terampil', 'group' => JobTitleGroupEnum::KESEHATAN_LINGKUNGAN],
            ['name' => 'Tenaga Sanitasi Lingkungan Ahli Pertama', 'group' => JobTitleGroupEnum::KESEHATAN_LINGKUNGAN],
            ['name' => 'Tenaga Sanitasi Lingkungan Ahli Muda', 'group' => JobTitleGroupEnum::KESEHATAN_LINGKUNGAN],

            // Kesehatan Masyarakat
            ['name' => 'Administrator Kesehatan Ahli Pertama', 'group' => JobTitleGroupEnum::KESEHATAN_MASYARAKAT],
            ['name' => 'Epidemiolog Kesehatan Ahli Pertama', 'group' => JobTitleGroupEnum::KESEHATAN_MASYARAKAT],
            [
                'name' => 'Tenaga Promosi Kesehatan dan Ilmu Perilaku Ahli Madya',
                'group' => JobTitleGroupEnum::KESEHATAN_MASYARAKAT,
            ],
            [
                'name' => 'Tenaga Promosi Kesehatan dan Ilmu Perilaku Ahli Pertama',
                'group' => JobTitleGroupEnum::KESEHATAN_MASYARAKAT,
            ],
            ['name' => 'Kesehatan Masyarakat Non-ASN', 'group' => JobTitleGroupEnum::KESEHATAN_MASYARAKAT],

            // Laboratorium
            ['name' => 'Pranata Laboratorium Kesehatan Penyelia', 'group' => JobTitleGroupEnum::LABORATORIUM],
            ['name' => 'Pranata Laboratorium Kesehatan Terampil', 'group' => JobTitleGroupEnum::LABORATORIUM],
            ['name' => 'Analis Kesehatan Non-ASN', 'group' => JobTitleGroupEnum::LABORATORIUM],

            // Administrasi & Penunjang
            ['name' => 'Fasilitator Pemerintahan', 'group' => JobTitleGroupEnum::ADMINISTRASI_PENUNJANG],
            ['name' => 'Operator Layanan Kesehatan', 'group' => JobTitleGroupEnum::ADMINISTRASI_PENUNJANG],
            ['name' => 'Operator Layanan Operasional', 'group' => JobTitleGroupEnum::ADMINISTRASI_PENUNJANG],
            [
                'name' => 'Penata Kelola Sistem dan Teknologi Informasi',
                'group' => JobTitleGroupEnum::ADMINISTRASI_PENUNJANG,
            ],
            ['name' => 'Penata Layanan Operasional', 'group' => JobTitleGroupEnum::ADMINISTRASI_PENUNJANG],
            ['name' => 'Pengadministrasi Perkantoran', 'group' => JobTitleGroupEnum::ADMINISTRASI_PENUNJANG],
        ])->each(
            fn(array $record) => JobTitle::updateOrCreate(['name' => $record['name']], ['group' => $record['group']]),
        );
    }
}
