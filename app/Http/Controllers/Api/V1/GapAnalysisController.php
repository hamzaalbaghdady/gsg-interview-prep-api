<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JobDescription;
use App\Models\GapAnalysis;
use App\Enums\ParsingStatus;
use App\Services\SkillGapAnalysisService;
use App\Jobs\GenerateSkillRoadmap; // Todo
use Illuminate\Http\Request;

class GapAnalysisController extends Controller
{

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

        // If Gap analysis already exists
        $existingAnalysis = GapAnalysis::query()
            ->where('job_description_id', $jobDescription->id)
            ->where('resume_id', $resume->id)
            ->first();

        if ($existingAnalysis !== null) {
            return response()->json([
                'status' => 'success',
                'source' => 'database_cache',
                'data' => array_merge([
                    'job_title' => $jobDescription->title,
                    'resume_title' => $resume->title,
                ], $existingAnalysis->toArray())
            ]);
        }

        // Eager load relationship data
        $jobDescription->load('skills');

        // Execute calculations
        $analysisResult = $analysisService->calculate($jobDescription, $resume);


        // Store data
        $newAnalysis = GapAnalysis::query()->create([
            'resume_id' => $resume->id,
            'job_description_id' => $jobDescription->id,
            'match_percentage' => $analysisResult['match_percentage'],
            'analysis' => $analysisResult, // Array auto-serialized to JSON by Eloquent AsArrayObject cast
            'roadmap_status' => ParsingStatus::PENDING,
            'roadmap' => null
        ]);

        // 7. ASYNC AI ROADMAP: Dispatch the background job safely after database write commits
        GenerateSkillRoadmap::dispatch($newAnalysis->id)->afterCommit();

        return response()->json([
            'status' => 'success',
            'data' => array_merge([
                'job_title' => $jobDescription->title,
                'resume_title' => $resume->title,
            ], $analysisResult)
        ], 201);
    }
}
