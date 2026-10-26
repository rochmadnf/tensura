<?php

namespace App\Services\LetterNumber;

use App\Enums\LetterNumberingModeEnum;
use App\Models\LetterDraft;
use App\Models\LetterNumber;
use App\Models\LetterNumberAssignment;
use App\Models\LetterNumberPeriod;
use App\Services\Letter\LetterSystemFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AllocateLetterNumber
{
    public function __construct(private LetterSystemFields $system) {}

    /** Caller holds the classification row lock; this only computes a candidate, never reserves it.
     * @return array{page: int, sequence: int, number: string, year: int, month: int}
     */
    public function candidate(LetterNumber $classification, LetterDraft $draft): array
    {
        if ($classification->needsPeriodInitialization()) {
            throw ValidationException::withMessages(['number' => 'Siapkan periode angka lama pada Penomoran Surat sebelum penerbitan.']);
        }
        $period = $classification->periods()->where('year', $draft->number_year)->where('month', $draft->number_month)->first();
        if ($draft->numbering_mode === LetterNumberingModeEnum::MANUAL) {
            $position = ['page' => $draft->page_number, 'sequence' => $draft->sequence_number];
            if ($period !== null && $period->assignments()->whereNull('released_at')->where('page_number', $position['page'])->where('sequence_number', $position['sequence'])->exists()) {
                $next = $this->advance($position);
                $suggestion = $next === null ? null : $this->available($period, $next, false);
                throw ValidationException::withMessages(['number' => 'Nomor ' . $this->format($classification, $draft, $position) . ' sedang digunakan. ' . ($suggestion === null ? 'Tidak ada nomor tersedia sesudahnya pada periode ini.' : 'Saran: ' . $this->format($classification, $draft, $suggestion) . '. Ubah pilihan secara eksplisit lalu periksa ulang.')]);
            }
        } else {
            $start = $period?->nextPosition() ?? ($period === null ? ['page' => 1, 'sequence' => 1] : null);
            $position = $start === null ? null : $this->available($period, $start, true);
            if ($position === null) {
                throw ValidationException::withMessages(['number' => 'Batas kapasitas nomor periode ini tercapai.']);
            }
        }

        return [...$position, 'number' => $this->format($classification, $draft, $position), 'year' => $draft->number_year, 'month' => $draft->number_month];
    }

    /** @param array{page: int, sequence: int} $position
     * @return array{page: int, sequence: int}|null
     */
    private function available(?LetterNumberPeriod $period, array $position, bool $automatic): ?array
    {
        if ($period === null) {
            return $position;
        }
        $occupied = $period->assignments()->where(function (Builder $query) use ($automatic): void {
            $query->whereNull('released_at');
            if ($automatic) {
                $query->orWhere('mode', LetterNumberingModeEnum::MANUAL->value);
            }
        })->where(fn(Builder $query): Builder => $query->where('page_number', '>', $position['page'])->orWhere(fn(Builder $query): Builder => $query->where('page_number', $position['page'])->where('sequence_number', '>=', $position['sequence'])))
            ->orderBy('page_number')->orderBy('sequence_number')->cursor();
        foreach ($occupied as $claim) {
            if ($claim->page_number > $position['page'] || $claim->page_number === $position['page'] && $claim->sequence_number > $position['sequence']) {
                return $position;
            }
            if ($claim->page_number === $position['page'] && $claim->sequence_number === $position['sequence']) {
                $position = $this->advance($position);
                if ($position === null) {
                    return null;
                }
            }
        }

        return $position;
    }

    /** @param array{page: int, sequence: int} $position
     * @return array{page: int, sequence: int}|null
     */
    private function advance(array $position): ?array
    {
        if ($position['sequence'] < 99) {
            return ['page' => $position['page'], 'sequence' => $position['sequence'] + 1];
        }

        return $position['page'] === 2147483647 ? null : ['page' => $position['page'] + 1, 'sequence' => 1];
    }

    private function format(LetterNumber $classification, LetterDraft $draft, array $position): string
    {
        return $this->system->number($classification->classification_code, $draft->letter_date, $position['page'], $position['sequence']);
    }

    /** Caller holds the classification row lock and has compared the rendered candidate again.
     * @param  array{page: int, sequence: int, number: string, year: int, month: int}  $candidate
     */
    public function claim(LetterNumber $classification, LetterDraft $draft, array $candidate): LetterNumberAssignment
    {
        $period = $classification->periods()->firstOrCreate(['year' => $draft->number_year, 'month' => $draft->number_month]);
        $claim = $period->assignments()->create(['letter_id' => $draft->letter_id, 'page_number' => $candidate['page'], 'sequence_number' => $candidate['sequence'], 'mode' => $draft->numbering_mode->value, 'claimed_at' => now()]);
        if ($draft->numbering_mode === LetterNumberingModeEnum::AUTOMATIC) {
            $period->update(['last_page_number' => $candidate['page'], 'last_sequence_number' => $candidate['sequence']]);
        }

        return $claim;
    }
}
