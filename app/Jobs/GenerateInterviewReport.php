<?php

namespace App\Jobs;

use App\Enums\InterviewStatus;
use App\Enums\ReportStatus;
use App\Models\FinalReport;
use App\Models\Interview;
use App\Services\PythonInterviewReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateInterviewReport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 300;

    public function __construct(public int $reportId) {}

    public function uniqueId(): string
    {
        return (string) $this->reportId;
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(PythonInterviewReportService $pythonService): void
    {

        // get the report with resume, jobDescription, questions and answers
        $report = FinalReport::query()
            ->with([
                'interview.resume',
                'interview.jobDescription',
                'interview.questions.answer',
            ])
            ->findOrFail($this->reportId);

        // check if report is completed or failed
        if (in_array($report->status, [
            ReportStatus::COMPLETED,
            ReportStatus::FAILED,
        ], true)) {
            return;
        }

        // update status to processing
        $updated = FinalReport::query()
            ->whereKey($report->id)
            ->whereIn('status', [
                ReportStatus::PENDING->value,
                ReportStatus::PROCESSING->value,
            ])
            ->update([
                'status' => ReportStatus::PROCESSING->value,
            ]);

        if ($updated === 0) {
            return;
        }

        // get the interview
        $interview = $report->interview;

        // get the result of the AI service
        $aiResult = $pythonService->generate($interview);

        // start a transaction to update report
        DB::transaction(function () use ($report, $interview, $aiResult): void {
            $lockedReport = FinalReport::query()
                ->lockForUpdate()
                ->findOrFail($report->id);

            if ($lockedReport->status !== ReportStatus::PROCESSING) {
                return;
            }

            $lockedReport->fill([
                'summary' => $aiResult['summary'],
                'improvement_areas' => $aiResult['improvement_areas'],
                'dimensions' => $aiResult['dimensions'],
                'discrepancy_flag' => $aiResult['discrepancy_flag'],
                'status' => ReportStatus::COMPLETED,
            ]);

            $lockedReport->save();
        });
    }

    public function failed(?Throwable $exception): void
    {
        // update report if failed
        FinalReport::query()
            ->whereKey($this->reportId)
            ->whereIn('status', [
                ReportStatus::PENDING->value,
                ReportStatus::PROCESSING->value,
            ])
            ->update([
                'status' => ReportStatus::FAILED->value,
            ]);

        // log the exceptions
        Log::error('Interview report generation failed.', [
            'report_id' => $this->reportId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
