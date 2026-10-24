<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderReferenceItemsRequest;
use App\Http\Requests\StoreReferenceItemRequest;
use App\Http\Requests\UpdateReferenceItemRequest;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Services\Archive\ArchiveReference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferenceItemController extends Controller
{
    protected string $pageId = 'a3063dcc-2750-45e0-8ea7-c859c50bd3fb';

    public function store(StoreReferenceItemRequest $request, ReferenceGroup $referenceGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $referenceGroup): void {
            ReferenceGroup::query()->whereKey($referenceGroup->id)->where('uuid', $referenceGroup->uuid)->lockForUpdate()->firstOrFail();
            $referenceGroup
                ->items()
                ->create([
                    ...$request->safe()->only(['content']),
                    'sort_order' => (int) $referenceGroup->items()->max('sort_order') + 1,
                ]);
        }, 5);

        return back();
    }

    public function update(
        UpdateReferenceItemRequest $request,
        ReferenceGroup $referenceGroup,
        ReferenceItem $item,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $referenceGroup, $item): void {
            ReferenceGroup::query()->whereKey($referenceGroup->id)->where('uuid', $referenceGroup->uuid)->lockForUpdate()->firstOrFail();
            $referenceGroup->items()->whereKey($item->id)->where('uuid', $item->uuid)->firstOrFail()
                ->update($request->safe()->only(['content']));
        }, 5);

        return back();
    }

    public function destroy(Request $request, ReferenceGroup $referenceGroup, ReferenceItem $item, ArchiveReference $archive): RedirectResponse
    {
        $archive->item($referenceGroup, $item, $request->user());

        return back();
    }

    public function reorder(ReorderReferenceItemsRequest $request, ReferenceGroup $referenceGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $referenceGroup): void {
            ReferenceGroup::query()->whereKey($referenceGroup->id)->where('uuid', $referenceGroup->uuid)->lockForUpdate()->firstOrFail();
            $request->assertCurrentItems();

            foreach ($request->validated('ids') as $index => $uuid) {
                $referenceGroup
                    ->items()
                    ->where('uuid', $uuid)
                    ->update(['sort_order' => $index + 1]);
            }
        }, 5);

        return back();
    }
}
