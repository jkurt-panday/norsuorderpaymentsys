<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormInputController;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(
    [
        // 'auth', 'verified'
    ]
)->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::prefix('public')->group(function () {
    Route::get('form', [FormInputController::class, 'create'])->name('public.form');
    Route::post('form', [FormInputController::class, 'store'])->name('public.form.store');
    Route::get('success/{reference_number}', [FormInputController::class, 'success'])->name('public.success');
});


// TODO