<?php

use App\Http\Controllers\AIChatController;
use App\Http\Controllers\AISuggestionController;
use App\Http\Controllers\AutomationRuleController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\CrmModuleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DealFollowUpController;
use App\Http\Controllers\DealProductController;
use App\Http\Controllers\DealProposalController;
use App\Http\Controllers\DealTimelineController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\LeadFormController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductStatsController;
use App\Http\Controllers\PublicLeadFormController;
use App\Http\Controllers\QuickDealActivityController;
use App\Http\Controllers\TenantOnboardingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('public/lead-forms/{slug}', [PublicLeadFormController::class, 'show'])
    ->name('public.lead-forms.show')
    ->middleware('throttle:30,1');
Route::post('public/lead-forms/{slug}', [PublicLeadFormController::class, 'submit'])
    ->name('public.lead-forms.submit')
    ->middleware('throttle:10,1');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('tenant/onboarding', [TenantOnboardingController::class, 'create'])
        ->name('tenant.onboarding');
    Route::post('tenant/onboarding', [TenantOnboardingController::class, 'store'])
        ->name('tenant.store');

    Route::middleware('tenant.selected')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('ai-suggestions', [AISuggestionController::class, 'index'])->name('ai-suggestions.index');
        Route::get('ai-suggestions/{suggestion}', [AISuggestionController::class, 'show'])->name('ai-suggestions.show');
        Route::patch('ai-suggestions/{suggestion}/accept', [AISuggestionController::class, 'accept'])->name('ai-suggestions.accept');
        Route::patch('ai-suggestions/{suggestion}/postpone', [AISuggestionController::class, 'postpone'])->name('ai-suggestions.postpone');
        Route::patch('ai-suggestions/{suggestion}/archive', [AISuggestionController::class, 'archive'])->name('ai-suggestions.archive');
        Route::patch('ai-suggestions/{suggestion}/ignore', [AISuggestionController::class, 'ignore'])->name('ai-suggestions.ignore');
        Route::post('ai-suggestions/{suggestion}/convert-to-activity', [AISuggestionController::class, 'convertToActivity'])->name('ai-suggestions.convert-to-activity');
        Route::get('ai-chat', [AIChatController::class, 'index'])->name('ai-chat.index');
        Route::post('ai-chat', [AIChatController::class, 'storeMessage'])->name('ai-chat.store')->middleware('throttle:30,1');
        Route::get('ai-chat-suggestions', [AIChatController::class, 'suggestedQuestions'])->name('ai-chat.suggestions')->middleware('throttle:60,1');
        Route::get('ai-chat/{conversation}', [AIChatController::class, 'show'])->name('ai-chat.show');
        Route::post('ai-chat/{conversation}/messages', [AIChatController::class, 'storeMessage'])->name('ai-chat.messages.store')->middleware('throttle:30,1');
        Route::get('ai-chat/{conversation}/stream', [AIChatController::class, 'streamMessage'])->name('ai-chat.stream')->middleware('throttle:60,1');
        Route::post('ai-chat/{conversation}/actions', [AIChatController::class, 'executeAction'])->name('ai-chat.actions')->middleware('throttle:20,1');
        Route::delete('ai-chat/{conversation}', [AIChatController::class, 'destroy'])->name('ai-chat.destroy');
        Route::resource('entities', EntityController::class);
        Route::resource('products', ProductController::class);
        Route::resource('lead-forms', LeadFormController::class);
        Route::patch('automations/{automation}/pause', [AutomationRuleController::class, 'pause'])->name('automations.pause');
        Route::patch('automations/{automation}/resume', [AutomationRuleController::class, 'resume'])->name('automations.resume');
        Route::resource('automations', AutomationRuleController::class);
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
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
