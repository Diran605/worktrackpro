<?php

use Illuminate\Support\Facades\Route;

// PDF Export/Preview Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/reports/worker/{user}/pdf', [\App\Http\Controllers\ReportController::class, 'workerPdf'])->name('reports.worker-pdf');
    Route::get('/reports/team/pdf', [\App\Http\Controllers\ReportController::class, 'teamPdf'])->name('reports.team-pdf');
});

// Portal -> admin panel sign-in hand-off, and ending the admin session when the portal logs out.
Route::middleware('signed')->group(function () {
    Route::get('/admin-handoff', [\App\Http\Controllers\AdminHandoffController::class, 'enter'])->name('admin.handoff');
    Route::get('/admin-session/end', [\App\Http\Controllers\AdminHandoffController::class, 'end'])->name('admin.session-end');
});

// Knowledge-base files. Links are signed and short-lived; the API only hands them to permitted users.
Route::middleware('signed')->prefix('kb')->group(function () {
    Route::get('assets/{asset}', [\App\Http\Controllers\KnowledgeFileController::class, 'asset'])->name('kb.asset');
    Route::get('revisions/{revision}/file', [\App\Http\Controllers\KnowledgeFileController::class, 'revisionFile'])->name('kb.revision-file');
    Route::get('changes/{change}/file', [\App\Http\Controllers\KnowledgeFileController::class, 'changeFile'])->name('kb.change-file');
});

// For the MVP, everything running on the web routes acts as a catch-all
// pointing to the Vue 3 Single Page Application shell.
Route::get('{any}', function () {
    return view('app');
})->where('any', '.*');
