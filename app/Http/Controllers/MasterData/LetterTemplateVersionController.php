<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeLetterTemplateVersionRequest;
use App\Http\Requests\PublishLetterTemplateVersionRequest;
use App\Http\Requests\UpdateLetterTemplateActivationRequest;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\ManageLetterTemplateVersions;
use Illuminate\Http\RedirectResponse;

class LetterTemplateVersionController extends Controller
{
    public function publish(PublishLetterTemplateVersionRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, ManageLetterTemplateVersions $versions): RedirectResponse
    {
        $version = $versions->publish($letterTemplate, $version, $request->validated('version_token'), $request->validated('test_token'), $request->user());

        return redirect()->route('lt.edit', ['letterTemplate' => $letterTemplate->uuid, 'version' => $version->uuid]);
    }

    public function draft(ChangeLetterTemplateVersionRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, ManageLetterTemplateVersions $versions): RedirectResponse
    {
        $draft = $versions->draft($letterTemplate, $version, $request->validated('version_token'), $request->user());

        return redirect()->route('lt.edit', ['letterTemplate' => $letterTemplate->uuid, 'version' => $draft->uuid]);
    }

    public function archive(ChangeLetterTemplateVersionRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, ManageLetterTemplateVersions $versions): RedirectResponse
    {
        $versions->archive($letterTemplate, $version, $request->validated('version_token'), $request->user());

        return redirect()->route('lt.edit', ['letterTemplate' => $letterTemplate->uuid, 'version' => $version->uuid]);
    }

    public function activation(UpdateLetterTemplateActivationRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, ManageLetterTemplateVersions $versions): RedirectResponse
    {
        $versions->activation($letterTemplate, $version, (bool) $request->validated('is_active'), $request->user());

        return redirect()->route('lt.edit', ['letterTemplate' => $letterTemplate->uuid, 'version' => $version->uuid]);
    }
}
