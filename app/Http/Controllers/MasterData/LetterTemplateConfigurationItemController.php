<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeLetterTemplateConfigurationItemRequest;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\ChangeLetterTemplateConfigurationItem;
use Illuminate\Http\RedirectResponse;

class LetterTemplateConfigurationItemController extends Controller
{
    public function __invoke(ChangeLetterTemplateConfigurationItemRequest $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, ChangeLetterTemplateConfigurationItem $configuration): RedirectResponse
    {
        $configuration->change($letterTemplate, $version, $request->route('kind'), $request->route('operation'), $request->route('item'), $request->route('attribute'), $request->validated(), $request->user());

        return redirect()->route('lt.edit', $letterTemplate->uuid);
    }
}
