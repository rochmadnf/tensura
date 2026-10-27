<?php

namespace App\Services\LetterNumber;

use App\Models\LetterNumber;
use App\Models\LetterNumberPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrepareLetterNumberPeriod
{
    /** @param array{year: int|string, month: int|string, mode: string, last_page_number?: int|string, last_sequence_number?: int|string} $data */
    public function prepare(LetterNumber $letterNumber, array $data): LetterNumberPeriod
    {
        return DB::transaction(function () use ($letterNumber, $data): LetterNumberPeriod {
            $letterNumber = LetterNumber::query()->whereKey($letterNumber->id)->lockForUpdate()->firstOrFail();
            if ($letterNumber->periods()->where('year', $data['year'])->where('month', $data['month'])->exists()) {
                throw ValidationException::withMessages(['month' => 'Periode ini sudah tersedia. Angka tersimpan tidak diubah.']);
            }
            $legacy = $letterNumber->needsPeriodInitialization();
            if ($legacy !== ($data['mode'] === 'legacy')) {
                throw ValidationException::withMessages(['mode' => $legacy ? 'Tentukan periode untuk angka lama terlebih dahulu.' : 'Angka lama tidak tersedia atau sudah dipindahkan. Muat ulang halaman.']);
            }
            if ($legacy && ($letterNumber->page_number === null || $letterNumber->sequence_number === null)) {
                throw ValidationException::withMessages(['mode' => 'Angka lama tidak lengkap. Periksa data sebelum pemindahan.']);
            }
            $period = $letterNumber->periods()->create([
                'year' => $data['year'], 'month' => $data['month'],
                'last_page_number' => $legacy ? $letterNumber->page_number : ($data['mode'] === 'custom' ? $data['last_page_number'] : null),
                'last_sequence_number' => $legacy ? $letterNumber->sequence_number : ($data['mode'] === 'custom' ? $data['last_sequence_number'] : null),
            ]);
            if ($legacy) {
                $letterNumber->forceFill(['initial_period_id' => $period->id])->save();
            }

            return $period;
        }, 5);
    }
}
