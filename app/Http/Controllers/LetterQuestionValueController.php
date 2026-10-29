<?php

namespace App\Http\Controllers;

use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\ResolveLetterQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LetterQuestionValueController extends Controller
{
    public function store(Request $request, ResolveLetterQuestion $resolver): JsonResponse
    {
        try {
            $input = $request->validate(['template_version' => 'required|uuid', 'values' => 'present|array|max:200']);
            $version = LetterTemplateVersion::query()->where('uuid', $input['template_version'])->firstOrFail();
            $variables = $version->variables()->with('radioOptions')->get()->keyBy('uuid');
            $fields = [];
            foreach ($input['values'] as $id => $value) {
                $variable = $variables->get($id);
                if ($variable === null || !in_array($variable->type->value, [5, 6, 7, 8], true)) {
                    throw ValidationException::withMessages(['values.' . $id => 'Pertanyaan tidak tersedia untuk perhitungan ini.']);
                }
                try {
                    $fields[] = ['id' => $id, ...$resolver->resolve($variable, $value)];
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(['values.' . $id => collect($e->errors())->flatten()->all()]);
                }
            }

            return response()->json(['data' => ['contract_version' => 1, 'fields' => $fields]]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'validation_failed', 'errors' => $e->errors()], 422);
        }
    }
}
