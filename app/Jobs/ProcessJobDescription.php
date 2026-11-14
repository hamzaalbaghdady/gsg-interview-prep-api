<?php

namespace App\Jobs;

use App\Enums\ParsingStatus;
use App\Exceptions\AiServiceException;
use App\Models\JobDescription;
use App\Models\Skill;
use App\Services\Ai\AiFailure;
use App\Services\PythonJobParsingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessJobDescription implements ShouldQueue
{
    use Queueable;

    // Waiting for another worker's lock must not consume the execution failure budget.
    public int $tries = 0;

    public int $maxExceptions = 3;

    public bool $countCrashesAsExceptions = true;

    public int $timeout = 45;

    // Automatic retries keep this run; manual retries start a new one.
    public int $run = 0;

    public function __construct(public int $jobDescriptionId, int $run = 0)
    {
        $this->run = $run;
    }

    public function backoff(): array
    {
        return [10, 30]; // delays of 10 seconds and 30 seconds for retries
    }

    public function handle(PythonJobParsingService $aiService): void
    {
        // Performance optimization: select only columns required during the sync block
        $job = JobDescription::query()
            ->where('ai_run', $this->run)
            ->whereIn('parsing_status', [ParsingStatus::PENDING->value, ParsingStatus::PROCESSING->value])
            ->select(['id', 'parsing_status', 'description'])
            ->find($this->jobDescriptionId);

        if ($job->parsing_status === ParsingStatus::COMPLETED) {
            return;
        }

        // Recheck after loading so an older job cannot restart a failed or newer run.
        $updated = JobDescription::query()->whereKey($job->id)->where('ai_run', $this->run)
            ->whereIn('parsing_status', [ParsingStatus::PENDING->value, ParsingStatus::PROCESSING->value])
            ->update(['parsing_status' => ParsingStatus::PROCESSING->value]);

        if ($updated === 0) {
            return;
        }

        // Query AI service
        try {
            $result = $aiService->extractSkills($job);
        } catch (AiServiceException $exception) {
            if (! AiFailure::automaticRetry($exception->errorCode)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        DB::transaction(function () use ($job, $result) {
            $job = JobDescription::query()->where('ai_run', $this->run)
                ->lockForUpdate()->find($job->id);

            if ($job === null || $job->parsing_status !== ParsingStatus::PROCESSING) {
                return;
            }

            $links = [];

            // Store all new skills
            foreach ($result['skills'] as $item) {
                $skill = Skill::query()->firstOrCreate([
                    'name' => trim(strtolower($item['name'])),
                    'category' => trim(strtolower($item['category'])),
                ]);

                // Cast and round the metadata metric safely
                $importance = $item['importance'] === null
                    ? null
                    : round((float) $item['importance'], 2);

                $links[$skill->id] = ['importance' => $importance];
            }

            // attach skills to jobs
            $job->skills()->sync($links);

            $job->update(['parsing_status' => ParsingStatus::COMPLETED]);
        });
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('JobDescription:'.$this->jobDescriptionId))
            ->releaseAfter(5)->expireAfter($this->timeout + 5)];
    }

    public function failed(?Throwable $exception): void
    {
        $code = AiFailure::code($exception);

        // A stale callback must not overwrite a newer run or a completed result.
        $updated = JobDescription::query()->whereKey($this->jobDescriptionId)->where('ai_run', $this->run)
            ->whereIn('parsing_status', [ParsingStatus::PENDING->value, ParsingStatus::PROCESSING->value])
            ->update([
                'parsing_status' => ParsingStatus::FAILED->value,
                'ai_error_code' => $code,
            ]);

        if ($updated === 0) {
            return;
        }

        Log::error('Job description skill extraction permanently failed.', [
            'job_description_id' => $this->jobDescriptionId,
            'run' => $this->run,
            'error_code' => $code,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
