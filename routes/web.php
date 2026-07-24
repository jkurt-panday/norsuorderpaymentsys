<?php

use Illuminate\Support\Facades\Route;

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
    Route::inertia('form', 'public/SubmitForm')->name('Submitform');
});