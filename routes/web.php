<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterData\LetterNumberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::controller(LetterNumberController::class)
        ->prefix('letter-number')
        ->group(function () {
            Route::get('/', 'index')->name('ln.index');
        });
});

require __DIR__ . '/auth.php';
