<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthenticatedUserController;

Route::controller(AuthenticatedUserController::class)->group(function () {
    Route::middleware('auth')->group(function () {
        Route::post('/logout', 'logout')->name('logout');
    });

    Route::middleware('guest')->group(function () {
        Route::get('/login', 'index')->name('login.index');
        Route::post('/login', 'login')->name('login.post');
    });
});
