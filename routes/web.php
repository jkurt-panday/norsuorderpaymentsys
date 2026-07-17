<?php

use App\Http\Controllers\AdminUserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GradLedgerController;

Route::get('/graduate-ledger', [GradLedgerController::class, 'index'])->name('graduate-ledger.index');
Route::inertia('/', 'welcome')->name('home');

Route::middleware('auth')->get('/dashboard', fn () => inertia('dashboard'))->name('dashboard');

Route::middleware(['auth', 'admin'])->group(function () {
    // 1. The page view (Renders the React Component via Inertia)
    Route::get('/admin/users', function () {
        return inertia('Admin/UserManagement');
    })->name('admin.users.index');

    // 2. The form submission endpoint (We built this in Phase 4)
    Route::post('/admin/users', [AdminUserController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('admin.users.store');
});

require __DIR__.'/settings.php';
