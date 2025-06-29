<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicDashboardController;
use App\Http\Controllers\MarketingAdminController;

// --- Public Clinic & Marketing Routes ---

// Public-facing analytics dashboard
Route::get('/clinic-dashboard', [PublicDashboardController::class, 'index'])->name('public.dashboard');
Route::get('/clinic-dashboard/ar', [PublicDashboardController::class, 'index_ar'])->name('public.dashboard.ar');

// Main admin/marketing tools dashboard
Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

// Marketing-related routes
Route::post('/marketing/exclusions', [MarketingAdminController::class, 'addExclusion'])->name('marketing.exclusions.add');
Route::post('/marketing/manual-send', [MarketingAdminController::class, 'sendManualBroadcast'])->name('marketing.manual.send');
Route::get('/marketing-report', [MarketingAdminController::class, 'showReport'])->name('marketing.report');


// --- Authenticated User Routes (for future use, if needed) ---

// Profile routes remain protected by auth middleware
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Authentication routes (login, logout, etc.)
require __DIR__.'/auth.php';