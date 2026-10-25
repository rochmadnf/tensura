<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLetterTemplateConfigurationRequest;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\SaveLetterTemplateConfiguration;
use Illuminate\Http\RedirectResponse;

class LetterTemplateConfigurationController extends Controller
{
    public function __invoke(UpdateLetterTemplateConfigurationRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, SaveLetterTemplateConfiguration $configuration): RedirectResponse
    {
        $configuration->save($letterTemplate, $version, $request->safe()->only(['variables', 'signer_slots']), $request->user());

        return redirect()->route('lt.edit', $letterTemplate->uuid);
    }
}
