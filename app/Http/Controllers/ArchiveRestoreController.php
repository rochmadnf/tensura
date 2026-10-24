<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestoreArchiveRequest;
use App\Http\Resources\ArchiveRestoreResource;
use App\Models\Archive;
use App\Services\Archive\RestoreReference;

class ArchiveRestoreController extends Controller
{
    public function __invoke(RestoreArchiveRequest $request, Archive $archive, RestoreReference $restore): ArchiveRestoreResource
    {
        return ArchiveRestoreResource::make($restore->restore(
            $archive,
            $request->user(),
            $request->validated('confirmation_token'),
            $request->safe()->except(['confirmation_token']),
        ));
    }
}
