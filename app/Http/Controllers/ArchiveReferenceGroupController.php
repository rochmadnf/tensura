<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateArchivedReferenceGroupRequest;
use App\Http\Resources\ArchivedReferenceGroupResource;
use App\Models\Archive;
use App\Services\Archive\RenameArchivedReferenceGroup;

class ArchiveReferenceGroupController extends Controller
{
    public function __invoke(UpdateArchivedReferenceGroupRequest $request, Archive $archive, RenameArchivedReferenceGroup $rename): ArchivedReferenceGroupResource
    {
        return ArchivedReferenceGroupResource::make($rename->rename($archive, $request->validated('name')));
    }
}
