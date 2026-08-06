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
    Route::get('/graduate-ledger/{id}/edit', [GraduateLedgerController::class, 'edit'])->name('graduate-ledger.edit');
    Route::put('/graduate-ledger/{id}', [GraduateLedgerController::class, 'update'])->name('graduate-ledger.update');
    Route::delete('/graduate-ledger/{id}', [GraduateLedgerController::class, 'destroy'])->name('graduate-ledger.destroy');

    // Law School Ledger Routes
    Route::get('/law-ledger/print-select', [LawSchoolLedgerController::class, 'printSelect'])->name('law-ledger.print-select');
    Route::get('/law-ledger/pdf', [LawSchoolLedgerController::class, 'generatePdf'])->name('law-ledger.pdf');
    Route::get('/law-ledger/add', [LawSchoolLedgerController::class, 'create'])->name('law-ledger.create');
    Route::post('/law-ledger', [LawSchoolLedgerController::class, 'store'])->name('law-ledger.store');
    Route::post('/law-ledger/import', [LawSchoolLedgerController::class, 'import'])->name('law-ledger.import');
    Route::get('/law-ledger', [LawSchoolLedgerController::class, 'index'])
        ->name('law-ledger.index');

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

// Root redirect → public submission form
Route::get('/', function () {
    return redirect()->route('public.submit');
})->name('home');

// ─────────────────────────────────────────────────────────────────────────────
// Debug (remove in production)
// ─────────────────────────────────────────────────────────────────────────────

Route::get('/debug-temp', function () {
    return response()->json([
        'temp_dir' => sys_get_temp_dir(),
        'writable' => is_writable(sys_get_temp_dir()),
        'tmpfile' => tmpfile() !== false,
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Authenticated Routes
// ─────────────────────────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    // Main Dashboard
    Route::get('/dashboard', fn () => inertia('dashboard'))->name('dashboard');

    // Order of Payment
    Route::get('/order-of-payment', fn () => inertia('order-of-payment/Index'))->name('order-of-payment.index');

    // ── Graduate Ledger ──────────────────────────────────────────────────────
    Route::prefix('graduate-ledger')->name('graduate-ledger.')->group(function () {
        // Specific routes ABOVE wildcard routes
        Route::get('/print-select', [GraduateLedgerController::class, 'printSelect'])->name('print-select');
        Route::get('/pdf', [GraduateLedgerController::class, 'generatePdf'])->name('pdf');
        Route::get('/add', [GraduateLedgerController::class, 'create'])->name('create');
        Route::post('/', [GraduateLedgerController::class, 'store'])->name('store');
        Route::post('/import', [GraduateLedgerController::class, 'import'])->name('import');
        Route::get('/', [GraduateLedgerController::class, 'index'])->name('index');
        Route::get('/{id}/edit', [GraduateLedgerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [GraduateLedgerController::class, 'update'])->name('update');
        Route::delete('/{id}', [GraduateLedgerController::class, 'destroy'])->name('destroy');
    });

    // ── Law School Ledger ────────────────────────────────────────────────────
    Route::prefix('law-ledger')->name('law-ledger.')->group(function () {
        Route::get('/print-select', [LawSchoolLedgerController::class, 'printSelect'])->name('print-select');
        Route::get('/pdf', [LawSchoolLedgerController::class, 'generatePdf'])->name('pdf');
        Route::get('/add', [LawSchoolLedgerController::class, 'create'])->name('create');
        Route::post('/', [LawSchoolLedgerController::class, 'store'])->name('store');
        Route::post('/import', [LawSchoolLedgerController::class, 'import'])->name('import');
        Route::get('/', [LawSchoolLedgerController::class, 'index'])->name('index');
        Route::get('/{id}/edit', [LawSchoolLedgerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [LawSchoolLedgerController::class, 'update'])->name('update');
        Route::delete('/{id}', [LawSchoolLedgerController::class, 'destroy'])->name('destroy');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Admin-Only Routes
// ─────────────────────────────────────────────────────────────────────────────

Route::middleware(['auth', 'admin'])->group(function () {

    Route::get('/admin/users', function () {
        return inertia('Admin/UserManagement');
    })->name('admin.users.index');

    Route::post('/admin/users', [AdminUserController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('admin.users.store');

});

// ─────────────────────────────────────────────────────────────────────────────
// Staff Routes (auth protected)
// ─────────────────────────────────────────────────────────────────────────────

Route::name('staff.')->prefix('staff')->middleware(['auth'])->group(function () {

    // Redirect /staff -> /staff/staffdashboard
    Route::redirect('/', '/staff/staffdashboard');

    // Dashboard
    Route::get('/staffdashboard', [StaffInputController::class, 'dashboard'])->name('dashboard');

    // ── Requests Management ──────────────────────────────────────────────────
    Route::name('requests.')->prefix('requests')->group(function () {
        Route::get('/', [StaffInputController::class, 'index'])->name('index');

        // Non-wildcard routes ABOVE wildcard routes to prevent fallback hijacking
        Route::post('/process', [StaffInputController::class, 'store'])->name('store');

        Route::get('/{formInput}/process', [StaffInputController::class, 'create'])->name('process');
        Route::get('/{formInput}', [StaffInputController::class, 'show'])->name('show');
        Route::get('/{staffInput}/edit', [StaffInputController::class, 'edit'])->name('edit');
        Route::put('/{staffInput}', [StaffInputController::class, 'update'])->name('update');
    });

    // ── Master Data (Resource Routes) ───────────────────────────────────────
    // Explicit parameter bindings prevent Laravel's inflector from scrambling singular names.

    Route::resource('memberships', MembershipController::class)
        ->except(['show'])
        ->parameters(['memberships' => 'membership']);

    Route::resource('payment-options', PaymentDetailOptionController::class)
        ->except(['show'])
        ->parameters(['payment-options' => 'paymentOption']);

    Route::resource('bank-accounts', BankAccountInfoController::class)
        ->except(['show'])
        ->parameters(['bank-accounts' => 'bankAccount']);

    Route::resource('uacs', UacsController::class)
        ->except(['show'])
        ->parameters(['uacs' => 'uacs']); // Prevents Laravel from converting singular 'uacs' to 'uac'

    // ── Supporting Documents ─────────────────────────────────────────────────
    Route::name('documents.')->prefix('documents')->group(function () {
        Route::get('/', [SupportingDocumentController::class, 'index'])->name('index');
        Route::post('/', [SupportingDocumentController::class, 'store'])->name('store');
        Route::get('/{supportingDocument}/download', [SupportingDocumentController::class, 'download'])->name('download');
        Route::delete('/{supportingDocument}', [SupportingDocumentController::class, 'destroy'])->name('destroy');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Settings Routes
// ─────────────────────────────────────────────────────────────────────────────

require __DIR__.'/settings.php';
