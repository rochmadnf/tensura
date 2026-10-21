<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Inertia\Response as InertiaResponse;

class LetterNumberController extends Controller
{
    public function index(): InertiaResponse
    {
        return inertia('letter-number/index', [
            'page' => ['id' => 'a3063dab-44a6-4de2-9c93-8fa84742e3da'],
        ]);
    }
}
