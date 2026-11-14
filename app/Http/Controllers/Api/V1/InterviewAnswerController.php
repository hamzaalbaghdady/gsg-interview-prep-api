<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InterviewStatus;
use App\Http\Controllers\Controller;
use App\Jobs\EvaluateInterviewAnswer;
use App\Models\Interview;
use App\Models\InterviewAnswer;
use App\Services\Ai\AiFailure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InterviewAnswerController extends Controller
{
    public function store(Request $request, Interview $interview, string $question): JsonResponse
    {
        abort_unless($request->user()->id === $interview->user_id, 404);

        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:10000'],
        ]);

        $answer = DB::transaction(function () use ($interview, $question, $validated): InterviewAnswer {
            $interview = Interview::query()->lockForUpdate()->findOrFail($interview->id);
            $question = $interview->questions()->findOrFail($question);
            $existingAnswer = $question->answer()->first();

            if ($existingAnswer !== null) {
                abort_unless($existingAnswer->answer === $validated['answer'], 409, 'This question already has an answer.');

                return $existingAnswer;
            }

            abort_unless(
                in_array($interview->status, [InterviewStatus::READY, InterviewStatus::IN_PROGRESS], true),
                409,
                'This interview is not accepting answers.'
            );

            $answer = $question->answer()->create([
                'answer' => $validated['answer'],
            ]);

            if ($interview->status === InterviewStatus::READY) {
                $interview->status = InterviewStatus::IN_PROGRESS;
                $interview->started_at = now();
            }

            if (! $interview->questions()->whereDoesntHave('answer')->exists()) {
                $interview->status = InterviewStatus::COMPLETED;
                $interview->completed_at = now();
            }

            if ($interview->isDirty()) {
                $interview->save();
            }

            DB::afterCommit(function () use ($answer): void {
                try {
                    Bus::dispatch(new EvaluateInterviewAnswer($answer->id));
                } catch (Throwable $exception) {
                    $answer->newQuery()->whereKey($answer->id)->where('ai_run', 0)->where('evaluation_status', 'pending')->update([
                        'evaluation_status' => 'failed',
                        'ai_error_code' => 'queue_unavailable',
                    ]);
                    Log::warning('AI processing could not be queued.', [
                        'record_id' => $answer->id,
                        'exception_type' => $exception::class,
                    ]);
                }
            });

            return $answer;
        });

        $answer->refresh();

        return response()->json([
            'data' => [
                'answer_id' => $answer->id,
                'evaluation_status' => $answer->evaluation_status->value,
                'ai_failure' => AiFailure::details($answer, 'evaluation_status'),
            ],
        ], $answer->wasRecentlyCreated ? 201 : 200);
    }
}
