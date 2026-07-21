<?php

use App\Http\Controllers\BankAccountInfoController;
use App\Http\Controllers\FormInputController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PaymentDetailOptionController;
use App\Http\Controllers\StaffInputController;
use App\Http\Controllers\SupportingDocumentController;
use App\Http\Controllers\UacsController;
use Illuminate\Support\Facades\Route;

// Route::inertia('/', 'welcome')->name('home');

// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::inertia('dashboard', 'dashboard')->name('dashboard');
// });

require __DIR__.'/settings.php';


// Public Routes
Route::name('public.')->prefix('public')->group(function () {
    Route::get('/submit', [FormInputController::class, 'create'])->name('submit');
    Route::post('/submit', [FormInputController::class, 'store'])->name('submit.store');
    Route::get('/success/{referenceNumber?}', [FormInputController::class, 'success'])->name('success');
});


// BEFORE:
// Route::name('staff.')->prefix('staff')->middleware(['auth', 'staff'])->group(function () {

// AFTER (Temporarily disabled for development):
Route::name('staff.')->prefix('staff')->middleware([])->group(function () {
    
    // Redirect /staff -> /staff/dashboard
    // Route::redirect('/staff', '/staff/dashboard');

    // Dashboard
    Route::get('/dashboard', [StaffInputController::class, 'index'])->name('dashboard');
    
    // Requests Management
    Route::name('requests.')->prefix('requests')->group(function () {
        Route::get('/', [StaffInputController::class, 'index'])->name('index');
        
        // Non-wildcard routes must reside ABOVE wildcard routes to prevent fallback hijacking
        Route::post('/process', [StaffInputController::class, 'store'])->name('store');
        
        Route::get('/{formInput}/process', [StaffInputController::class, 'create'])->name('process');
        Route::get('/{formInput}', [FormInputController::class, 'show'])->name('show');
        Route::get('/{staffInput}/edit', [StaffInputController::class, 'edit'])->name('edit');
        Route::put('/{staffInput}', [StaffInputController::class, 'update'])->name('update');
    });
    
    // Resource routes for master data
    // Explicit parameter bindings are set here to prevent Laravel's inflector from scrambling singular names!
    Route::resource('memberships', MembershipController::class)
        ->except(['show'])
        ->parameters(['memberships' => 'membership']);

    Route::resource('payment-options', PaymentDetailOptionController::class)
        ->except(['show'])
        ->parameters(['payment-options' => 'paymentOption']); // Resolves camelCase mapping in PaymentDetailOptionController

    Route::resource('bank-accounts', BankAccountInfoController::class)
        ->except(['show'])
        ->parameters(['bank-accounts' => 'bankAccount']);

    Route::resource('uacs', UacsController::class)
        ->except(['show'])
        ->parameters(['uacs' => 'uacs']); // Prevents Laravel from converting singular 'uacs' to 'uac'
    
    // Document routes
    Route::name('documents.')->prefix('documents')->group(function () {
        Route::get('/', [SupportingDocumentController::class, 'index'])->name('index');
        
        // Matches the camelCase parameter exactly to bind directly with SupportingDocumentController
        Route::get('/{supportingDocument}/download', [SupportingDocumentController::class, 'download'])->name('download');
        Route::delete('/{supportingDocument}', [SupportingDocumentController::class, 'destroy'])->name('destroy');
    });
});

// Root redirect
Route::get('/', function () {
    return redirect()->route('public.submit');
});