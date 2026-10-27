<?php

namespace App\Http\Controllers;

use App\Services\Release\ReadReleaseNotes;
use Inertia\Response as InertiaResponse;

class AboutController extends Controller
{
    public function index(ReadReleaseNotes $notes): InertiaResponse
    {
        return inertia('about/index', [
            'page' => ['id' => 'about'],
            'releases' => $notes->read(),
        ]);
    }
}
