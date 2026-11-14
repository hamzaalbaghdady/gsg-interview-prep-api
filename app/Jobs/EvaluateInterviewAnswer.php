<?php

namespace App\Jobs;

use App\Enums\InterviewAnswerStatus;
use App\Exceptions\AiServiceException;
use App\Models\InterviewAnswer;
use App\Services\Ai\AiFailure;
use App\Services\InterviewReportPreparationService;
use App\Services\PythonInterviewAnswerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateInterviewAnswer implements ShouldQueue
{
    use Queueable;

    // Lock releases do not spend the retry budget; only execution failures do.
    public int $tries = 0;

    public int $maxExceptions = 3;

    public bool $countCrashesAsExceptions = true;

    public int $timeout = 60;

    // Automatic attempts share this run; a manual retry increments it.
    public int $run = 0;

    public function __construct(public int $answerId, int $run = 0)
    {
        $this->run = $run;
    }

    public function backoff(): array
    {
        return [5, 10];
    }

    public function handle(PythonInterviewAnswerService $service, InterviewReportPreparationService $reportPreparation): void
    {
        $answer = InterviewAnswer::query()->where('ai_run', $this->run)->whereIn('evaluation_status', ['pending', 'in_progress', 'completed'])
            ->with('question:id,interview_id,sequence_number')
            ->find($this->answerId);

        if ($answer === null) {
            return;
        }

        // A previous attempt may have saved the score but failed before report preparation.
        if ($answer->evaluation_status === InterviewAnswerStatus::COMPLETED) {
            $reportPreparation->prepareIfReady($answer->question->interview_id);

            return;
        }

        if ($answer->evaluation_status === InterviewAnswerStatus::PENDING) {
            $started = InterviewAnswer::query()->where('ai_run', $this->run)->whereKey($answer->id)
                ->where('evaluation_status', InterviewAnswerStatus::PENDING->value)
                ->update(['evaluation_status' => InterviewAnswerStatus::IN_PROGRESS->value]);

            if ($started === 0) {
                return;
            }
        }

        try {
            $result = $service->evaluateAnswer(
                $answer->question->interview_id,
                $answer->question->sequence_number,
                $answer->answer,
            );
        } catch (AiServiceException $exception) {
            if (! AiFailure::automaticRetry($exception->errorCode)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        InterviewAnswer::query()->where('ai_run', $this->run)->whereKey($answer->id)
            ->where('evaluation_status', InterviewAnswerStatus::IN_PROGRESS->value)
            ->update([
                'evaluation_status' => InterviewAnswerStatus::COMPLETED->value,
                'score' => $result['score'],
                'better_answer' => $result['better_answer'],
            ]);

        // get answer
        $answer = InterviewAnswer::query()->where('ai_run', $this->run)
            ->with('question:id,interview_id,sequence_number')
            ->find($this->answerId);

        if ($answer === null) {
            return;
        }
        // get interview id
        $interviewId = $answer->question->interview_id;

        // Dispatch report generation job if answer evaluation is already completed
        if ($answer->evaluation_status === InterviewAnswerStatus::COMPLETED) {
            $reportPreparation->prepareIfReady($interviewId); // safe on retry
        }
    }

    public function middleware(): array
    {
        // Lock by record, across runs; expire after the job deadline to recover from worker crashes.
        return [(new WithoutOverlapping('InterviewAnswer:'.$this->answerId))
            ->releaseAfter(5)->expireAfter($this->timeout + 5)];
    }

    public function failed(?Throwable $exception = null): void
    {
        // Never let an old failure callback overwrite a completed or newer run.
        $code = AiFailure::code($exception);

        $updated = InterviewAnswer::query()->whereKey($this->answerId)->where('ai_run', $this->run)
            ->whereIn('evaluation_status', ['pending', 'in_progress'])->update([
                'evaluation_status' => 'failed',
                'ai_error_code' => $code,
            ]);

        if ($updated === 0) {
            return;
        }

        Log::error('Interview answer evaluation permanently failed.', [
            'run' => $this->run,
            'answer_id' => $this->answerId,
            'error_code' => $code,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
