<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Resources\LetterNumberAssignmentResource;
use App\Models\LetterNumber;
use App\Models\LetterNumberPeriod;
use Illuminate\Http\Request;
use Inertia\Response;

class LetterNumberUsageController extends Controller
{
    public function __invoke(Request $request, LetterNumber $letterNumber, LetterNumberPeriod $period): Response
    {
        abort_unless($period->letter_number_id === $letterNumber->id, 404);
        $perPage = in_array($request->integer('per_page'), [5, 10, 15, 20, 50, 100]) ? $request->integer('per_page') : 10;
        $claims = $period->assignments()->with(['letter', 'versions' => fn($query) => $query->orderBy('version_number')])->orderByDesc('claimed_at')->orderByDesc('id')->paginate($perPage)->withQueryString();

        return inertia('letter-number/usage', ['page' => ['id' => 'a3063dab-44a6-4de2-9c93-8fa84742e3da'], 'classification' => ['id' => $letterNumber->uuid, 'code' => $letterNumber->classification_code, 'name' => $letterNumber->classification_name], 'period' => ['id' => $period->uuid, 'year' => $period->year, 'month' => $period->month], 'resources' => LetterNumberAssignmentResource::collection($claims)]);
    }
}
