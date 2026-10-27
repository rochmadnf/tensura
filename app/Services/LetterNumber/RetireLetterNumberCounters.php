<?php

namespace App\Services\LetterNumber;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RetireLetterNumberCounters
{
    /** @return list<string> */
    public function pending(bool $lock = false): array
    {
        $page = Schema::hasColumn('letter_numbers', 'page_number');
        $sequence = Schema::hasColumn('letter_numbers', 'sequence_number');
        if (!$page && !$sequence) {
            return [];
        }
        if ($page !== $sequence) {
            throw new RuntimeException('Kolom counter lama tidak lengkap. Perbaiki skema sebelum melanjutkan.');
        }
        $numbers = DB::table('letter_numbers')->orderBy('id');
        if ($lock) {
            $numbers->lockForUpdate();
        }
        $pending = [];
        foreach ($numbers->get() as $number) {
            if ($number->page_number === null && $number->sequence_number === null) {
                continue;
            }
            $period = $number->initial_period_id === null ? null : DB::table('letter_number_periods')->where('id', $number->initial_period_id)->first();
            if ($number->page_number === null || $number->sequence_number === null || $number->page_number < 1 || $number->sequence_number < 1 || $number->sequence_number > 99 || $period === null || $period->letter_number_id !== $number->id || $period->last_page_number === null || [$period->last_page_number, $period->last_sequence_number] < [$number->page_number, $number->sequence_number]) {
                $pending[] = $number->classification_code . ' — ' . $number->classification_name;
            }
        }

        return $pending;
    }

    public function retire(): void
    {
        DB::transaction(function (): void {
            $pending = $this->pending(true);
            if ($pending !== []) {
                throw new RuntimeException('Counter lama belum boleh dihapus. Siapkan periode klasifikasi: ' . implode(', ', $pending));
            }
            if (Schema::hasColumn('letter_numbers', 'page_number')) {
                Schema::table('letter_numbers', fn(Blueprint $table) => $table->dropColumn(['page_number', 'sequence_number']));
            }
        }, 1);
    }
}
