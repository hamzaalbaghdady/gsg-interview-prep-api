<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateInterviewAnswer;
use App\Jobs\GenerateInterviewReport;
use App\Jobs\GenerateSkillRoadmap;
use App\Jobs\ProcessInterviewQuestion;
use App\Jobs\ProcessJobDescription;
use App\Jobs\ProcessResume;
use App\Models\GapAnalysis;
use App\Models\Interview;
use App\Models\User;
use App\Services\Ai\AiFailure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiRetryController extends Controller
{
    public function resume(Request $request, int $resume): JsonResponse
    {
        $record = $request->user()->resumes()->findOrFail($resume);

        return $this->retry($record, 'parsing_status', (new ProcessResume($record->id))->onConnection(config('resumes.queue_connection')));
    }

    public function jobDescription(Request $request, int $jobDescription): JsonResponse
    {
        $record = $request->user()->jobDescriptions()->findOrFail($jobDescription);

        return $this->retry($record, 'parsing_status', new ProcessJobDescription($record->id));
    }

    public function roadmap(Request $request, int $analysis): JsonResponse
    {
        $record = GapAnalysis::query()
            ->whereHas('resume', fn ($query) => $query->where('user_id', $request->user()->id))
            ->whereHas('jobDescription', fn ($query) => $query->where('user_id', $request->user()->id))
            ->findOrFail($analysis);

        return $this->retry($record, 'roadmap_status', new GenerateSkillRoadmap($record->id));
    }

    public function interview(Request $request, int $interview): JsonResponse
    {
        $record = $request->user()->interviews()->findOrFail($interview);

        return $this->retry($record, 'status', new ProcessInterviewQuestion($record->id), 'preparing');
    }

    public function answer(Request $request, int $interview, int $question): JsonResponse
    {
        $record = $request->user()->interviews()->findOrFail($interview)
            ->questions()->findOrFail($question)->answer()->firstOrFail();

        return $this->retry($record, 'evaluation_status', new EvaluateInterviewAnswer($record->id));
    }

    public function report(Request $request, int $interview): JsonResponse
    {
        // First-time report creation stays in the existing preparation service.
        $record = $request->user()->interviews()->findOrFail($interview)->report()->firstOrFail();

        return $this->retry($record, 'status', new GenerateInterviewReport($record->id));
    }

    private function retry(Model $record, string $statusColumn, object $job, string $pending = 'pending'): JsonResponse
    {
        $record = DB::transaction(function () use ($record, $statusColumn, $pending, $job): Model {
            // Use the same owner lock as interview creation to preserve one open interview.
            if ($record instanceof Interview) {
                User::query()->whereKey($record->user_id)->lockForUpdate()->firstOrFail();
            }

            // Re-read under the lock: a second retry must see the first request's pending state.
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            abort_unless($record->getRawOriginal($statusColumn) === 'failed', 409, 'Only failed operations can be retried.');

            if ($record instanceof Interview) {
                $open = $record->user->interviews()->whereKeyNot($record->id)
                    ->whereIn('status', ['preparing', 'ready', 'in_progress'])->exists();
                abort_if($open, 409, 'Finish your open interview before retrying this interview.');
                abort_if($record->questions()->exists(), 409, 'This interview already has generated questions.');
            }

            // Each manual retry gets a new run so older queued jobs cannot change this one.
            $record->forceFill([
                $statusColumn => $pending,
                'ai_error_code' => null,
                'ai_run' => ((int) $record->ai_run) + 1,
            ])->save();

            $job->run = $record->ai_run;

            // Workers must not see the job before the status and run number are committed.
            DB::afterCommit(function () use ($record, $statusColumn, $pending, $job): void {
                try {
                    // PendingDispatch applies ShouldBeUnique before pushing interview jobs.
                    dispatch($job);
                } catch (Throwable $exception) {
                    $record->newQuery()->whereKey($record->id)->where('ai_run', $job->run)
                        ->where($statusColumn, $pending)->update([
                            $statusColumn => 'failed',
                            'ai_error_code' => 'queue_unavailable',
                        ]);
                    Log::warning('AI retry could not be queued.', [
                        'job' => $job::class,
                        'record_id' => $record->id,
                        'exception_type' => $exception::class,
                    ]);
                }
            });

            return $record;
        });

        return $this->retryResponse($record, $statusColumn);
    }

    private function retryResponse(Model $record, string $statusColumn): JsonResponse
    {
        $record->refresh();
        $failed = $record->getRawOriginal($statusColumn) === 'failed';

        return response()->json([
            'message' => $failed ? 'Processing could not be restarted.' : 'Processing has been queued again.',
            'data' => [
                'id' => $record->id,
                'status' => $record->getRawOriginal($statusColumn),
                'ai_failure' => AiFailure::details($record, $statusColumn),
            ],
        ], $failed ? 503 : 202);
    }
}
