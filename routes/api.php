<?php

use App\Http\Controllers\FormInputController;
use App\Http\Controllers\StaffInputController;
use Illuminate\Support\Facades\Route;

// Client routes (no authentication needed for form submission)
Route::prefix('client')->group(function () {
    // Public form submission
    Route::post('/form-input', [FormInputController::class, 'store']);
    Route::get('/form-input/{id}', [FormInputController::class, 'show']);
    
    // Optional: For checking form status
    Route::get('/form-input/{id}/status', [FormInputController::class, 'checkStatus']);
});

// Staff routes (protected with authentication)
Route::prefix('staff')->middleware(['auth:sanctum'])->group(function () {
    // Staff input management
    Route::post('/staff-input', [StaffInputController::class, 'store']);
    Route::put('/staff-input/{id}', [StaffInputController::class, 'update']);
    Route::get('/staff-input/{formInputId}', [StaffInputController::class, 'show']);
    Route::get('/staff-input', [StaffInputController::class, 'index']);
    Route::delete('/staff-input/{id}', [StaffInputController::class, 'destroy']);
    
    // Dashboard endpoints
    Route::get('/pending-forms', [StaffInputController::class, 'pendingForms']);
    Route::get('/processed-forms', [StaffInputController::class, 'processedForms']);
    Route::get('/dashboard/stats', [StaffInputController::class, 'dashboardStats']);
});