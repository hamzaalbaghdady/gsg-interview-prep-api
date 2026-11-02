<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GapAnalysisController;
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
});

// 2. The Isolated mock route wrapper
Route::get('/test-roadmap-queue/{id}', function ($id) {
    Http::fake([
        'http://localhost:8000/api/v1/generate-roadmap' => Http::response([
            'roadmaps' => [
                [
                    'skill_id' => 1,
                    'skill_name' => 'laravel',
                    'category' => 'frameworks',
                    'importance' => 1.0,
                    'priority' => 1,
                    'title' => 'Learning roadmap: laravel',
                    'estimated_duration' => '1 week',
                    'steps' => [
                        [
                            'week' => 1,
                            'title' => 'Laravel Essentials',
                            'topics' => ['Routing', 'Eloquent ORM', 'Middlewares'],
                        ],
                    ],
                    'resources' => [],
                ],
                [
                    'skill_id' => 2,
                    'skill_name' => 'mysql',
                    'category' => 'databases',
                    'importance' => 0.8,
                    'priority' => 2,
                    'title' => 'Learning roadmap: mysql',
                    'estimated_duration' => '1 week',
                    'steps' => [
                        [
                            'week' => 1,
                            'title' => 'Database Optimization',
                            'topics' => ['Indexes', 'Foreign Keys', 'Query Optimization'],
                        ],
                    ],
                    'resources' => [
                        [
                            'title' => 'MySQL Documentation',
                            'url' => 'https://mysql.com',
                        ],
                    ],
                ],
            ]

        ], 200)
    ]);

    GenerateSkillRoadmap::dispatchSync((int) $id);

    return response()->json(['message' => 'Synchronous AI Roadmap script compiled and saved into database records safely!']);
});
