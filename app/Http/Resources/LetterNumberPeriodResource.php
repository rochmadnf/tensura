<?php

namespace App\Http\Resources;

use App\Models\LetterNumberPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LetterNumberPeriod */
class LetterNumberPeriodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: string, year: int, month: int, label: string, last_position: ?string, next_position: ?string}
     */
    public function toArray(Request $request): array
    {
        $next = $this->resource->nextPosition();
        $format = fn(int $page, int $sequence): string => sprintf('%02d.%02d', $page, $sequence);

        return [
            'id' => $this->uuid, 'year' => $this->year, 'month' => $this->month,
            'label' => CarbonImmutable::create($this->year, $this->month, 1)->locale(config('app.locale'))->translatedFormat('F Y'),
            'last_position' => $this->last_page_number === null ? null : $format($this->last_page_number, $this->last_sequence_number),
            'next_position' => $next === null ? null : $format($next['page'], $next['sequence']),
        ];
    }
}
