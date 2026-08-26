<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AssessmentFormController;
use App\Http\Controllers\BankAccountInfoController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\FormInputController;
use App\Http\Controllers\GraduateLedgerController;
use App\Http\Controllers\LawSchoolLedgerController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PaymentDetailOptionController;
use App\Http\Controllers\StaffInputController;
use App\Http\Controllers\SupportingDocumentController;
use App\Http\Controllers\UACSController;
use App\Http\Controllers\AssessmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// Public Routes (no auth required)
// ─────────────────────────────────────────────────────────────────────────────

Route::name('public.')->prefix('public')->group(function () {
    // Route::get('/submit', [FormInputController::class, 'create'])->name('submit');
    // Route::post('/submit', [FormInputController::class, 'store'])->name('submit.store');
    Route::get('/opform', [FormInputController::class, 'create']);
    Route::post('/opform', [FormInputController::class, 'store']);

    Route::get('/success/{reference_number}', [FormInputController::class, 'success'])->name('success');
    Route::get('/success/{reference_number}/print', [FormInputController::class, 'printReceipt'])->name('print');

    Route::get('/assessmentform', [AssessmentFormController::class, 'create'])->name('assessmentform');
    Route::post('/assessmentform', [AssessmentFormController::class, 'store']);

    Route::get('/assessment_complete/{assessmentForm:reference_number}', [AssessmentFormController::class, 'complete'])->name('complete');
    Route::get('/assessment_complete/{assessmentForm:reference_number}/print', [AssessmentFormController::class, 'print'])->name('print_req');
});

// Root redirect → public submission form
Route::redirect('/', '/login')->name('home');

// ─────────────────────────────────────────────────────────────────────────────
// Authentication Routes (Google OAuth)
// ─────────────────────────────────────────────────────────────────────────────

Route::get('/auth/google', [GoogleController::class, 'redirect'])
    ->name('google.redirect');

Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->name('google.callback');

// ─────────────────────────────────────────────────────────────────────────────
// Authenticated Routes
// ─────────────────────────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {

    // Main Dashboard Redirect by Role
    Route::get('/dashboard', function (Request $request) {
        if ($request->user()?->role === 'client') {
            return redirect()->route('client.dashboard');
        }
        return redirect()->route('staff.dashboard');
    })->name('dashboard');

    // ── Client Portal Routes ─────────────────────────────────────────────────
    Route::prefix('client')->name('client.')->group(function () {
        Route::get('/dashboard', [ClientController::class, 'dashboard'])->name('dashboard');
    });

    // Order of Payment
    Route::get('/order-of-payment', fn () => inertia('order-of-payment/Index'))->name('order-of-payment.index');

    // ── Graduate Ledger ──────────────────────────────────────────────────────
    Route::prefix('graduate-ledger')->name('graduate-ledger.')->middleware('staff')->group(function () {
        Route::get('/print-select', [GraduateLedgerController::class, 'printSelect'])->name('print-select');
        Route::get('/pdf',          [GraduateLedgerController::class, 'generatePdf'])->name('pdf');
        Route::get('/export',       [GraduateLedgerController::class, 'export'])->name('export');
        Route::get('/add',          [GraduateLedgerController::class, 'create'])->name('create');
        Route::post('/',            [GraduateLedgerController::class, 'store'])->name('store');
        Route::post('/import',      [GraduateLedgerController::class, 'import'])->name('import');
        Route::get('/',             [GraduateLedgerController::class, 'index'])->name('index');
        Route::get('/{id}/edit',    [GraduateLedgerController::class, 'edit'])->name('edit');
        Route::put('/{id}',         [GraduateLedgerController::class, 'update'])->name('update');
        Route::delete('/{id}',      [GraduateLedgerController::class, 'destroy'])->name('destroy');
    });

    // ── Law School Ledger ────────────────────────────────────────────────────
    Route::prefix('law-ledger')->name('law-ledger.')->middleware('staff')->group(function () {
        Route::get('/print-select',    [LawSchoolLedgerController::class, 'printSelect'])->name('print-select');
        Route::get('/pdf',             [LawSchoolLedgerController::class, 'generatePdf'])->name('pdf');
        Route::get('/export',          [LawSchoolLedgerController::class, 'export'])->name('export');
        Route::get('/students/search', [LawSchoolLedgerController::class, 'searchStudents'])->name('students.search');
        Route::get('/add',             [LawSchoolLedgerController::class, 'create'])->name('create');
        Route::get('/new-transaction', [LawSchoolLedgerController::class, 'create']);
        Route::post('/',               [LawSchoolLedgerController::class, 'store'])->name('store');
        Route::post('/import',         [LawSchoolLedgerController::class, 'import'])->name('import');
        Route::get('/',                [LawSchoolLedgerController::class, 'index'])->name('index');
        Route::get('/{id}/edit',       [LawSchoolLedgerController::class, 'edit'])->name('edit');
        Route::put('/{id}',            [LawSchoolLedgerController::class, 'update'])->name('update');
        Route::delete('/{id}',         [LawSchoolLedgerController::class, 'destroy'])->name('destroy');
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

Route::name('staff.')->prefix('staff')->middleware(['auth', 'staff'])->group(function () {

    Route::redirect('/', '/staff/staffdashboard');

    Route::get('/staffdashboard', [StaffInputController::class, 'dashboard'])->name('dashboard');

    // ── Requests Management ──────────────────────────────────────────────────
    Route::name('requests.')->prefix('requests')->group(function () {
        Route::get('/',                      [StaffInputController::class, 'index'])->name('index');
        Route::post('/process',              [StaffInputController::class, 'store'])->name('store');
        Route::get('/{formInput}/process',   [StaffInputController::class, 'create'])->name('process');
        Route::get('/{formInput}',           [StaffInputController::class, 'show'])->name('show');
        Route::get('/{formInput}/view-op',   [StaffInputController::class, 'viewOp'])->name('viewOp');
        Route::post('/{formInput}/email-op', [StaffInputController::class, 'emailOp'])->name('emailOp');
        Route::put('/{formInput}/details',   [StaffInputController::class, 'updateDetails'])->name('updateDetails');
        Route::get('/{staffInput}/edit',     [StaffInputController::class, 'edit'])->name('edit');
        Route::put('/{staffInput}',          [StaffInputController::class, 'update'])->name('update');
    });

    // ── Master Data (Resource Routes) ───────────────────────────────────────
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
        ->parameters(['uacs' => 'uacs']);

    // ── Supporting Documents ─────────────────────────────────────────────────
    Route::name('documents.')->prefix('documents')->group(function () {
        Route::get('/',                              [SupportingDocumentController::class, 'index'])->name('index');
        Route::post('/',                             [SupportingDocumentController::class, 'store'])->name('store');
        Route::get('/{supportingDocument}/download', [SupportingDocumentController::class, 'download'])->name('download');
        Route::delete('/{supportingDocument}',       [SupportingDocumentController::class, 'destroy'])->name('destroy');
    });

    // Courses
    Route::resource('courses', CoursesController::class)
        ->parameters(['courses' => 'courses']);

    // ── Assessment Requests ──────────────────────────────────────────────────
    Route::name('assessments.')->prefix('assessments')->group(function () {
        Route::get('/dashboard', [AssessmentController::class, 'dashboard'])->name('dashboard');
        Route::get('/', [AssessmentController::class, 'index'])->name('index');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Settings Routes
// ─────────────────────────────────────────────────────────────────────────────

require __DIR__.'/settings.php';
