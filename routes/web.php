<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\GraduateLedgerController;
use Illuminate\Support\Facades\Route;

// Home / Welcome Page
Route::inertia('/', 'welcome')->name('home');

// Authenticated Routes
Route::middleware('auth')->group(function () {

    // Main Dashboard
    Route::get('/dashboard', fn () => inertia('dashboard'))->name('dashboard');
    Route::get('/graduate-ledger/print-select', [GraduateLedgerController::class, 'printSelect'])->name('graduate-ledger.print-select');
    Route::get('/graduate-ledger/pdf', [GraduateLedgerController::class, 'generatePdf'])->name('graduate-ledger.pdf');
    Route::get('/graduate-ledger/add', [GraduateLedgerController::class, 'create'])->name('graduate-ledger.create');
    Route::post('/graduate-ledger', [GraduateLedgerController::class, 'store'])->name('graduate-ledger.store');
    // Graduate Ledger: Single definition pointing to your controller
    Route::get('/graduate-ledger', [GraduateLedgerController::class, 'index'])
        ->name('graduate-ledger.index');

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
