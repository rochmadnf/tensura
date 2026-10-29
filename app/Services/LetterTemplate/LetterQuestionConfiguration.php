<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplateVariable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LetterQuestionConfiguration
{
    /** @return array<string, mixed> */
    public function read(LetterTemplateVariable $variable): array
    {
        return ['radio' => $variable->radio === null ? null : [...$variable->radio, 'options' => $variable->radioOptions->map(fn($option): array => ['id' => $option->uuid, 'code' => $option->code, 'label' => $option->label])->all()], 'numeric' => $variable->numeric, 'date_range' => $variable->date_range];
    }

    /** @return array<string, list<string>> */
    public function errors(array $variable): array
    {
        $type = (int) $variable['type'];
        $errors = [];
        foreach (['radio' => $type === 5, 'numeric' => in_array($type, [6, 7], true), 'date_range' => $type === 8] as $key => $required) {
            if ($required && !is_array($variable[$key] ?? null)) {
                $errors[$key] = ['Konfigurasi jenis pertanyaan wajib diisi.'];
            }
            if (!$required && ($variable[$key] ?? null) !== null) {
                $errors[$key] = ['Konfigurasi tidak sesuai jenis pertanyaan.'];
            }
        }
        if ($type <= 4 || $errors !== []) {
            return $errors;
        }
        foreach (['default_text', 'employee_attributes'] as $key) {
            if (filled($variable[$key] ?? null)) {
                $errors[$key] = ['Pengaturan tidak berlaku untuk jenis pertanyaan ini.'];
            }
        }
        $rules = match ($type) {
            5 => ['radio' => 'array:selected_text,options', 'radio.selected_text' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F]/'], 'radio.options' => 'required|array|list|min:2|max:100', 'radio.options.*' => 'array:id,code,label', 'radio.options.*.id' => 'nullable|uuid|distinct', 'radio.options.*.code' => ['required', 'string', 'max:100', 'distinct', 'regex:/\A[a-z][a-z0-9_]*\z/D'], 'radio.options.*.label' => 'required|string|max:100'],
            6, 7 => ['numeric' => 'array:mode,scale,allow_negative', 'numeric.mode' => 'required|in:integer,decimal', 'numeric.scale' => 'required|integer|between:0,6', 'numeric.allow_negative' => 'required|boolean'],
            8 => ['date_range' => 'array:basis,date_format', 'date_range.basis' => 'required|in:calendar_days,working_days', 'date_range.date_format' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F]/']],
        };
        $validator = Validator::make($variable, $rules);
        $errors = array_merge($errors, $validator->errors()->messages());
        if ($errors !== []) {
            return $errors;
        }
        if ($type === 5) {
            foreach ($variable['radio']['options'] as $index => $option) {
                if (strlen($variable['name'] . ':' . $option['code']) > 100) {
                    $errors['radio.options.' . $index . '.code'] = ['Placeholder lengkap maksimal 100 karakter.'];
                }
            }
        }
        if (in_array($type, [6, 7], true)) {
            $numeric = $variable['numeric'];
            if (($numeric['mode'] === 'integer' && $numeric['scale'] !== 0) || ($numeric['mode'] === 'decimal' && ($numeric['scale'] < 1 || ($type === 7 && $numeric['scale'] !== 2)))) {
                $errors['numeric.scale'] = ['Presisi tidak sesuai mode angka.'];
            }
        }
        $suffixes = match ($type) {
            6,7 => ['terbilang'], 8 => ['mulai', 'akhir', 'jumlah_hari', 'terbilang', 'durasi'], default => []
        };
        foreach ($suffixes as $suffix) {
            if (strlen($variable['name'] . ':' . $suffix) > 100) {
                $errors['name'] = ['Placeholder lengkap maksimal 100 karakter.'];
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $data */
    public function save(LetterTemplateVariable $variable, array $data, ?LetterTemplateVariable $old = null): void
    {
        $oldOptions = $old?->radioOptions ?? collect();
        $radio = $data['radio'] ?? null;
        foreach ($radio['options'] ?? [] as $index => $option) {
            if (isset($option['id']) && !$oldOptions->contains('uuid', $option['id'])) {
                throw ValidationException::withMessages(['radio.options.' . $index . '.id' => 'Opsi bukan milik pertanyaan ini.']);
            }
        }
        $variable->fill(['radio' => $radio === null ? null : ['selected_text' => $radio['selected_text']], 'numeric' => $data['numeric'] ?? null, 'date_range' => $data['date_range'] ?? null])->save();
        $variable->radioOptions()->delete();
        foreach ($radio['options'] ?? [] as $position => $option) {
            $model = $variable->radioOptions()->make(['code' => $option['code'], 'label' => $option['label'], 'sort_order' => $position]);
            if (isset($option['id'])) {
                $model->uuid = $option['id'];
            }
            $model->save();
        }
        $variable->unsetRelation('radioOptions');
    }
}
