<?php

use App\Http\Controllers\CrmModuleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\PersonController;
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
        Route::post('people/{person}/merge', [PersonController::class, 'merge'])->name('people.merge');
        Route::resource('people', PersonController::class);
        Route::get('crm/{module}', CrmModuleController::class)->name('crm.module');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
