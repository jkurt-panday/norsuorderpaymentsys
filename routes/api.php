<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashierRequestController;
use App\Http\Controllers\Api\PublicAssessmentFormController;
use App\Http\Controllers\Api\PublicOpFormController;
use App\Http\Controllers\Api\SupportingDocumentController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\CollegeOfficeController;
use App\Http\Controllers\Api\StaffRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api/v1.
| Authentication: Sanctum stateful SPA cookies (EnsureFrontendRequestsAreStateful
| is already prepended to the api middleware group in bootstrap/app.php).
|
| Endpoint stubs return 501 until the matching controller is implemented.
| Phase 2 of the SPA/API split will replace each 501 one module at a time.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Auth ─────────────────────────────────────────────────────────────────
    // GET  /sanctum/csrf-cookie  — provided by Sanctum, no stub needed
    // POST /login                — provided by Fortify, no stub needed
    // POST /logout               — provided by Fortify, no stub needed

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/user', [AuthController::class, 'user'])
            ->name('auth.user');
    });

    // ── Public: Order-of-Payment form ────────────────────────────────────────
    Route::prefix('public')->name('public.')->group(function () {
        Route::get('/opform/options', [PublicOpFormController::class, 'options'])
            ->middleware('throttle:public-form')
            ->name('opform.options');

        Route::post('/opform', [PublicOpFormController::class, 'store'])
            ->middleware('throttle:public-form')
            ->name('opform.store');

        Route::get('/opform/{reference}', [PublicOpFormController::class, 'show'])
            ->name('opform.show');

        Route::get('/opform/{reference}/print', [PublicOpFormController::class, 'print'])
            ->name('opform.print');

        Route::get('/opform/{reference}/download', [PublicOpFormController::class, 'download'])
            ->name('opform.download');

        // ── Public: Assessment form ───────────────────────────────────────────
        Route::get('/assessmentform/options', [PublicAssessmentFormController::class, 'options'])
            ->middleware('throttle:public-form')
            ->name('assessmentform.options');

        Route::post('/assessmentform', [PublicAssessmentFormController::class, 'store'])
            ->middleware('throttle:public-form')
            ->name('assessmentform.store');

        Route::get('/assessmentform/{reference}', [PublicAssessmentFormController::class, 'show'])
            ->name('assessmentform.show');

        Route::get('/assessmentform/{reference}/print', [PublicAssessmentFormController::class, 'print'])
            ->name('assessmentform.print');

        Route::get('/assessmentform/{reference}/download', [PublicAssessmentFormController::class, 'download'])
            ->name('assessmentform.download');

        // ── Public: College offices ───────────────────────────────────────────
        Route::post('/college-offices', [CollegeOfficeController::class, 'store'])
            ->middleware('throttle:public-form')
            ->name('college-offices.store');
    });

    // ── Authenticated ─────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // ── Staff requests ────────────────────────────────────────────────────
        Route::prefix('staff/requests')->name('staff.requests.')->middleware('staff')->group(function () {
            Route::get('/', [StaffRequestController::class, 'index'])->name('index');
            Route::post('/process', [StaffRequestController::class, 'store'])->name('store');
            Route::get('/email-recipients', [StaffRequestController::class, 'emailRecipients'])->name('emailRecipients');
            Route::get('/student-search', [StaffRequestController::class, 'searchStudents'])
                ->middleware('throttle:search')
                ->name('searchStudents');
            Route::post('/bulk-email-op', [StaffRequestController::class, 'bulkEmailOp'])
                ->middleware('throttle:email')
                ->name('bulkEmailOp');
            Route::get('/{formInput}', [StaffRequestController::class, 'show'])->name('show');
            Route::post('/{formInput}/process', [StaffRequestController::class, 'process'])->name('process');
            Route::post('/{formInput}/email-op', [StaffRequestController::class, 'emailOp'])
                ->middleware('throttle:email')
                ->name('emailOp');
            Route::put('/{formInput}/details', [StaffRequestController::class, 'updateDetails'])->name('updateDetails');
            Route::put('/{staffInput}/or', [StaffRequestController::class, 'updateOrNumber'])->name('updateOr');
            Route::put('/{formInput}/student', [StaffRequestController::class, 'linkStudent'])->name('linkStudent');
            Route::post('/{formInput}/student', [StaffRequestController::class, 'createAndLinkStudent'])->name('createAndLinkStudent');
            Route::put('/{formInput}/academic-term', [StaffRequestController::class, 'assignAcademicTerm'])->name('assignAcademicTerm');
            Route::put('/{staffInput}', [StaffRequestController::class, 'update'])->name('update');
        });

        // ── Cashier requests ──────────────────────────────────────────────────
        Route::prefix('cashier/requests')->name('cashier.requests.')->middleware('cashier')->group(function () {
            Route::get('/', [CashierRequestController::class, 'index'])->name('index');
            Route::get('/{staffInput}', [CashierRequestController::class, 'show'])->name('show');
            Route::put('/{staffInput}/payment', [CashierRequestController::class, 'updatePayment'])->name('payment.update');
        });

        // ── Supporting documents ──────────────────────────────────────────────
        Route::prefix('documents')->name('documents.')->middleware('staff')->group(function () {
            Route::get('/', [SupportingDocumentController::class, 'index'])->name('index');
            Route::post('/', [SupportingDocumentController::class, 'store'])
                ->middleware('throttle:upload')
                ->name('store');
            Route::get('/{supportingDocument}/view', [SupportingDocumentController::class, 'view'])->name('view');
            Route::get('/{supportingDocument}/download', [SupportingDocumentController::class, 'download'])->name('download');
            Route::delete('/{supportingDocument}', [SupportingDocumentController::class, 'destroy'])->name('destroy');
        });

        // ── Admin ─────────────────────────────────────────────────────────────
        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/activity-log', [AdminController::class, 'activityLog'])->name('activity-log');
            Route::get('/users', [AdminController::class, 'index'])->name('users.index');
            Route::post('/users', [AdminController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('users.store');
            Route::put('/users/{id}', [AdminController::class, 'update'])->name('users.update');
            Route::put('/users/{id}/toggle', [AdminController::class, 'toggle'])->name('users.toggle');
            Route::delete('/users/{id}', [AdminController::class, 'destroy'])->name('users.destroy');
        });

        // ── Master data ───────────────────────────────────────────────────────
        Route::middleware('staff')->group(function () {
            foreach (['memberships', 'payment-options', 'bank-accounts', 'uacs', 'authorized-officials', 'courses', 'academic-terms', 'college-offices'] as $resource) {
                Route::get('/'.$resource, [MasterDataController::class, 'index'])->defaults('resource', $resource)->name($resource.'.index');
                Route::post('/'.$resource, [MasterDataController::class, 'store'])->defaults('resource', $resource)->name($resource.'.store');
                Route::put('/'.$resource.'/{id}', [MasterDataController::class, 'update'])->defaults('resource', $resource)->name($resource.'.update');
                Route::delete('/'.$resource.'/{id}', [MasterDataController::class, 'destroy'])->defaults('resource', $resource)->name($resource.'.destroy');
            }
            Route::put('/authorized-officials/{id}/set-active', [MasterDataController::class, 'setActive'])->name('authorized-officials.set-active');
            Route::get('/uacs/export', [MasterDataController::class, 'exportUacs'])->middleware('throttle:export')->name('uacs.export');
            Route::post('/uacs/import', [MasterDataController::class, 'importUacs'])->middleware('throttle:import')->name('uacs.import');
        });

        // ── Graduate ledger ───────────────────────────────────────────────────
        foreach (['graduate-ledger' => 'graduate', 'law-ledger' => 'law'] as $prefix => $ledger) {
            Route::prefix($prefix)->name($ledger.'-ledger.')->middleware('staff')->group(function () use ($ledger) {
                Route::get('/', [LedgerController::class, 'index'])->defaults('ledger', $ledger)->name('index');
                Route::post('/', [LedgerController::class, 'store'])->defaults('ledger', $ledger)->name('store');
                Route::post('/cohort-store', [LedgerController::class, 'cohortStore'])->defaults('ledger', $ledger)->name('cohort-store');
                Route::get('/students/search', [LedgerController::class, 'searchStudents'])->defaults('ledger', $ledger)->middleware('throttle:search')->name('students.search');
                Route::get('/students/roster', [LedgerController::class, 'roster'])->defaults('ledger', $ledger)->name('students.roster');
                Route::post('/import', [LedgerController::class, 'import'])->defaults('ledger', $ledger)->middleware('throttle:import')->name('import');
                Route::get('/export', [LedgerController::class, 'export'])->defaults('ledger', $ledger)->middleware('throttle:export')->name('export');
                Route::get('/pdf', [LedgerController::class, 'pdf'])->defaults('ledger', $ledger)->middleware('throttle:export')->name('pdf');
                Route::get('/email-recipients', [LedgerController::class, 'emailRecipients'])->defaults('ledger', $ledger)->name('email-recipients');
                Route::post('/send-emails', [LedgerController::class, 'sendEmails'])->defaults('ledger', $ledger)->middleware('throttle:email')->name('send-emails');
                Route::put('/{id}', [LedgerController::class, 'update'])->defaults('ledger', $ledger)->name('update');
                Route::delete('/{id}', [LedgerController::class, 'destroy'])->defaults('ledger', $ledger)->name('destroy');
                Route::get('/students/{student}/balance', [LedgerController::class, 'balance'])->defaults('ledger', $ledger)->name('students.balance');
            });
        }

        // ── Assessments ───────────────────────────────────────────────────────
        Route::prefix('assessments')->name('assessments.')->middleware('staff')->group(function () {
            Route::get('/', [AssessmentController::class, 'index'])->name('index');
            Route::get('/dashboard', [AssessmentController::class, 'dashboard'])->name('dashboard');
            Route::get('/{assessment}/print-soa', [AssessmentController::class, 'print'])->name('print_soa');
            Route::post('/{assessment}/email-soa', [AssessmentController::class, 'emailSoa'])->middleware('throttle:email')->name('email_soa');
            Route::get('/{assessment}', [AssessmentController::class, 'show'])->name('show');
            Route::patch('/{assessment}', [AssessmentController::class, 'update'])->name('update');
             Route::delete('/{assessment}', [AssessmentController::class, 'destroy'])->middleware('admin')->name('destroy');
        });

        // ── Settings ──────────────────────────────────────────────────────────
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::patch('/profile', [SettingsController::class, 'profile'])->name('profile.update');
            Route::put('/password', [SettingsController::class, 'password'])->name('password.update');
        });
    });
});
