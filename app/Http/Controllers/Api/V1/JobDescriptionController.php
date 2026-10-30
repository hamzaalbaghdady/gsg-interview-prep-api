<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreJobDescriptionRequest;
use App\Http\Resources\Api\V1\JobDescriptionResource;
use App\Jobs\ProcessJobDescription;
use App\Models\JobDescription;
use Illuminate\Http\Request;
use App\Services\SkillGapAnalysisService;
use App\Enums\ParsingStatus;


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
            ->select(['id', 'user_id', 'title', 'company_name', 'parsing_status', 'created_at'])
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
        ProcessJobDescription::dispatch($jobDescription->id)->afterCommit();

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


    /**
     * Calculate a real-time Skill Gap Analysis between a specific job and an uploaded resume.
     */
    public function gapAnalysis(Request $request, JobDescription $jobDescription, int $resumeId, SkillGapAnalysisService $analysisService)
    {

        // Check if user owns this resource
        if ($request->user()->id !== $jobDescription->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resource not found.' // 404 is better for security here
            ], 404);
        }

        // Get the user resume
        $resume = $request->user()->resumes()->with('skills')->findOrFail($resumeId);

        // Prevent processing if background AI microservices have not concluded extraction runs
        if ($jobDescription->parsing_status !== ParsingStatus::COMPLETED || $resume->parsing_status !== 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Analysis unavailable while processing resource data queues.'
            ], 422);
        }

        // Eager load relationship data
        $jobDescription->load('skills');

        // Execute calculations
        $analysisResult = $analysisService->calculate($jobDescription, $resume);

        return response()->json([
            'status' => 'success',
            'data' => array_merge([
                'job_title' => $jobDescription->title,
                'resume_title' => $resume->title,
            ], $analysisResult)
        ]);
    }
}
