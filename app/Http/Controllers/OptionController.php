<?php

namespace App\Http\Controllers;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Http\Requests\SearchOptionsRequest;
use App\Http\Resources\MasterData\LetterTemplateConfigurationResource;
use App\Models\LetterNumber;
use App\Models\MasterData\Employee;
use App\Models\MasterData\JobTitle;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class OptionController extends Controller
{
    public function __invoke(SearchOptionsRequest $request): JsonResponse
    {
        $source = $request->validated('source');
        $context = $request->validated('context');
        $query = match ($source) {
            'classifications' => LetterNumber::query(),
            'job-titles' => JobTitle::query()->when($request->validated('groups', []) !== [], fn(Builder $query): Builder => $query->whereIn('group', $request->validated('groups'))),
            'reference-groups' => ReferenceGroup::query(),
            'employees' => Employee::query()->where('status', AsnStatusEnum::AKTIF),
            'variable-options' => $this->variableOptions($context),
            'signer-candidates' => $this->signerCandidates($context),
        };
        $columns = match ($query->getModel()::class) {
            LetterNumber::class => ['classification_code', 'classification_name'],
            ReferenceItem::class => ['content'],
            Employee::class => ['name', 'front_title', 'back_title', 'nip'],
            default => ['name'],
        };
        $selected = (clone $query)->whereIn('uuid', $request->validated('selected', []))->get()->map($this->option(...))->values();
        $keyword = trim($request->validated('q') ?? '');
        if (mb_strlen($keyword) >= 2) {
            $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($keyword)) . '%';
            $query->where(function (Builder $query) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $column = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn($column));
                    $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$term]);
                }
            });
        }
        $order = $query->getModel() instanceof ReferenceItem ? 'sort_order' : $columns[0];

        return response()->json([
            'items' => $query->orderBy($order)->orderBy('id')->limit(10)->get()->map($this->option(...))->values(),
            'selected' => $selected,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function variableOptions(string $uuid): Builder
    {
        $variable = LetterTemplateVariable::query()->where('uuid', $uuid)->firstOrFail();
        abort_unless($variable->option_source !== null, 404);

        return $variable->option_source === LetterTemplateOptionSourceEnum::EMPLOYEE
            ? Employee::query()->where('status', AsnStatusEnum::AKTIF)
            : ReferenceItem::query()->where('reference_group_id', $variable->reference_group_id);
    }

    private function signerCandidates(string $uuid): Builder
    {
        $slot = LetterTemplateSignerSlot::query()->where('uuid', $uuid)->firstOrFail();

        return Employee::query()->where('status', AsnStatusEnum::AKTIF)
            ->whereIn('id', $slot->candidates()->select('employee_id'));
    }

    /** @return array{value: string, label: string, group?: ?int} */
    private function option(Model $model): array
    {
        return [...($model instanceof JobTitle ? ['group' => $model->group?->value] : []), 'value' => $model->uuid, 'label' => match (true) {
            $model instanceof LetterNumber => $model->classification_code . ' — ' . $model->classification_name,
            $model instanceof Employee => LetterTemplateConfigurationResource::employeeName($model),
            $model instanceof ReferenceItem => $model->content,
            default => $model->name,
        }];
    }
}
