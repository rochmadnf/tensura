<?php

namespace App\Services\LetterTemplate;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LetterTemplatePublicationReadiness
{
    public function __construct(private LetterTemplateTestEvidence $evidence) {}

    /** @return array{ready: bool, checks: list<array{key: string, label: string, passed: bool, message: string}>} */
    public function inspect(LetterTemplateVersion $version, bool $lockCandidates = false): array
    {
        $version->loadMissing(['signerSlots.candidates']);
        $candidateIds = $version->signerSlots->flatMap(fn(LetterTemplateSignerSlot $slot): Collection => $slot->candidates->pluck('employee_id'))->unique()->values()->all();
        $employees = Employee::query()->whereIn('id', $candidateIds)->orderBy('id')
            ->when($lockCandidates, fn(Builder $query): Builder => $query->sharedLock())->get();
        $candidatesValid = !$version->signerSlots->contains(fn(LetterTemplateSignerSlot $slot): bool => $slot->candidates->isEmpty())
            && $employees->count() === count($candidateIds)
            && !$employees->contains(fn(Employee $employee): bool => $employee->status !== AsnStatusEnum::AKTIF);
        $validated = false;
        $validationMessage = 'Unggah dan validasi DOCX sumber untuk konfigurasi terbaru.';
        try {
            if ($version->source_disk === null) {
                throw ValidationException::withMessages(['publication' => 'Unggah DOCX sumber dan jalankan validasi terlebih dahulu.']);
            }
            $this->evidence->assertValidated($version);
            $validated = true;
            $validationMessage = 'DOCX sumber dan konfigurasi terbaru sudah valid.';
        } catch (ValidationException $exception) {
            $validationMessage = implode(' ', array_merge(...array_values($exception->errors())));
        }
        $testState = $this->evidence->state($version);
        $testCurrent = $validated && in_array($testState, ['ready', 'confirmed'], true);
        $confirmed = $testCurrent && $version->visually_confirmed_at !== null && filled($version->visually_confirmed_by_uuid) && filled($version->visually_confirmed_by_name);
        $draft = $version->status === LetterTemplateVersionStatusEnum::DRAFT && $version->first_used_at === null;
        $checks = [
            ['key' => 'draft', 'label' => 'Draft yang belum digunakan', 'passed' => $draft, 'message' => $draft ? 'Versi dapat dipublikasikan setelah seluruh pemeriksaan terpenuhi.' : 'Publikasi hanya tersedia untuk draft yang belum digunakan.'],
            ['key' => 'validation', 'label' => 'Validasi DOCX dan konfigurasi', 'passed' => $validated, 'message' => $validationMessage],
            ['key' => 'test', 'label' => 'Hasil uji terbaru masih berlaku', 'passed' => $testCurrent, 'message' => $testCurrent ? 'Hasil uji tersedia dan cocok dengan DOCX serta konfigurasi.' : 'Generate ulang hasil yang hilang, berubah, usang, atau kedaluwarsa.'],
            ['key' => 'visual', 'label' => 'Pemeriksaan visual dikonfirmasi', 'passed' => $confirmed, 'message' => $confirmed ? 'Admin sudah mengonfirmasi pemeriksaan hasil uji terbaru.' : 'Buka hasil uji dan konfirmasikan pemeriksaan visual terlebih dahulu.'],
            ['key' => 'candidates', 'label' => 'Seluruh kandidat penandatangan aktif', 'passed' => $candidatesValid, 'message' => $candidatesValid ? 'Semua slot memiliki kandidat aktif; token slot tetap opsional.' : 'Ganti atau hapus kandidat tidak aktif dan lengkapi kandidat setiap slot.'],
        ];

        return ['ready' => collect($checks)->every(fn(array $check): bool => $check['passed']), 'checks' => $checks];
    }

    public function assertReady(LetterTemplateVersion $version): void
    {
        $readiness = $this->inspect($version, true);
        if (!$readiness['ready']) {
            $messages = array_column(array_filter($readiness['checks'], fn(array $check): bool => !$check['passed']), 'message');
            throw ValidationException::withMessages(['publication' => implode(' ', $messages)]);
        }
    }
}
