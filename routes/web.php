<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\GradLedgerController;
use Illuminate\Support\Facades\Route;

// Home / Welcome Page
Route::inertia('/', 'welcome')->name('home');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    
    // Main Dashboard
    Route::get('/dashboard', fn () => inertia('dashboard'))->name('dashboard');

    // GRADUATE LEDGER: Keep only ONE definition. 
    // This points to your 'Index' component inside 'resources/js/Pages/graduate-ledger/'
    Route::get('/graduate-ledger', function () {
        return inertia('graduate-ledger/Index'); // Note the capital "I" to match Index.tsx!
    })->name('graduate-ledger.index');

});

// Admin-Only Routes
Route::middleware(['auth', 'admin'])->group(function () {
    
    // 1. The page view (Renders the React Component via Inertia)
    Route::get('/admin/users', function () {
        return inertia('Admin/UserManagement');
    })->name('admin.users.index');

    // 2. The form submission endpoint
    Route::post('/admin/users', [AdminUserController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('admin.users.store');

});

require __DIR__.'/settings.php';