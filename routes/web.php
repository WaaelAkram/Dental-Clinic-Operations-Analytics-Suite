<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicDashboardController;
// ... (all your route definitions)
Route::get('/clinic-dashboard', [PublicDashboardController::class, 'index'])->name('public.dashboard');
Route::get('/clinic-dashboard/ar', [PublicDashboardController::class, 'index_ar'])->name('public.dashboard.ar');
Route::middleware(['auth'])->group(function () {
   Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// THIS LINE MUST BE PRESENT AND THE LAST LINE IN THE FILE
require __DIR__.'/auth.php';