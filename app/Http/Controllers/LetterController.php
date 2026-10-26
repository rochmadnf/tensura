<?php

namespace App\Http\Controllers;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Http\FilterQuery;
use App\Http\Requests\DestroyLetterDraftRequest;
use App\Http\Requests\StoreLetterRequest;
use App\Http\Requests\UpdateLetterDraftRequest;
use App\Http\Resources\LetterResource;
use App\Models\Letter;
use App\Models\MasterData\LetterTemplate;
use App\Services\Letter\LetterFormDefinition;
use App\Services\Letter\ManageLetterDraft;
use App\Services\Letter\ManageLetterIssuance;
use App\Services\Letter\ManageLetterPreview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class LetterController extends Controller
{
    private const string PAGE_ID = 'a306324d-b2b7-401c-a2be-7b8ed37fdf62';

    public function index(Request $request): Response
    {
        $parsed = FilterQuery::parse($request->query('filter'));
        $status = collect($parsed['status'] ?? [])->first(fn(string $value): bool => in_array($value, ['1', '2', '3'], true));
        $template = collect($parsed['template'] ?? [])->first(fn(string $value): bool => Str::isUuid($value));
        $creator = collect($parsed['creator'] ?? [])->first(fn(string $value): bool => Str::isUuid($value));
        $year = collect($parsed['year'] ?? [])->first(fn(string $value): bool => ctype_digit($value) && (int) $value >= 1 && (int) $value <= 9999);
        $month = collect($parsed['month'] ?? [])->first(fn(string $value): bool => ctype_digit($value) && (int) $value >= 1 && (int) $value <= 12);
        $keyword = Letter::normalizeSearchKeyword($request->query('keyword'));
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100]) ? $request->integer('per_page') : 10;
        $letters = Letter::query()->with(['template', 'draft.templateVersion', 'currentVersion'])
            ->search($keyword)->when($status, fn(Builder $query): Builder => $query->where('status', (int) $status))
            ->when($template, fn(Builder $query): Builder => $query->whereHas('template', fn(Builder $query): Builder => $query->where('uuid', $template)))
            ->when($creator, fn(Builder $query): Builder => $query->where('creator_uuid', $creator))
            ->when($year || $month, fn(Builder $query): Builder => $query->where(fn(Builder $query): Builder => $query->whereHas('currentVersion', fn(Builder $query): Builder => $query->when($year, fn(Builder $query): Builder => $query->where('number_year', (int) $year))->when($month, fn(Builder $query): Builder => $query->where('number_month', (int) $month)))->orWhere(fn(Builder $query): Builder => $query->whereNull('current_version_id')->whereHas('draft', fn(Builder $query): Builder => $query->when($year, fn(Builder $query): Builder => $query->where('number_year', (int) $year))->when($month, fn(Builder $query): Builder => $query->where('number_month', (int) $month))))))
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate($perPage)->withQueryString();

        return inertia('letters/index', ['page' => ['id' => self::PAGE_ID], 'resources' => LetterResource::collection($letters), 'filters' => compact('keyword', 'status', 'template', 'creator', 'year', 'month'), 'selectedOptions' => ['templates' => LetterTemplate::query()->where('uuid', $template)->get(['uuid', 'name'])->map(fn($record): array => ['value' => $record->uuid, 'label' => $record->name]), 'creators' => Letter::query()->where('creator_uuid', $creator)->get(['creator_uuid', 'creator_name'])->unique('creator_uuid')->map(fn($record): array => ['value' => $record->creator_uuid, 'label' => $record->creator_name])->values()]]);
    }

    public function create(Request $request, LetterFormDefinition $forms): Response
    {
        $data = $request->validate(['template' => ['nullable', 'uuid']]);
        $template = isset($data['template']) ? LetterTemplate::query()->where('uuid', $data['template'])->where('is_active', true)->whereHas('activeVersion', fn(Builder $query): Builder => $query->where('status', LetterTemplateVersionStatusEnum::PUBLISHED))->with('activeVersion.letterNumber')->firstOrFail() : null;

        return inertia('letters/create', ['page' => ['id' => self::PAGE_ID], 'selectedTemplate' => $template === null ? null : ['id' => $template->uuid, 'name' => $template->name], 'configuration' => $template === null ? null : $forms->make($template->activeVersion), 'today' => now()->toDateString()]);
    }

    public function store(StoreLetterRequest $request, ManageLetterDraft $drafts): RedirectResponse
    {
        $letter = $drafts->create($request->validated(), $request->user());

        return redirect()->route('ltr.show', $letter->uuid);
    }

    public function show(Request $request, Letter $letter, ManageLetterPreview $previews, ManageLetterIssuance $issuance): Response
    {
        $letter->load(['template', 'draft.templateVersion.letterNumber', 'currentVersion.wordArtifact']);

        return inertia('letters/show', ['page' => ['id' => self::PAGE_ID], 'resource' => LetterResource::make($letter), 'issuance' => fn(): array => $issuance->detail($letter, $request->user()), 'preview' => fn(): array => $previews->detail($letter, $request->user()), 'audits' => fn() => $letter->audits()->orderByDesc('occurred_at')->orderByDesc('id')->paginate(10)->through(fn($audit): array => ['id' => $audit->uuid, 'actor' => $audit->actor_name, 'event' => $audit->event, 'description' => $audit->description, 'revision' => $audit->draft_revision, 'changes' => $audit->changes['entries'] ?? [], 'occurred_at' => $audit->occurred_at->toIso8601String()])->withQueryString()]);
    }

    public function edit(Letter $letter): RedirectResponse
    {
        return redirect()->route('ltr.show', $letter->uuid);
    }

    public function update(UpdateLetterDraftRequest $request, Letter $letter, ManageLetterDraft $drafts): RedirectResponse
    {
        $drafts->update($letter, $request->validated(), $request->user());

        return redirect()->route('ltr.show', $letter->uuid);
    }

    public function destroy(DestroyLetterDraftRequest $request, Letter $letter, ManageLetterDraft $drafts): RedirectResponse
    {
        $drafts->destroy($letter, (int) $request->validated('revision'));

        return redirect()->route('ltr.index');
    }
}
