<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\ValidateLetterTemplateDocx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LetterTemplateValidationController extends Controller
{
    public function __invoke(Request $request, LetterTemplate $letterTemplate, LetterTemplateVersion $version, ValidateLetterTemplateDocx $validation): RedirectResponse
    {
        $result = $validation->validate($letterTemplate, $version, $request->user());
        $response = redirect()->route('lt.edit', $letterTemplate->uuid);

        return $result['valid'] ? $response : $response->withErrors(['docx' => $result['errors'][0]]);
    }
}
