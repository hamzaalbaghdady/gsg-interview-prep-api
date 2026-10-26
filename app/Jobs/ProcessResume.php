<?php

namespace App\Jobs;

use App\Models\Resume;
use App\Models\Skill;
use App\Services\PythonResumeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessResume implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public int $resumeId) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(PythonResumeService $service): void
    {
        $resume = Resume::query()
            ->select(['id', 'parsing_status', 'file_path'])
            ->find($this->resumeId);

        if ($resume === null || $resume->parsing_status === Resume::COMPLETED) {
            return;
        }

        // Atomically mark as processing only if another job has not already completed it.
        $updated = Resume::query()
            ->whereKey($this->resumeId)
            ->where('parsing_status', '!=', Resume::COMPLETED)
            ->update([
                'parsing_status' => Resume::PROCESSING,
            ]);

        if ($updated === 0) {
            return;
        }

        $result = $service->extract($resume);

        DB::transaction(function () use ($result) {
            $resume = Resume::query()
                ->select(['id', 'parsing_status'])
                ->lockForUpdate()->findOrFail($this->resumeId);

            if ($resume->parsing_status === Resume::COMPLETED) {
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

    public function failed(?Throwable $exception): void
    {
        Resume::query()->whereKey($this->resumeId)
            ->where('parsing_status', '!=', Resume::COMPLETED)
            ->update(['parsing_status' => Resume::FAILED]);

        Log::warning('Resume processing failed.', [
            'resume_id' => $this->resumeId,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
