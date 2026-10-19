<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->get('/', function () {
        return inertia('welcome');
    })
    ->name('dashboard');

require __DIR__ . '/auth.php';
