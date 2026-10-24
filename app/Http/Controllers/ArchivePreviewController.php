<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewArchiveRequest;
use App\Http\Resources\ArchivePreviewResource;
use App\Models\Archive;
use App\Services\Archive\ArchiveConfirmation;
use App\Services\Archive\PreviewArchive;

class ArchivePreviewController extends Controller
{
    public function __invoke(PreviewArchiveRequest $request, Archive $archive, PreviewArchive $preview, ArchiveConfirmation $confirmation): ArchivePreviewResource
    {
        $plan = $preview->build($archive, $request->validated());
        $plan['confirmation_token'] = $confirmation->issue($archive, $request->user(), $plan);

        return ArchivePreviewResource::make($plan);
    }
}
