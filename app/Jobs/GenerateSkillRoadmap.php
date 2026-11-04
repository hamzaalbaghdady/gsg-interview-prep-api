<?php

namespace App\Jobs;

use App\Enums\ParsingStatus;
use App\Models\GapAnalysis;
use App\Services\PythonRoadmapService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateSkillRoadmap implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 45;

    public function __construct(public int $gapAnalysisId) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(PythonRoadmapService $roadmapService): void
    {
        // get analysis
        $analysis = GapAnalysis::query()
            ->with('jobDescription:id,title')
            ->select([
                'id',
                'job_description_id',
                'roadmap_status',
                'analysis',
            ])
            ->findOrFail($this->gapAnalysisId);

        // check if roadmap is completed
        if ($analysis->roadmap_status === ParsingStatus::COMPLETED) {
            return;
        }
        // update roadmap status
        $analysis->update(['roadmap_status' => ParsingStatus::PROCESSING]);

        // Process request through the Python AI service
        $result = $roadmapService->generate($analysis);

        // update if success
        $analysis->update([
            'roadmap' => $result['roadmaps'],
            'roadmap_status' => ParsingStatus::COMPLETED,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('AI roadmap background compilation permanently failed.', [
            'gap_analysis_id' => $this->gapAnalysisId,
            'exception_message' => $exception?->getMessage(),
        ]);

        GapAnalysis::query()
            ->whereKey($this->gapAnalysisId)
            ->update([
                'roadmap_status' => ParsingStatus::FAILED->value,
            ]);
    }
}
