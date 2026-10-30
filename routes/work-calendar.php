<?php

use App\Http\Controllers\LetterQuestionValueController;
use App\Http\Controllers\WorkCalendarController;
use App\Http\Controllers\WorkCalendarHolidayController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::post('letter-question-values/resolve', [LetterQuestionValueController::class, 'store'])->middleware('throttle:60,1')->name('lt.values.resolve');
});

Route::middleware(['auth'])->prefix('work-calendar')->name('wc.')->group(function (): void {
    $controller = WorkCalendarController::class;
    Route::get('', [$controller, 'index'])->name('index');
    Route::get('data', [$controller, 'data'])->name('data');
    Route::post('years', [$controller, 'store'])->name('years.store');
    Route::patch('years/{year}', [$controller, 'update'])->whereNumber('year')->name('years.update');
    Route::post('years/{year}/confirm', [$controller, 'confirm'])->whereNumber('year')->name('years.confirm');
    Route::post('events', [$controller, 'storeEvent'])->name('events.store');
    Route::put('events/{event}', [$controller, 'updateEvent'])->whereUuid('event')->name('events.update');
    Route::delete('events/{event}', [$controller, 'destroyEvent'])->whereUuid('event')->name('events.destroy');
    Route::post('years/{year}/holidays/preview', [WorkCalendarHolidayController::class, 'preview'])->whereNumber('year')->middleware('throttle:10,1')->name('holidays.preview');
    Route::post('years/{year}/holidays/apply', [WorkCalendarHolidayController::class, 'apply'])->whereNumber('year')->name('holidays.apply');
    Route::post('count-days', [$controller, 'count'])->name('count');
});
