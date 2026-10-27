<?php

namespace App\Http\Controllers;

use Inertia\Response as InertiaResponse;

class AboutController extends Controller
{
    public function index(): InertiaResponse
    {
        return inertia('about/index', [
            'page' => ['id' => 'about'],
            'releases' => collect(config('releases'))
                ->map(fn(array $release, string $version): array => ['version' => $version, ...$release])
                ->values()
                ->all(),
        ]);
    }
}
