<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueLetterRequest;
use App\Http\Resources\LetterOperationResource;
use App\Models\Letter;
use App\Services\Letter\ManageLetterIssuance;
use Illuminate\Http\JsonResponse;

class LetterIssuanceController extends Controller
{
    public function store(IssueLetterRequest $request, Letter $letter, ManageLetterIssuance $issuance): JsonResponse
    {
        return response()->json(['operation' => (new LetterOperationResource($issuance->request($letter, $request->validated(), $request->user())))->resolve()], 202);
    }
}
