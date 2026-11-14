<?php

namespace App\Jobs;

use App\Enums\InterviewStatus;
use App\Exceptions\AiServiceException;
use App\Models\Interview;
use App\Services\Ai\AiFailure;
use App\Services\PythonInterviewService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInterviewQuestion implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    // Lock releases do not spend the retry budget; only execution failures do.
    public int $tries = 0;

    public int $maxExceptions = 3;

    public bool $countCrashesAsExceptions = true;

    public int $timeout = 60;

    // Automatic attempts share this run; a manual retry increments it.
    public int $run = 0;

    public function __construct(public int $interviewId, int $run = 0)
    {
        $this->run = $run;
    }

    /**
     * Define the backoff array sequence for automatic retries.
     */
    public function backoff(): array
    {
        return [5, 10];
    }

    /**
     * Prevents duplicate dispatches of the same unique job
     */
    public function uniqueId(): string
    {
        return $this->interviewId.':'.$this->run;
    }

    /**
     * Execute the queue job.
     */
    public function handle(PythonInterviewService $aiService): void
    {
        // Eager load the required text models
        $interview = Interview::query()->where('ai_run', $this->run)->where('status', InterviewStatus::PREPARING->value)
            ->with(['resume', 'jobDescription'])
            ->find($this->interviewId);

        if ($interview === null) {
            return;
        }

        // Prevent duplicate generation sweeps if already processed by another worker thread
        if ($interview->status !== InterviewStatus::PREPARING || $interview->questions()->exists()) {
            return;
        }

        // call python service
        try {
            $result = $aiService->generateBaseQuestions($interview);
        } catch (AiServiceException $exception) {
            if (! AiFailure::automaticRetry($exception->errorCode)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        // store each question
        DB::transaction(function () use ($interview, $result) {
            // lock interview
            $lockedInterview = Interview::query()->where('ai_run', $this->run)
                ->lockForUpdate()
                ->findOrFail($interview->id);

            // recheck status and questions
            if ($lockedInterview->status !== InterviewStatus::PREPARING) {
                return;
            }

            if ($lockedInterview->questions()->exists()) {
                return;
            }

            foreach ($result['questions'] as $index => $question) {

                $lockedInterview->questions()->create([
                    'sequence_number' => $index + 1, // from 1 to 10
                    'text' => $question['text'],
                    'type' => 'base'
                ]);
            }

            // update interview status
            $lockedInterview->update([
                'status' => InterviewStatus::READY
            ]);
        });
    }

    public function middleware(): array
    {
        // Lock by record, across runs; expire after the job deadline to recover from worker crashes.
        return [(new WithoutOverlapping('Interview:'.$this->interviewId))
            ->releaseAfter(5)->expireAfter($this->timeout + 5)];
    }

    public function failed(?Throwable $exception = null): void
    {
        // Never let an old failure callback overwrite a completed or newer run.
        $code = AiFailure::code($exception);

        $updated = Interview::query()->whereKey($this->interviewId)->where('ai_run', $this->run)
            ->where('status', InterviewStatus::PREPARING->value)->update([
                'status' => 'failed',
                'ai_error_code' => $code,
            ]);

        if ($updated === 0) {
            return;
        }

        Log::error('Asynchronous initial 10-question batch generation permanently failed.', [
            'run' => $this->run,
            'interview_id' => $this->interviewId,
            'error_code' => $code,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
