<?php

namespace App\Services\Letter;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceItem;
use App\Services\LetterTemplate\LetterTemplateTestFields;
use Illuminate\Validation\ValidationException;

class AdjustLetterDraftTemplate
{
    public function __construct(private LetterTemplateTestFields $fields) {}

    /**
     * Match semantic identity; copied template item UUIDs deliberately differ between versions.
     *
     * @param  array{values: array<string, string|list<string>|null>, signers: array<string, ?string>}  $stored
     * @return array{data: array{values: array, signers: array}, changes: list<array{label: string, before: ?string, after: ?string}>}
     */
    public function map(LetterTemplateVersion $source, LetterTemplateVersion $target, array $stored): array
    {
        $old = $this->fields->variables($source)->keyBy('name');
        $new = $this->fields->variables($target)->keyBy('name');
        $values = [];
        $changes = [['label' => 'Versi templat', 'before' => 'Versi ' . $source->version_number, 'after' => 'Versi ' . $target->version_number . '; isi DOCX mengikuti versi ini dan perlu diperiksa ulang.']];
        foreach ($new as $name => $variable) {
            $previous = $old->get($name);
            $compatible = $previous !== null && $this->signature($previous) === $this->signature($variable);
            $input = $compatible ? ($stored['values'][$previous->uuid] ?? null) : null;
            if ($compatible && $variable->type->value === 5 && $input !== null && !$variable->radioOptions->contains('code', $input)) {
                $compatible = false;
            }
            if ($compatible && in_array($variable->type->value, [6, 7], true) && $input !== null) {
                try {
                    app(ResolveLetterQuestion::class)->resolve($variable, $input);
                } catch (ValidationException) {
                    $compatible = false;
                }
            }
            if ($compatible && $variable->type === LetterTemplateVariableTypeEnum::COMBOBOX && !$this->validChoice($variable, $input)) {
                $compatible = false;
            }
            $values[$variable->uuid] = $compatible ? $input : ($variable->is_multiple ? [] : null);
            $changes[] = ['label' => $variable->label . ' (' . $name . ')', 'before' => $previous === null ? null : $previous->label, 'after' => $previous === null ? 'Variabel baru; lengkapi isian.' : ($compatible ? 'Isian dipertahankan; konfigurasi/format terbaru diterapkan.' : 'Jenis, sumber, atau pilihan berubah; isi ulang.')];
        }
        foreach ($old as $name => $variable) {
            if (!$new->has($name)) {
                $changes[] = ['label' => $variable->label . ' (' . $name . ')', 'before' => 'Ada pada versi sebelumnya.', 'after' => 'Variabel dihapus; tidak digunakan dalam dokumen baru.'];
            }
        }
        $oldSlots = $this->fields->slots($source);
        $newSlots = $this->fields->slots($target);
        $target->loadMissing('signerSlots.candidates.employee');
        $source->loadMissing('signerSlots.candidates.employee');
        $edges = [];
        foreach ($newSlots as $newId => $prefix) {
            $slot = $target->signerSlots->firstWhere('uuid', $newId);
            $allowed = $slot->candidates->filter(fn($candidate): bool => $candidate->employee?->status === AsnStatusEnum::AKTIF)->map(fn($candidate): string => $candidate->employee->uuid)->all();
            $exact = [];
            foreach ($oldSlots as $oldId => $oldPrefix) {
                $selected = $stored['signers'][$oldId] ?? null;
                if ($selected === null || !in_array($selected, $allowed, true)) {
                    continue;
                }
                $edges[$newId][] = $oldId;
                $previous = $source->signerSlots->firstWhere('uuid', $oldId);
                if ($previous->candidates->pluck('employee_id')->sort()->values()->all() === $slot->candidates->pluck('employee_id')->sort()->values()->all()) {
                    $exact[] = $oldId;
                }
            }
            if (count($exact) === 1) {
                $edges[$newId] = $exact;
            }
        }
        $signers = [];
        foreach ($newSlots as $newId => $prefix) {
            $candidates = $edges[$newId] ?? [];
            $oldId = count($candidates) === 1 ? $candidates[0] : null;
            $unique = $oldId !== null && count(array_filter($edges, fn(array $ids): bool => in_array($oldId, $ids, true))) === 1;
            $signers[$newId] = $unique ? $stored['signers'][$oldId] : null;
            $changes[] = ['label' => str_replace('_', ' ', ucfirst($prefix)), 'before' => null, 'after' => $unique ? 'Pilihan dipertahankan; pemetaan slot tidak ambigu.' : 'Pilih ulang penandatangan; slot baru, kandidat berubah, atau pemetaan ambigu.'];
        }
        if ($source->letter_date_format !== $target->letter_date_format) {
            $changes[] = ['label' => 'Format tanggal surat', 'before' => $source->letter_date_format, 'after' => $target->letter_date_format];
        }
        if ($source->letter_number_id !== $target->letter_number_id) {
            $changes[] = ['label' => 'Klasifikasi', 'before' => $source->letterNumber->classification_code, 'after' => $target->letterNumber->classification_code];
        }

        return ['data' => ['values' => $values, 'signers' => $signers], 'changes' => $changes];
    }

    private function signature(LetterTemplateVariable $variable): array
    {
        return [$variable->type->value, $variable->option_source?->value, $variable->reference_group_id, $variable->is_multiple, $variable->type->value === 8 ? ($variable->date_range['basis'] ?? null) : null];
    }

    private function validChoice(LetterTemplateVariable $variable, mixed $input): bool
    {
        $selected = $variable->is_multiple ? ($input ?? []) : (filled($input) ? [$input] : []);
        $query = $variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE ? Employee::query()->where('status', AsnStatusEnum::AKTIF) : ReferenceItem::query()->where('reference_group_id', $variable->reference_group_id);

        return $query->whereIn('uuid', $selected)->count() === count($selected);
    }
}
