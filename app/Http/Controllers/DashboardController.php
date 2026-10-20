<?php

namespace App\Http\Controllers;

use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(): InertiaResponse
    {
        return inertia('welcome', [
            'page' => [
                'id' => 'a3062fa9-4422-44a7-834c-1e5471ed1f07',
            ],
        ]);
    }
}
