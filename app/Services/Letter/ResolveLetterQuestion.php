<?php

namespace App\Services\Letter;

use App\Models\MasterData\LetterTemplateVariable;
use App\Services\LetterTemplate\FormatEmployeeDate;
use App\Services\WorkCalendar\CountWorkCalendarDays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResolveLetterQuestion
{
    public function __construct(private SpellLetterNumber $numbers) {}

    /** @return list<string> */
    public function names(LetterTemplateVariable $variable): array
    {
        return match ($variable->type->value) {
            5 => $variable->radioOptions->map(fn($o): string => $variable->name . ':' . $o->code)->all(),
            6, 7 => [$variable->name, $variable->name . ':terbilang'],
            8 => array_map(fn(string $suffix): string => $variable->name . ':' . $suffix, ['mulai', 'akhir', 'jumlah_hari', 'terbilang', 'durasi']),
            default => [$variable->name],
        };
    }

    /** @return array{value: mixed, display: string, words: ?string, tokens: array<string,string>, calendar_state: ?array} */
    public function resolve(LetterTemplateVariable $variable, mixed $input): array
    {
        if ($input === null || $input === '') {
            if ($variable->is_required) {
                throw ValidationException::withMessages(['value' => 'Pertanyaan wajib diisi.']);
            }

            return ['value' => null, 'display' => '', 'words' => null, 'tokens' => array_fill_keys($this->names($variable), ''), 'calendar_state' => null];
        }
        if ($variable->type->value === 5) {
            $option = is_string($input) ? $variable->radioOptions->firstWhere('code', $input) : null;
            if ($option === null) {
                throw ValidationException::withMessages(['value' => 'Pilihan bukan milik pertanyaan ini.']);
            }
            $tokens = [];
            foreach ($variable->radioOptions as $choice) {
                $tokens[$variable->name . ':' . $choice->code] = $choice->code === $input ? $variable->radio['selected_text'] : '';
            }

            return ['value' => $input, 'display' => $option->label, 'words' => null, 'tokens' => $tokens, 'calendar_state' => null];
        }
        if ($variable->type->value === 8) {
            Validator::make(['value' => $input], ['value' => 'required|array:start_date,end_date', 'value.start_date' => 'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2199-12-31', 'value.end_date' => 'required|date_format:Y-m-d|after_or_equal:value.start_date|before_or_equal:2199-12-31'])->validate();
            $result = app(CountWorkCalendarDays::class)->count($input['start_date'], $input['end_date'], $variable->date_range['basis']);
            $format = $variable->date_range['date_format'];
            $tokens = [];
            foreach (['mulai' => app(FormatEmployeeDate::class)->format(CarbonImmutable::parse($input['start_date']), $format), 'akhir' => app(FormatEmployeeDate::class)->format(CarbonImmutable::parse($input['end_date']), $format), 'jumlah_hari' => (string) $result['days'], 'terbilang' => $result['words'], 'durasi' => $result['duration']] as $suffix => $text) {
                $tokens[$variable->name . ':' . $suffix] = $text;
            }

            return ['value' => $input, 'display' => $result['duration'], 'words' => $result['words'], 'tokens' => $tokens, 'calendar_state' => $result['calendar_state']];
        }
        if (!is_string($input)) {
            throw ValidationException::withMessages(['value' => 'Nilai angka harus berupa teks desimal.']);
        }
        $number = $this->numbers->resolve($input, $variable->numeric, $variable->type->value === 7);

        return [...$number, 'tokens' => [$variable->name => $number['display'], $variable->name . ':terbilang' => $number['words']], 'calendar_state' => null];
    }
}
