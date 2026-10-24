<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteArchiveRequest;
use App\Http\Resources\ArchiveDeleteResource;
use App\Models\Archive;
use App\Services\Archive\DeleteArchivedRecords;

class ArchiveDestroyController extends Controller
{
    public function __invoke(DeleteArchiveRequest $request, Archive $archive, DeleteArchivedRecords $delete): ArchiveDeleteResource
    {
        return ArchiveDeleteResource::make($delete->delete(
            $archive,
            $request->user(),
            $request->validated('confirmation_token'),
            $request->safe()->except(['confirmation_token']),
        ));
    }
}
