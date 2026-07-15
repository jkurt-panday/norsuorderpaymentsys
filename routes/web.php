<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\FormInputController;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';


// FormInputController
Route::resource('')