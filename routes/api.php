<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MedBank\PatientController;
use App\Http\Controllers\MedBank\NotifyController;
use App\Http\Controllers\MedBank\QueueController;
use App\Http\Controllers\MedBank\ImportController;
use App\Http\Controllers\MedBank\PharmacistAuthController;
use App\Http\Controllers\MedBank\PharmacistAccountController;

// Public routes
Route::prefix('medbank')->group(function () {

    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/patient/{vn}', [PatientController::class, 'show']);
        Route::post('/notify', [NotifyController::class, 'store']);
    });

    Route::post('/auth/login', [PharmacistAuthController::class, 'login']);
    Route::post('/auth/register', [PharmacistAuthController::class, 'register']);

    // Pharmacist protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [PharmacistAuthController::class, 'logout']);
        Route::get('/patients/search', [PatientController::class, 'search']);
        Route::get('/queue', [QueueController::class, 'index']);
        Route::patch('/queue/{id}/status', [QueueController::class, 'updateStatus']);
        Route::post('/import', [ImportController::class, 'store']);

        // Admin only
        Route::middleware('admin')->group(function () {
            Route::get('/accounts/pending', [PharmacistAccountController::class, 'index']);
            Route::post('/accounts/{id}/approve', [PharmacistAccountController::class, 'approve']);
            Route::post('/accounts/{id}/reject', [PharmacistAccountController::class, 'reject']);
        });
    });
});
