<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ParsingStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateSkillRoadmap;
use App\Models\GapAnalysis;
use App\Models\JobDescription;
use App\Services\SkillGapAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        // Find or compute the gap analysis
        $analysis = GapAnalysis::query()->firstOrCreate(
            [
                'resume_id' => $resume->id,
                'job_description_id' => $jobDescription->id,
            ],
            function () use ($jobDescription, $resume, $analysisService) {
                // Calculate the gapAnalysis if not
                $jobDescription->load('skills');
                $analysisResult = $analysisService->calculate($jobDescription, $resume);

                return [
                    'match_percentage' => $analysisResult['match_percentage'],
                    'analysis' => $analysisResult, // Array auto-serialized to JSON
                    'roadmap_status' => ParsingStatus::PENDING,
                    'roadmap' => null
                ];
            }
        );

        // Dispatch the roadmap job
        if ($analysis->wasRecentlyCreated) {
            try {
                GenerateSkillRoadmap::dispatch($analysis->id)->afterCommit();
            } catch (Throwable $exception) {
                GapAnalysis::query()
                    ->whereKey($analysis->id)
                    ->update([
                        'roadmap_status' => ParsingStatus::FAILED->value,
                    ]);
            }
        }

        // Return unified response structure
        return response()->json([
            'status' => 'success',
            'source' => $analysis->wasRecentlyCreated ? 'calculated' : 'database_cache',
            'data' => array_merge([
                'job_title' => $jobDescription->title,
                'resume_title' => $resume->title,
            ], $analysis->toArray())
        ], $analysis->wasRecentlyCreated ? 201 : 200);
    }
}
