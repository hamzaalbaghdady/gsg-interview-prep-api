<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GapAnalysisController;
use App\Http\Controllers\Api\V1\InterviewController;
use App\Http\Controllers\Api\V1\InterviewQuestionController;
use App\Http\Controllers\Api\V1\JobDescriptionController;
use App\Http\Controllers\Api\V1\ResumeController;
use App\Http\Controllers\Api\V1\UserController;
use App\Jobs\GenerateSkillRoadmap;
use Illuminate\Support\Facades\Http;
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

    // Resumes routes
    Route::get('/resumes', [ResumeController::class, 'index'])->name('resumes.index');
    Route::post('/resumes', [ResumeController::class, 'store'])->name('resumes.store');
    Route::get('/resumes/{resume}', [ResumeController::class, 'show'])
        ->whereNumber('resume')->name('resumes.show');

    // Job_description routes
    Route::name('api.v1.job-descriptions.')->prefix('job-descriptions')
        ->controller(JobDescriptionController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{job_description}', 'show')->name('show');
        });
    // GapAnalysis
    Route::get(
        'job-descriptions/{job_description}/gap-analysis/{resume_id}',
        [GapAnalysisController::class, 'gapAnalysis']
    )->name('api.v1.gap-analysis');

    // Interviews
    Route::post('/interviews', [InterviewController::class, 'store'])->name('api.v1.interviews.store');

    // Interview Question
    Route::name('api.v1.interviews.questions.')->prefix('interviews/{interview}/questions')
        ->controller(InterviewQuestionController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/next', 'next')->name('next');
        });
});
