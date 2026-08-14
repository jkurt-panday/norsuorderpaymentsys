<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\BankAccountInfoController;
use App\Http\Controllers\FormInputController;
use App\Http\Controllers\GraduateLedgerController;
use App\Http\Controllers\LawSchoolLedgerController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PaymentDetailOptionController;
use App\Http\Controllers\StaffInputController;
use App\Http\Controllers\SupportingDocumentController;
use App\Http\Controllers\AssessmentFormController;
use App\Http\Controllers\UACSController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// Public Routes (no auth required)
// ─────────────────────────────────────────────────────────────────────────────

Route::name('public.')->prefix('public')->group(function () {
    Route::get('/form', [FormInputController::class, 'create'])->name('submit');
    Route::post('/form', [FormInputController::class, 'store'])->name('submit.store');
    
    Route::get('/success/{reference_number}', [FormInputController::class, 'success'])->name('success');
    Route::get('/success/{reference_number}/print', [FormInputController::class, 'printReceipt'])->name('print');

    Route::get('/assessmentform', [AssessmentFormController::class, 'create'])->name('assessmentform');
    Route::post('/assessmentform', [AssessmentFormController::class, 'store']);

    Route::get('/assessment_complete/{reference_number}', [AssessmentFormController::class, 'complete'])->name('complete');
    Route::get('/assessment_complete/{reference_number}/print', [AssessmentFormController::class, 'printReceipt'])->name('print');
    
});

// Root redirect → public submission form
Route::get('/', function () {
    return redirect()->route('login');
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
        Route::get('/export', [GraduateLedgerController::class, 'export'])->name('export');
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
        Route::get('/export', [LawSchoolLedgerController::class, 'export'])->name('export');
        Route::get('/new-transaction', [LawSchoolLedgerController::class, 'create'])->name('create');
        Route::redirect('/add', '/law-ledger/new-transaction');
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
        Route::put('/{formInput}/details', [StaffInputController::class, 'updateDetails'])->name('updateDetails');
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

    Route::resource('uacs', UACSController::class)
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
