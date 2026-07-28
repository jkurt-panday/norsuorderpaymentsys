<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\GraduateLedgerController;
use App\Http\Controllers\LawSchoolLedgerController;
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
    Route::post('/graduate-ledger/import', [GraduateLedgerController::class, 'import'])->name('graduate-ledger.import');
    // Graduate Ledger: Single definition pointing to your controller
    Route::get('/graduate-ledger', [GraduateLedgerController::class, 'index'])
        ->name('graduate-ledger.index');

    // Law School Ledger Routes
    Route::get('/law-ledger/print-select', [LawSchoolLedgerController::class, 'printSelect'])->name('law-ledger.print-select');
    Route::get('/law-ledger/pdf', [LawSchoolLedgerController::class, 'generatePdf'])->name('law-ledger.pdf');
    Route::get('/law-ledger/add', [LawSchoolLedgerController::class, 'create'])->name('law-ledger.create');
    Route::post('/law-ledger', [LawSchoolLedgerController::class, 'store'])->name('law-ledger.store');
    Route::post('/law-ledger/import', [LawSchoolLedgerController::class, 'import'])->name('law-ledger.import');
    Route::get('/law-ledger', [LawSchoolLedgerController::class, 'index'])
        ->name('law-ledger.index');

});


Route::get('/debug-temp', function () {
    return response()->json([
        'temp_dir' => sys_get_temp_dir(),
        'writable' => is_writable(sys_get_temp_dir()),
        'tmpfile' => tmpfile() !== false,
    ]);
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
