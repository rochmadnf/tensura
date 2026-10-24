<?php

use App\Http\Controllers\AccountAvatarController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ArchiveDestroyController;
use App\Http\Controllers\ArchivePreviewController;
use App\Http\Controllers\ArchiveReferenceGroupController;
use App\Http\Controllers\ArchiveRestoreController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterData\EmployeeController;
use App\Http\Controllers\MasterData\JobTitleController;
use App\Http\Controllers\MasterData\LetterNumberController;
use App\Http\Controllers\MasterData\ReferenceGroupController;
use App\Http\Controllers\MasterData\ReferenceItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('archives', [ArchiveController::class, 'index'])->name('archives.index');
    Route::get('reference/archive', [ArchiveController::class, 'index'])->name('ref.archive.index');
    Route::get('archives/{archive:uuid}', [ArchiveController::class, 'show'])->name('archives.show');
    Route::post('archives/{archive:uuid}/preview', ArchivePreviewController::class)->name('archives.preview');
    Route::post('archives/{archive:uuid}/restore', ArchiveRestoreController::class)->name('archives.restore');
    Route::delete('archives/{archive:uuid}', ArchiveDestroyController::class)->name('archives.destroy');
    Route::patch('archives/{archive:uuid}/reference-group', ArchiveReferenceGroupController::class)->name('archives.reference-group.update');

    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('account/profile', [AccountController::class, 'update'])->name('account.profile.update');
    Route::put('account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
    Route::post('account/avatar', [AccountAvatarController::class, 'store'])->name('account.avatar.store');
    Route::delete('account/avatar', [AccountAvatarController::class, 'destroy'])->name('account.avatar.destroy');

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
