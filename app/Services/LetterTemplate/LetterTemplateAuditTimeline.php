<?php

namespace App\Services\LetterTemplate;

use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateAudit;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LetterTemplateAuditTimeline
{
    public function page(LetterTemplate $template, ?string $encoded = null): array
    {
        $cursor = $encoded === null ? null : Cursor::fromEncoded($encoded);
        if ($encoded !== null) {
            $parameters = $cursor?->toArray() ?? [];
            if (Validator::make($parameters, ['id' => ['required', 'integer', 'min:1'], 'occurred_at' => ['required', 'date_format:Y-m-d H:i:s']])->fails()) {
                throw ValidationException::withMessages(['cursor' => 'Halaman aktivitas tidak valid. Muat ulang aktivitas terbaru.']);
            }
        }
        $page = $template->audits()->with('version:id,version_number')->orderByDesc('occurred_at')->orderByDesc('id')->cursorPaginate(10, ['*'], 'cursor', $cursor);

        return [
            'data' => collect($page->items())->map(fn(LetterTemplateAudit $audit): array => [
                'id' => $audit->uuid, 'version' => $audit->version?->version_number,
                'actor' => $audit->actor_name, 'occurred_at' => $audit->occurred_at->toIso8601String(),
                'operation' => $audit->operation, 'description' => $audit->description, 'changes' => $audit->changes,
            ])->all(),
            'previous_cursor' => $page->previousCursor()?->encode(),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }
}
