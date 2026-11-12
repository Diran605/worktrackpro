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
    Route::get('/inbox/attachments/{attachment}', [\App\Http\Controllers\InboxAttachmentController::class, 'download'])->name('inbox.attachment');
});

// Knowledge-base files. Links are signed and short-lived; the API only hands them to permitted users.
Route::middleware('signed')->prefix('kb')->group(function () {
    Route::get('assets/{asset}', [\App\Http\Controllers\KnowledgeFileController::class, 'asset'])->name('kb.asset');
    Route::get('revisions/{revision}/file', [\App\Http\Controllers\KnowledgeFileController::class, 'revisionFile'])->name('kb.revision-file');
    Route::get('changes/{change}/file', [\App\Http\Controllers\KnowledgeFileController::class, 'changeFile'])->name('kb.change-file');
    Route::get('exports/{export}', [\App\Http\Controllers\KnowledgeFileController::class, 'export'])->name('kb.export');
});

// Public documentation library (docs.fortinet.com style). The same pages are served for the
// default organisation (or an organisation's own docs domain) at "/", and for any organisation
// under /org/{slug}. ResolveDocsOrganisation decides which one.
$docsRoutes = function () {
    Route::get('/', [\App\Http\Controllers\Docs\HomeController::class, 'index'])->name('home');
    Route::get('docs/search', [\App\Http\Controllers\Docs\SearchController::class, 'index'])->name('search');
    Route::get('docs/access', [\App\Http\Controllers\Docs\AccessController::class, 'show'])->name('access');
    Route::post('docs/access', [\App\Http\Controllers\Docs\AccessController::class, 'unlock'])->middleware('throttle:5,1')->name('access.unlock');
    Route::post('docs/access/forget', [\App\Http\Controllers\Docs\AccessController::class, 'forget'])->name('access.forget');
    Route::get('docs/{system}', [\App\Http\Controllers\Docs\SystemController::class, 'show'])->name('system');
    Route::get('docs/{system}/changes', [\App\Http\Controllers\Docs\SystemController::class, 'changes'])->name('changes');
    Route::get('docs/{system}/pdf', [\App\Http\Controllers\Docs\SystemController::class, 'pdf'])->name('pdf');
    Route::post('docs/{system}/request-access', [\App\Http\Controllers\Docs\AccessController::class, 'request'])->middleware('throttle:3,10')->name('request-access');
    Route::get('docs/{system}/{section}', [\App\Http\Controllers\Docs\SectionController::class, 'show'])->name('section');
    Route::post('docs/{system}/{section}/feedback', [\App\Http\Controllers\Docs\SectionController::class, 'feedback'])->middleware('throttle:20,1')->name('feedback');
    Route::get('sitemap.xml', [\App\Http\Controllers\Docs\SitemapController::class, 'index'])->name('sitemap');
};

Route::middleware(\App\Http\Middleware\ResolveDocsOrganisation::class)
    ->prefix('org/{organisation}')
    ->name('docs.org.')
    ->group($docsRoutes);

Route::middleware(\App\Http\Middleware\ResolveDocsOrganisation::class)
    ->name('docs.')
    ->group($docsRoutes);

// Staff portal -> docs library sign-in (so staff see restricted manuals there too).
Route::get('/docs-handoff', [\App\Http\Controllers\AdminHandoffController::class, 'enterDocs'])
    ->middleware('signed')
    ->name('docs.handoff');

// Staff portal (Vue single-page app). It used to live at "/"; the public documentation
// library now has the front of the site.
Route::get('/staff/{any?}', fn () => view('app'))->where('any', '.*')->name('staff');

// Old portal addresses keep working (bookmarks, links in old messages).
foreach (['login', 'plans', 'logs', 'team', 'settings', 'inbox', 'recurring-tasks'] as $oldPath) {
    Route::permanentRedirect("/{$oldPath}", "/staff/{$oldPath}");
}
Route::get('/knowledge/{rest?}', fn (?string $rest = null) => redirect('/staff/knowledge' . ($rest ? "/{$rest}" : ''), 301))
    ->where('rest', '.*');
