<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\FilterQuery;
use App\Http\Requests\StoreLetterTemplateRequest;
use App\Http\Requests\UpdateLetterTemplateRequest;
use App\Http\Resources\MasterData\LetterTemplateConfigurationResource;
use App\Http\Resources\MasterData\LetterTemplateDocumentResource;
use App\Http\Resources\MasterData\LetterTemplateResource;
use App\Models\LetterNumber;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Services\LetterTemplate\EmployeeAttributeCatalog;
use App\Services\LetterTemplate\SaveLetterTemplateIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class LetterTemplateController extends Controller
{
    protected string $pageId = 'a3063dc3-7414-4385-bf3f-0cf0742e3980';

    public function index(Request $request): InertiaResponse
    {
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100]) ? $request->integer('per_page') : 10;
        $keyword = LetterTemplate::normalizeSearchKeyword($request->query('keyword'));
        $parsed = FilterQuery::parse($request->query('filter'));
        $status = count($parsed['status'] ?? []) === 1 && in_array($parsed['status'][0], ['active', 'inactive'], true)
            ? $parsed['status'][0] : null;
        $classifications = $this->classifications();
        $classification = count($parsed['classification'] ?? []) === 1
            && in_array($parsed['classification'][0], array_column($classifications, 'id'), true)
            ? $parsed['classification'][0] : null;
        $records = LetterTemplate::query()
            ->with(['activeVersion', 'displayVersion.letterNumber', 'draftVersion.letterNumber'])
            ->when($status !== null, fn(Builder $query): Builder => $query->where('is_active', $status === 'active'))
            ->when($classification !== null, fn(Builder $query): Builder => $query->whereHas('displayVersion.letterNumber', fn(Builder $query): Builder => $query->where('uuid', $classification)))
            ->search($keyword)
            ->orderBy('name')->orderBy('id')
            ->paginate($perPage)->withQueryString();

        return inertia('letter-templates/index', [
            'page' => ['id' => $this->pageId],
            'filters' => ['keyword' => $keyword, 'status' => $status, 'classification' => $classification],
            'classifications' => $classifications,
            'resources' => LetterTemplateResource::collection($records),
        ]);
    }

    public function create(): InertiaResponse
    {
        return inertia('letter-templates/create', [
            'page' => ['id' => $this->pageId],
            'classifications' => $this->classifications(),
        ]);
    }

    public function store(StoreLetterTemplateRequest $request, SaveLetterTemplateIdentity $identity): RedirectResponse
    {
        $template = $identity->create($request->safe()->only(['name', 'letter_number']), $request->user());

        return redirect()->route('lt.edit', $template->uuid);
    }

    public function edit(LetterTemplate $letterTemplate): InertiaResponse
    {
        $letterTemplate->load(['activeVersion', 'displayVersion.letterNumber', 'draftVersion.letterNumber']);

        $version = $letterTemplate->draftVersion ?? $letterTemplate->displayVersion;
        $version?->load(['variables.referenceGroup', 'variables.columns', 'variables.employeeAttributes', 'signerSlots.candidates.employee', 'signerSlots.employeeAttributes']);
        $version?->loadExists(['audits as configuration_saved' => fn(Builder $query): Builder => $query->where('operation', 'configuration.updated')]);

        return inertia('letter-templates/edit', [
            'page' => ['id' => $this->pageId],
            'classifications' => $this->classifications(),
            'resource' => LetterTemplateResource::make($letterTemplate),
            'configuration' => $version === null ? null : LetterTemplateConfigurationResource::make($version),
            'document' => $version === null ? null : LetterTemplateDocumentResource::make($version),
            'documentUploadLimitKb' => config('letter-templates.max_upload_kb'),
            'documentVersions' => $letterTemplate->versions()->orderByDesc('version_number')->get(['uuid', 'version_number', 'status', 'source_path'])->map(fn(LetterTemplateVersion $item): array => ['id' => $item->uuid, 'number' => $item->version_number, 'status' => $item->status->label(), 'has_source' => $item->source_path !== null])->all(),
            'configurationOptions' => [
                'employeeAttributes' => EmployeeAttributeCatalog::options(),
                'types' => LetterTemplateVariableTypeEnum::options(),
                'sources' => LetterTemplateOptionSourceEnum::options(),
                'columns' => collect(LetterTemplateOptionSourceEnum::cases())->mapWithKeys(fn(LetterTemplateOptionSourceEnum $source): array => [$source->value => $source->columnOptions()])->all(),
                'referenceGroups' => ReferenceGroup::query()->orderBy('name')->orderBy('id')->get(['uuid', 'name'])->map(fn(ReferenceGroup $group): array => ['id' => $group->uuid, 'label' => $group->name])->all(),
                'employees' => Employee::query()->where('status', AsnStatusEnum::AKTIF)->orderBy('name')->orderBy('id')->get(['id', 'uuid', 'name', 'front_title', 'back_title'])->map(fn(Employee $employee): array => ['id' => $employee->uuid, 'label' => LetterTemplateConfigurationResource::employeeName($employee)])->all(),
            ],
        ]);
    }

    public function update(UpdateLetterTemplateRequest $request, LetterTemplate $letterTemplate, SaveLetterTemplateIdentity $identity): RedirectResponse
    {
        $identity->update($letterTemplate, $request->safe()->only(['name', 'letter_number']), $request->user());

        return redirect()->route('lt.edit', $letterTemplate->uuid);
    }

    /** @return list<array{id: string, label: string}> */
    private function classifications(): array
    {
        return LetterNumber::query()->orderBy('classification_code')->get(['uuid', 'classification_code', 'classification_name'])
            ->map(fn(LetterNumber $number): array => ['id' => $number->uuid, 'label' => $number->classification_code . ' — ' . $number->classification_name])->all();
    }
}
