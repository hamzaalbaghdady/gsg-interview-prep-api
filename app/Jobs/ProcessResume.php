<?php

namespace App\Jobs;

use App\Exceptions\AiServiceException;
use App\Models\Resume;
use App\Models\Skill;
use App\Services\Ai\AiFailure;
use App\Services\PythonResumeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessResume implements ShouldQueue
{
    use Queueable;

    // Lock releases do not spend the retry budget; only execution failures do.
    public int $tries = 0;

    public int $maxExceptions = 3;

    public bool $countCrashesAsExceptions = true;

    public int $timeout = 45;

    // Automatic attempts share this run; a manual retry increments it.
    public int $run = 0;

    public function __construct(public int $resumeId, int $run = 0)
    {
        $this->run = $run;
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(PythonResumeService $service): void
    {
        $resume = Resume::query()->where('ai_run', $this->run)->whereIn('parsing_status', ['pending', 'processing'])
            ->select(['id', 'parsing_status', 'file_path'])
            ->find($this->resumeId);

        if ($resume === null) {
            return;
        }

        // Claim only an active run; a failure or newer manual retry may have happened after the read.
        $updated = Resume::query()->where('ai_run', $this->run)
            ->whereKey($this->resumeId)
            ->whereIn('parsing_status', [Resume::PENDING, Resume::PROCESSING])
            ->update([
                'parsing_status' => Resume::PROCESSING,
            ]);

        if ($updated === 0) {
            return;
        }

        try {
            $result = $service->extract($resume);
        } catch (AiServiceException $exception) {
            if (! AiFailure::automaticRetry($exception->errorCode)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        DB::transaction(function () use ($result) {
            $resume = Resume::query()->where('ai_run', $this->run)
                ->select(['id', 'parsing_status'])
                ->lockForUpdate()->findOrFail($this->resumeId);

            if (! in_array($resume->parsing_status, [Resume::PENDING, Resume::PROCESSING], true)) {
                return;
            }

            $links = [];

            foreach ($result['skills'] as $item) {
                $skill = Skill::query()->firstOrCreate([
                    'name' => trim($item['name']),
                    'category' => trim($item['category']),
                ]);

                $score = $item['confidence_score'] === null
                    ? null
                    : round((float) $item['confidence_score'], 2);

                // Repeated skills in an AI response produce one pivot row.
                if (
                    ! array_key_exists($skill->id, $links)
                    || ($score !== null && $score > ($links[$skill->id]['confidence_score'] ?? -1))
                ) {
                    $links[$skill->id] = ['confidence_score' => $score];
                }
            }

            $resume->skills()->sync($links);
            $resume->update([
                'extracted_text' => $result['extracted_text'],
                'parsing_status' => Resume::COMPLETED,
            ]);
        });
    }

    public function middleware(): array
    {
        // Lock by record, across runs; expire after the job deadline to recover from worker crashes.
        return [(new WithoutOverlapping('Resume:'.$this->resumeId))
            ->releaseAfter(5)->expireAfter($this->timeout + 5)];
    }

    public function failed(?Throwable $exception = null): void
    {
        // Never let an old failure callback overwrite a completed or newer run.
        $code = AiFailure::code($exception);

        $updated = Resume::query()->whereKey($this->resumeId)->where('ai_run', $this->run)
            ->whereIn('parsing_status', ['pending', 'processing'])->update([
                'parsing_status' => 'failed',
                'ai_error_code' => $code,
            ]);

        if ($updated === 0) {
            return;
        }

        Log::warning('Resume processing failed.', [
            'run' => $this->run,
            'resume_id' => $this->resumeId,
            'error_code' => $code,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
