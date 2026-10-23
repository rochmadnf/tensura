<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterData\JobTitleController;
use App\Http\Controllers\MasterData\LetterNumberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('job-title', JobTitleController::class)
        ->except('show')
        ->parameters(['job-title' => 'jobTitle'])
        ->scoped(['jobTitle' => 'uuid'])
        ->names('jt');

    Route::controller(LetterNumberController::class)
        ->prefix('letter-number')
        ->group(function () {
            Route::get('/', 'index')->name('ln.index');
            Route::get('/create', 'create')->name('ln.create');
            Route::post('/store', 'store')->name('ln.store');
            Route::get('/{letterNumber:uuid}/edit', 'edit')->name('ln.edit');
            Route::patch('/{letterNumber:uuid}', 'update')->name('ln.update');
            Route::delete('/{letterNumber:uuid}', 'destroy')->name('ln.destroy');
        });
});

require __DIR__ . '/auth.php';
