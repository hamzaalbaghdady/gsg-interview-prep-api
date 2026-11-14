<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreJobDescriptionRequest;
use App\Http\Resources\Api\V1\JobDescriptionResource;
use App\Jobs\ProcessJobDescription;
use App\Models\JobDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class JobDescriptionController extends Controller
{

    /**
     * Display the all user resources.
     */
    public function index(Request $request)
    {
        // Return latest 15 job description without the description part.
        $data = $request->user()
            ->jobDescriptions()
            ->select(['id', 'user_id', 'title', 'company_name', 'parsing_status', 'ai_error_code', 'created_at'])
            ->latest()
            ->paginate($request->query('per_page', 15));

        return JobDescriptionResource::collection($data);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJobDescriptionRequest $request)
    {
        // Validate request
        $validated = $request->validated();

        // Store data
        $user = $request->user();
        $jobDescription = $user->jobDescriptions()->create($validated);

        // Dispatch background processing job safely after the DB commits the record
        DB::afterCommit(function () use ($jobDescription): void {
            try {
                ProcessJobDescription::dispatch($jobDescription->id);
            } catch (Throwable $exception) {
                report($exception);
                $jobDescription->newQuery()->whereKey($jobDescription->id)
                    ->where('ai_run', 0)->where('parsing_status', 'pending')->update([
                        'parsing_status' => 'failed',
                        'ai_error_code' => 'queue_unavailable',
                    ]);
            }
        });

        // Return response
        return response()->json([
            'status' => 'success',
            'message' => 'Job Description created successfully and queued for AI skill extraction.',
            'data' => new JobDescriptionResource($jobDescription->refresh())
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, JobDescription $jobDescription)
    {
        // Check if user owns this resource
        if ($request->user()->id !== $jobDescription->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resource not found.' // 404 is better for security here
            ], 404);
        }
        // EAGER LOAD
        $jobDescription->load('skills');

        return response()->json([
            'status' => 'success',
            'data' => new JobDescriptionResource($jobDescription)
        ]);
    }
}
