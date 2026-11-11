<?php

use Illuminate\Support\Facades\Route;

// PDF Export/Preview Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/reports/worker/{user}/pdf', [\App\Http\Controllers\ReportController::class, 'workerPdf'])->name('reports.worker-pdf');
    Route::get('/reports/team/pdf', [\App\Http\Controllers\ReportController::class, 'teamPdf'])->name('reports.team-pdf');
});

// For the MVP, everything running on the web routes acts as a catch-all 
// pointing to the Vue 3 Single Page Application shell.
Route::get('{any}', function () {
    return view('app');
})->where('any', '.*');
