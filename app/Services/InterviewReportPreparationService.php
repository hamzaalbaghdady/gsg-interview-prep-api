<?php

namespace App\Services;

use App\Enums\InterviewAnswerStatus;
use App\Enums\InterviewStatus;
use App\Enums\ReportStatus;
use App\Jobs\GenerateInterviewReport;
use App\Models\FinalReport;
use App\Models\Interview;
use Illuminate\Support\Facades\DB;
use Throwable;

class InterviewReportPreparationService
{
    public function __construct(private OverallInterviewScoreService $scoreService) {}

    public function prepareIfReady(int $interviewId): void
    {
        DB::transaction(function () use ($interviewId): void {

            // Lock the interview to prevent multiple report creation.
            $interview = Interview::query()->lockForUpdate()->findOrFail($interviewId);

            // Only generate a report for an completed interview.
            if ($interview->status !== InterviewStatus::COMPLETED) {
                return;
            }

            // Do not create another report.
            if ($interview->report()->exists()) {
                return;
            }

            // Ensure all 10 expected questions exist.
            if ($interview->questions()->count() !== Interview::INITIAL_QUESTION_COUNT) {
                return;
            }

            // Count questions whose answers have completed evaluation.
            $completedEvaluations = $interview->questions()
                ->whereHas('answer', function ($query) {
                    $query->where(
                        'evaluation_status',
                        InterviewAnswerStatus::COMPLETED->value
                    );
                })
                ->count();

            if ($completedEvaluations !== Interview::INITIAL_QUESTION_COUNT) {
                return;
            }

            $questions = $interview->questions()
                ->with('answer')
                ->get();

            // Calcualte the overall score
            $overallScore = $this->scoreService->calculate($questions);

            // Create the report record.
            $report = $interview->report()->create([
                'status' => ReportStatus::PENDING,
                'overall_score' => $overallScore,
            ]);

            // Dispatch report generation only after the transaction commits.
            DB::afterCommit(function () use ($report): void {
                try {
                    GenerateInterviewReport::dispatch($report->id);
                } catch (Throwable $exception) {
                    report($exception);

                    // Prevent a report from remaining pending if dispatch fails.
                    FinalReport::query()
                        ->whereKey($report->id)
                        ->where('status', ReportStatus::PENDING->value)
                        ->update([
                            'status' => ReportStatus::FAILED->value,
                        ]);
                }
            });
        });
    }
}
