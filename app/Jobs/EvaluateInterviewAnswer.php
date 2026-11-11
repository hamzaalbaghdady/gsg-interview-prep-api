<?php

namespace App\Jobs;

use App\Enums\InterviewAnswerStatus;
use App\Models\InterviewAnswer;
use App\Services\InterviewReportPreparationService;
use App\Services\PythonInterviewAnswerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvaluateInterviewAnswer implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $answerId) {}

    public function backoff(): array
    {
        return [5, 10];
    }

    public function handle(PythonInterviewAnswerService $service, InterviewReportPreparationService $reportPreparation): void
    {
        $answer = InterviewAnswer::query()
            ->with('question:id,interview_id,sequence_number')
            ->whereIn('evaluation_status', [InterviewAnswerStatus::PENDING->value, InterviewAnswerStatus::IN_PROGRESS->value])
            ->find($this->answerId);

        if ($answer === null) {
            return;
        }

        if ($answer->evaluation_status === InterviewAnswerStatus::PENDING) {
            $started = InterviewAnswer::query()->whereKey($answer->id)
                ->where('evaluation_status', InterviewAnswerStatus::PENDING->value)
                ->update(['evaluation_status' => InterviewAnswerStatus::IN_PROGRESS->value]);

            if ($started === 0) {
                return;
            }
        }

        $result = $service->evaluateAnswer(
            $answer->question->interview_id,
            $answer->question->sequence_number,
            $answer->answer,
        );

        $updated = InterviewAnswer::query()->whereKey($answer->id)
            ->where('evaluation_status', InterviewAnswerStatus::IN_PROGRESS->value)
            ->update([
                'evaluation_status' => InterviewAnswerStatus::COMPLETED->value,
                'score' => $result['score'],
                'better_answer' => $result['better_answer'],
            ]);

        // get answer
        $answer = InterviewAnswer::query()
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
            return;
        }
        if ($answer->evaluation_status === InterviewAnswerStatus::FAILED) {
            return;
        }
        // Dispatch report generation job if answer evaluation status is updated to completed
        if ($updated === 1) {
            $reportPreparation->prepareIfReady($interviewId);
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('Interview answer evaluation permanently failed.', [
            'answer_id' => $this->answerId,
            'error_message' => $exception?->getMessage(),
        ]);

        InterviewAnswer::query()->whereKey($this->answerId)
            ->whereIn('evaluation_status', [InterviewAnswerStatus::PENDING->value, InterviewAnswerStatus::IN_PROGRESS->value])
            ->update(['evaluation_status' => InterviewAnswerStatus::FAILED->value]);
    }
}
