<?php

namespace App\Jobs;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Services\PythonInterviewService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInterviewQuestion implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $interviewId) {}

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
        return (string) $this->interviewId;
    }

    /**
     * Execute the queue job.
     */
    public function handle(PythonInterviewService $aiService): void
    {
        // Eager load the required text models
        $interview = Interview::query()
            ->with(['resume', 'jobDescription'])
            ->findOrFail($this->interviewId);

        // Prevent duplicate generation sweeps if already processed by another worker thread
        if ($interview->status !== InterviewStatus::PREPARING || $interview->questions()->exists()) {
            return;
        }

        // call python service
        $result = $aiService->generateBaseQuestions($interview);

        // store each question
        DB::transaction(function () use ($interview, $result) {
            // lock interview
            $lockedInterview = Interview::query()
                ->lockForUpdate()
                ->findOrFail($interview->id);

            // recheck status and questions
            if ($lockedInterview->status !== InterviewStatus::PREPARING) {
                return;
            }

            if ($lockedInterview->questions()->exists()) {
                return;
            }

            foreach ($result['questions'] as $index => $questionText) {

                $lockedInterview->questions()->create([
                    'sequence_number' => $index + 1, // from 1 to 10
                    'text' => $questionText,
                    'type' => 'base'
                ]);
            }

            // update interview status
            $lockedInterview->update([
                'status' => InterviewStatus::READY
            ]);
        });
    }

    /**
     * Handle terminal execution job tracking failures cleanly.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Asynchronous initial 10-question batch generation permanently failed.', [
            'interview_id' => $this->interviewId,
            'error_message' => $exception?->getMessage()
        ]);

        // update interview status
        Interview::query()
            ->whereKey($this->interviewId)
            ->where('status', InterviewStatus::PREPARING->value)
            ->update([
                'status' => InterviewStatus::FAILED->value
            ]);
    }
}
