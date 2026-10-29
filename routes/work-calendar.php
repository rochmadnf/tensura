<?php

use App\Http\Controllers\LetterQuestionValueController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::post('letter-question-values/resolve', [LetterQuestionValueController::class, 'store'])->middleware('throttle:60,1')->name('lt.values.resolve');
});
