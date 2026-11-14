<?php

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Exceptions\AiServiceException;
use App\Models\FinalReport;
use App\Models\Interview;
use App\Services\Ai\AiFailure;
use App\Services\PythonInterviewReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateInterviewReport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Waiting for another worker's lock must not consume the execution failure budget.
    public int $tries = 0;

    public int $maxExceptions = 3;

    public bool $countCrashesAsExceptions = true;

    public int $timeout = 75;

    public int $uniqueFor = 300;

    // Automatic retries keep this run; manual retries start a new one.
    public int $run = 0;

    public function __construct(public int $reportId, int $run = 0)
    {
        $this->run = $run;
    }

    public function uniqueId(): string
    {
        return $this->reportId.':'.$this->run;
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(PythonInterviewReportService $pythonService): void
    {

        // get the report with resume, jobDescription, questions and answers
        $report = FinalReport::query()
            ->where('ai_run', $this->run)
            ->whereIn('status', [ReportStatus::PENDING->value, ReportStatus::PROCESSING->value])
            ->with([
                'interview.resume',
                'interview.jobDescription',
                'interview.questions.answer',
            ])
            ->find($this->reportId);

        if ($report === null) {
            return;
        }

        // update status to processing
        $updated = FinalReport::query()
            ->where('ai_run', $this->run)
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
        try {
            $aiResult = $pythonService->generate($interview);
        } catch (AiServiceException $exception) {
            if (! AiFailure::automaticRetry($exception->errorCode)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        // start a transaction to update report
        DB::transaction(function () use ($report, $aiResult): void {
            $lockedReport = FinalReport::query()
                ->where('ai_run', $this->run)
                ->lockForUpdate()
                ->find($report->id);

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

    public function middleware(): array
    {
        return [(new WithoutOverlapping('FinalReport:'.$this->reportId))
            ->releaseAfter(5)->expireAfter($this->timeout + 5)];
    }

    public function failed(?Throwable $exception): void
    {
        $code = AiFailure::code($exception);

        // A stale callback must not overwrite a newer run or a completed result.
        $updated = FinalReport::query()->whereKey($this->reportId)->where('ai_run', $this->run)
            ->whereIn('status', [ReportStatus::PENDING->value, ReportStatus::PROCESSING->value])
            ->update([
                'status' => ReportStatus::FAILED->value,
                'ai_error_code' => $code,
            ]);

        if ($updated === 0) {
            return;
        }

        Log::error('Interview report generation failed.', [
            'report_id' => $this->reportId,
            'run' => $this->run,
            'error_code' => $code,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
