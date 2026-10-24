<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterData\EmployeeController;
use App\Http\Controllers\MasterData\JobTitleController;
use App\Http\Controllers\MasterData\LetterNumberController;
use App\Http\Controllers\MasterData\ReferenceGroupController;
use App\Http\Controllers\MasterData\ReferenceItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('employees', EmployeeController::class)
        ->parameters(['employees' => 'employee'])
        ->scoped(['employee' => 'uuid'])
        ->names('empl');

    Route::resource('job-title', JobTitleController::class)
        ->except('show')
        ->parameters(['job-title' => 'jobTitle'])
        ->scoped(['jobTitle' => 'uuid'])
        ->names('jt');

    Route::resource('reference', ReferenceGroupController::class)
        ->except('show')
        ->parameters(['reference' => 'referenceGroup'])
        ->scoped(['referenceGroup' => 'uuid'])
        ->names('ref');

    Route::patch('reference/{referenceGroup:uuid}/items/reorder', [ReferenceItemController::class, 'reorder'])->name(
        'ref.item.reorder',
    );

    Route::resource('reference.items', ReferenceItemController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['reference' => 'referenceGroup', 'items' => 'item'])
        ->scoped(['referenceGroup' => 'uuid', 'item' => 'uuid'])
        ->names(['store' => 'ref.item.store', 'update' => 'ref.item.update', 'destroy' => 'ref.item.destroy']);

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
