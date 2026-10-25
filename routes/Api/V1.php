<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\JobDescriptionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok, AllIsGood!',
        'version' => 'v1',
    ]);
});

// Login and registration
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated Routes
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [UserController::class, 'show']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Job_description routes
    Route::name('api.v1.job-descriptions.')->prefix('job-descriptions')
        ->controller(JobDescriptionController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{job_description}', 'show')->name('show');
        });
});
