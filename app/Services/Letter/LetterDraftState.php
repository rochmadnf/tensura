<?php

namespace App\Services\Letter;

use App\Enums\LetterDraftKindEnum;
use App\Enums\LetterStatusEnum;
use App\Models\Letter;
use App\Models\LetterDraft;
use Illuminate\Validation\ValidationException;

class LetterDraftState
{
    public function assertEditable(Letter $letter, LetterDraft $draft, int $revision): void
    {
        if ($letter->status !== LetterStatusEnum::DRAFT || $draft->kind !== LetterDraftKindEnum::INITIAL) {
            throw ValidationException::withMessages(['draft' => 'Operasi ini hanya tersedia untuk draft surat baru.']);
        }
        if ($draft->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => $this->conflictMessage($draft)]);
        }
        if ($draft->revision === 2147483647) {
            throw ValidationException::withMessages(['draft' => 'Batas versi draft tercapai. Hubungi pengelola aplikasi.']);
        }
    }

    public function conflictMessage(LetterDraft $draft): string
    {
        return 'Draft telah diperbarui oleh ' . $draft->updater_name . '. Muat versi terbaru sebelum menyimpan. Input Anda tetap dapat disalin.';
    }

    public function token(array $data): string
    {
        return hash_hmac('sha256', json_encode($this->canonical($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), config('app.key'));
    }

    private function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->canonical(...), $value);
    }

    /**
     * @param  array<string, ?string>  $before
     * @param  array<string, ?string>  $after
     * @return list<array{label: string, before: ?string, after: ?string}>
     */
    public function changes(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changes[] = ['label' => $key, 'before' => $before[$key] ?? null, 'after' => $after[$key] ?? null];
            }
        }

        return $changes;
    }
}
