<?php

namespace App\Jobs;

use App\Enums\ParsingStatus;
use App\Exceptions\AiServiceException;
use App\Models\GapAnalysis;
use App\Services\Ai\AiFailure;
use App\Services\PythonRoadmapService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateSkillRoadmap implements ShouldQueue
{
    use Queueable;

    // Waiting for another worker's lock must not consume the execution failure budget.
    public int $tries = 0;

    public int $maxExceptions = 3;

    public bool $countCrashesAsExceptions = true;

    public int $timeout = 45;

    // Automatic retries keep this run; manual retries start a new one.
    public int $run = 0;

    public function __construct(public int $gapAnalysisId, int $run = 0)
    {
        $this->run = $run;
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(PythonRoadmapService $roadmapService): void
    {
        // get analysis
        $analysis = GapAnalysis::query()
            ->where('ai_run', $this->run)
            ->whereIn('roadmap_status', [ParsingStatus::PENDING->value, ParsingStatus::PROCESSING->value])
            ->with('jobDescription:id,title')
            ->select([
                'id',
                'job_description_id',
                'roadmap_status',
                'analysis',
            ])
            ->find($this->gapAnalysisId);

        // check if roadmap is completed
        if ($analysis === null) {
            return;
        }
        // update roadmap status
        // Recheck after loading so an older job cannot restart a failed or newer run.
        $updated = GapAnalysis::query()->whereKey($analysis->id)->where('ai_run', $this->run)
            ->whereIn('roadmap_status', [ParsingStatus::PENDING->value, ParsingStatus::PROCESSING->value])
            ->update(['roadmap_status' => ParsingStatus::PROCESSING->value]);

        if ($updated === 0) {
            return;
        }

        // Process request through the Python AI service
        try {
            $result = $roadmapService->generate($analysis);
        } catch (AiServiceException $exception) {
            if (! AiFailure::automaticRetry($exception->errorCode)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        // Save through the existing casts, after checking that this run is still current.
        DB::transaction(function () use ($analysis, $result): void {
            $analysis = GapAnalysis::query()->where('ai_run', $this->run)
                ->lockForUpdate()->find($analysis->id);

            if ($analysis === null || $analysis->roadmap_status !== ParsingStatus::PROCESSING) {
                return;
            }

            $analysis->update([
                'roadmap' => $result['roadmaps'],
                'roadmap_status' => ParsingStatus::COMPLETED,
            ]);
        });
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('GapAnalysis:'.$this->gapAnalysisId))
            ->releaseAfter(5)->expireAfter($this->timeout + 5)];
    }

    public function failed(?Throwable $exception): void
    {
        $code = AiFailure::code($exception);

        // A stale callback must not overwrite a newer run or a completed result.
        $updated = GapAnalysis::query()->whereKey($this->gapAnalysisId)->where('ai_run', $this->run)
            ->whereIn('roadmap_status', [ParsingStatus::PENDING->value, ParsingStatus::PROCESSING->value])
            ->update([
                'roadmap_status' => ParsingStatus::FAILED->value,
                'ai_error_code' => $code,
            ]);

        if ($updated === 0) {
            return;
        }

        Log::error('AI roadmap background compilation permanently failed.', [
            'gap_analysis_id' => $this->gapAnalysisId,
            'run' => $this->run,
            'error_code' => $code,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
