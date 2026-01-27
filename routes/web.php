<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return redirect('/admin/login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

// Monthly Plan PDF Export
Route::get('/monthly-plan/pdf', [\App\Http\Controllers\MonthlyPlanPdfController::class, 'generate'])
    ->name('monthly-plan.pdf')
    ->middleware('auth');

require __DIR__.'/settings.php';
