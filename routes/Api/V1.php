<?php

use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Just for now...
Route::get('/me', [UserController::class, 'show'])->middleware('auth:sanctum');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok, AllIsGood!',
        'version' => 'v1',
    ]);
});
