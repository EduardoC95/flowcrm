<?php

use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\CrmModuleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DealFollowUpController;
use App\Http\Controllers\DealProductController;
use App\Http\Controllers\DealProposalController;
use App\Http\Controllers\DealTimelineController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductStatsController;
use App\Http\Controllers\QuickDealActivityController;
use App\Http\Controllers\TenantOnboardingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('tenant/onboarding', [TenantOnboardingController::class, 'create'])
        ->name('tenant.onboarding');
    Route::post('tenant/onboarding', [TenantOnboardingController::class, 'store'])
        ->name('tenant.store');

    Route::middleware('tenant.selected')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::resource('entities', EntityController::class);
        Route::resource('products', ProductController::class);
        Route::get('product-stats', [ProductStatsController::class, 'index'])->name('product-stats.index');
        Route::get('product-stats/export', [ProductStatsController::class, 'export'])->name('product-stats.export');
        Route::post('people/{person}/merge', [PersonController::class, 'merge'])->name('people.merge');
        Route::resource('people', PersonController::class);
        Route::get('calendar', [CalendarEventController::class, 'index'])->name('calendar.index');
        Route::get('calendar/feed', [CalendarEventController::class, 'calendarFeed'])->name('calendar.feed');
        Route::patch('calendar-events/{calendarEvent}/complete', [CalendarEventController::class, 'complete'])->name('calendar-events.complete');
        Route::patch('calendar-events/{calendarEvent}/cancel', [CalendarEventController::class, 'cancel'])->name('calendar-events.cancel');
        Route::resource('calendar-events', CalendarEventController::class)->except(['index']);
        Route::get('deals-board', [DealController::class, 'board'])->name('deals.board');
        Route::get('deals/{deal}/timeline', [DealTimelineController::class, 'index'])->name('deals.timeline');
        Route::post('deals/{deal}/quick-activities', [QuickDealActivityController::class, 'store'])->name('deals.quick-activities.store');
        Route::patch('deals/{deal}/move-stage', [DealController::class, 'moveStage'])->name('deals.move-stage');
        Route::patch('deals/{deal}/follow-up/cancel', [DealFollowUpController::class, 'cancel'])->name('deals.follow-up.cancel');
        Route::patch('deals/{deal}/follow-up/client-replied', [DealFollowUpController::class, 'markClientReplied'])->name('deals.follow-up.client-replied');
        Route::post('deals/{deal}/products', [DealProductController::class, 'store'])->name('deals.products.store');
        Route::patch('deals/{deal}/products/{dealProduct}', [DealProductController::class, 'update'])->name('deals.products.update');
        Route::delete('deals/{deal}/products/{dealProduct}', [DealProductController::class, 'destroy'])->name('deals.products.destroy');
        Route::post('deals/{deal}/proposals', [DealProposalController::class, 'store'])->name('deals.proposals.store');
        Route::get('deals/{deal}/proposals/{proposal}/preview-email', [DealProposalController::class, 'previewEmail'])->name('deals.proposals.preview-email');
        Route::post('deals/{deal}/proposals/{proposal}/send', [DealProposalController::class, 'send'])->name('deals.proposals.send');
        Route::get('deals/{deal}/proposals/{proposal}/download', [DealProposalController::class, 'download'])->name('deals.proposals.download');
        Route::delete('deals/{deal}/proposals/{proposal}', [DealProposalController::class, 'destroy'])->name('deals.proposals.destroy');
        Route::resource('deals', DealController::class);
        Route::get('crm/{module}', CrmModuleController::class)->name('crm.module');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
