<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicAssessmentFormController;
use App\Http\Controllers\Api\PublicOpFormController;
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
        Route::post('/college-offices', fn () => response()->json(['message' => 'Not implemented.'], 501))
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
            Route::get('/', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('index');
            Route::get('/{staffInput}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('show');
            Route::put('/{staffInput}/payment', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('payment.update');
        });

        // ── Supporting documents ──────────────────────────────────────────────
        Route::prefix('documents')->name('documents.')->middleware('staff')->group(function () {
            Route::get('/', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('index');
            Route::post('/', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:upload')
                ->name('store');
            Route::get('/{supportingDocument}/view', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('view');
            Route::get('/{supportingDocument}/download', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('download');
            Route::delete('/{supportingDocument}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('destroy');
        });

        // ── Admin ─────────────────────────────────────────────────────────────
        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
            Route::get('/dashboard', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('dashboard');
            Route::get('/activity-log', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('activity-log');
            Route::get('/users', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('users.index');
            Route::post('/users', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:10,1')
                ->name('users.store');
            Route::put('/users/{id}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('users.update');
            Route::put('/users/{id}/toggle', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('users.toggle');
            Route::delete('/users/{id}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('users.destroy');
        });

        // ── Master data ───────────────────────────────────────────────────────
        Route::middleware('staff')->group(function () {
            // Memberships
            Route::get('/memberships', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('memberships.index');
            Route::post('/memberships', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('memberships.store');
            Route::put('/memberships/{membership}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('memberships.update');
            Route::delete('/memberships/{membership}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('memberships.destroy');

            // Payment options
            Route::get('/payment-options', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('payment-options.index');
            Route::post('/payment-options', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('payment-options.store');
            Route::put('/payment-options/{paymentOption}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('payment-options.update');
            Route::delete('/payment-options/{paymentOption}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('payment-options.destroy');

            // Bank accounts
            Route::get('/bank-accounts', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('bank-accounts.index');
            Route::post('/bank-accounts', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('bank-accounts.store');
            Route::put('/bank-accounts/{bankAccount}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('bank-accounts.update');
            Route::delete('/bank-accounts/{bankAccount}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('bank-accounts.destroy');

            // UACS
            Route::get('/uacs', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('uacs.index');
            Route::post('/uacs', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('uacs.store');
            Route::put('/uacs/{uacs}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('uacs.update');
            Route::delete('/uacs/{uacs}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('uacs.destroy');
            Route::get('/uacs/export', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:export')
                ->name('uacs.export');
            Route::post('/uacs/import', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:import')
                ->name('uacs.import');

            // Authorized officials
            Route::get('/authorized-officials', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('authorized-officials.index');
            Route::post('/authorized-officials', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('authorized-officials.store');
            Route::put('/authorized-officials/{authorizedOfficial}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('authorized-officials.update');
            Route::delete('/authorized-officials/{authorizedOfficial}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('authorized-officials.destroy');
            Route::put('/authorized-officials/{authorizedOfficial}/set-active', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('authorized-officials.set-active');

            // Courses
            Route::get('/courses', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('courses.index');
            Route::post('/courses', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('courses.store');
            Route::put('/courses/{course}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('courses.update');
            Route::delete('/courses/{course}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('courses.destroy');

            // Academic terms
            Route::get('/academic-terms', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('academic-terms.index');
            Route::post('/academic-terms', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('academic-terms.store');
            Route::put('/academic-terms/{academicTerm}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('academic-terms.update');
            Route::delete('/academic-terms/{academicTerm}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('academic-terms.destroy');
        });

        // ── Graduate ledger ───────────────────────────────────────────────────
        Route::prefix('graduate-ledger')->name('graduate-ledger.')->middleware('staff')->group(function () {
            Route::get('/', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('index');
            Route::post('/', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('store');
            Route::get('/{id}/edit', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('edit');
            Route::put('/{id}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('update');
            Route::delete('/{id}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('destroy');
            Route::post('/cohort-store', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('cohort-store');
            Route::post('/{id}/apply-membership', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('apply-membership');
            Route::get('/students/{student}/balance', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('students.balance');
            Route::post('/import', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:import')
                ->name('import');
            Route::get('/export', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:export')
                ->name('export');
            Route::get('/pdf', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:export')
                ->name('pdf');
            Route::get('/email-recipients', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('email-recipients');
            Route::post('/send-emails', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:email')
                ->name('send-emails');
        });

        // ── Law School ledger ─────────────────────────────────────────────────
        Route::prefix('law-ledger')->name('law-ledger.')->middleware('staff')->group(function () {
            Route::get('/', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('index');
            Route::post('/', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('store');
            Route::get('/{id}/edit', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('edit');
            Route::put('/{id}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('update');
            Route::delete('/{id}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('destroy');
            Route::post('/cohort-store', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('cohort-store');
            Route::post('/{id}/apply-honor', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('apply-honor');
            Route::get('/students/search', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:search')
                ->name('students.search');
            Route::get('/students/{student}/balance', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('students.balance');
            Route::delete('/students/{id}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('students.destroy');
            Route::post('/import', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:import')
                ->name('import');
            Route::get('/export', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:export')
                ->name('export');
            Route::get('/pdf', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:export')
                ->name('pdf');
            Route::get('/email-recipients', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('email-recipients');
            Route::post('/send-emails', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:email')
                ->name('send-emails');
        });

        // ── Assessments ───────────────────────────────────────────────────────
        Route::prefix('assessments')->name('assessments.')->middleware('staff')->group(function () {
            Route::get('/', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('index');
            Route::get('/dashboard', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('dashboard');
            Route::get('/{assessment}/edit', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('edit');
            Route::patch('/{assessment}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('update');
            Route::get('/{assessment}/print-soa', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('print_soa');
            Route::post('/{assessment}/email-soa', fn () => response()->json(['message' => 'Not implemented.'], 501))
                ->middleware('throttle:email')
                ->name('email_soa');
            Route::delete('/{assessment}', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('destroy');
        });

        // ── Settings ──────────────────────────────────────────────────────────
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::patch('/profile', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('profile.update');
            Route::put('/password', fn () => response()->json(['message' => 'Not implemented.'], 501))->name('password.update');
        });
    });
});
