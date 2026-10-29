<?php

namespace App\Jobs;

use App\Enums\ParsingStatus;
use App\Models\JobDescription;
use App\Models\Skill;
use App\Services\PythonJobParsingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessJobDescription implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 45;

    public function __construct(public int $jobDescriptionId) {}

    public function backoff(): array
    {
        return [10, 30]; // delays of 10 seconds and 30 seconds for retries
    }

    public function handle(PythonJobParsingService $aiService): void
    {
        // Performance optimization: select only columns required during the sync block
        $job = JobDescription::query()
            ->select(['id', 'parsing_status', 'description'])
            ->findOrFail($this->jobDescriptionId);

        if ($job->parsing_status === ParsingStatus::COMPLETED) {
            return;
        }

        $job->update(['parsing_status' => ParsingStatus::PROCESSING]);

        // Query AI service
        $result = $aiService->extractSkills($job);

        DB::transaction(function () use ($job, $result) {
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

    public function failed(?Throwable $exception): void
    {
        // Log any exception happens here or in the AI service
        Log::error('Job description skill extraction permanently failed.', [
            'job_description_id' => $this->jobDescriptionId,
            'exception_type' => $exception ? $exception::class : null,
            'exception_message' => $exception?->getMessage(),
        ]);

        // Update status to failed
        JobDescription::query()
            ->whereKey($this->jobDescriptionId)
            ->update([
                'parsing_status' => ParsingStatus::FAILED->value,
            ]);
    }
}
