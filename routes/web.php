<?php

use App\Http\Controllers\CaseAttachmentController;
use App\Http\Controllers\HelpGuideController;
use App\Http\Controllers\HistoryAttachmentController;
use App\Http\Controllers\HistoryReportController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SalesOrderReportController;
use App\Http\Controllers\TicketController;
use App\Livewire\CaseWorkspace;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

Route::middleware(['auth', 'network.context'])->group(function (): void {
    Route::get('/cases/{case}', CaseWorkspace::class)->name('cases.show');
    Route::get('/cases/{case}/ticket', [TicketController::class, 'show'])->name('cases.ticket');
    Route::get('/cases/{case}/ticket/pdf', [TicketController::class, 'pdf'])->name('cases.ticket.pdf');
    Route::get('/cases/{case}/informe', [TicketController::class, 'report'])->name('cases.report');
    Route::get('/cases/{case}/attachments/{media}', [CaseAttachmentController::class, 'show'])->name('cases.attachments.show');
    Route::get('/history-entries/{entry}/attachments/{media}', [HistoryAttachmentController::class, 'show'])->name('history.attachments.show');
    Route::get('/history-entries/{entry}/pdf', [HistoryReportController::class, 'entry'])->name('history.entries.pdf');
    Route::get('/subjects/{subject}/history.pdf', [HistoryReportController::class, 'subject'])->name('history.subjects.pdf');
    Route::get('/sales-orders/{order}/pdf', [SalesOrderReportController::class, 'pdf'])->name('sales.orders.pdf');
    Route::get('/sales-orders/{order}/csv', [SalesOrderReportController::class, 'csv'])->name('sales.orders.csv');
    Route::get('/capacitacion/{article}/pdf', [HelpGuideController::class, 'pdf'])->name('help.articles.pdf');
});

Route::prefix('red/{networkSlug}')
    ->middleware('network.context')
    ->group(function (): void {
        Route::get('/', [PublicSiteController::class, 'home'])->name('public.home');
        Route::get('/sedes', [PublicSiteController::class, 'organizations'])->name('public.organizations');
        Route::get('/sedes/{organizationSlug}', [PublicSiteController::class, 'organization'])->name('public.organization');
        Route::get('/especialistas', [PublicSiteController::class, 'specialists'])->name('public.specialists');
        Route::get('/especialistas/{party}', [PublicSiteController::class, 'specialist'])->name('public.specialist');
        Route::get('/blog', [PublicSiteController::class, 'posts'])->name('public.posts');
        Route::get('/blog/{slug}', [PublicSiteController::class, 'post'])->name('public.post');
        Route::get('/paginas/{slug}', [PublicSiteController::class, 'page'])->name('public.page');
        Route::get('/ayuda', [PublicSiteController::class, 'help'])->name('public.help');
        Route::middleware('auth')->group(function (): void {
            Route::get('/tutoriales', [PublicSiteController::class, 'tutorials'])->name('public.tutorials');
        });
    });
