<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InterviewAnswerStatus;
use App\Enums\InterviewStatus;
use App\Http\Controllers\Controller;
use App\Jobs\EvaluateInterviewAnswer;
use App\Models\Interview;
use App\Models\InterviewAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    EvaluateInterviewAnswer::dispatch($answer->id);
                } catch (Throwable $exception) {
                    report($exception);

                    $answer->newQuery()->whereKey($answer->id)
                        ->where('evaluation_status', InterviewAnswerStatus::PENDING->value)
                        ->update(['evaluation_status' => InterviewAnswerStatus::FAILED->value]);

                    $answer->refresh();
                }
            });

            return $answer;
        });

        return response()->json([
            'data' => [
                'answer_id' => $answer->id,
                'evaluation_status' => $answer->evaluation_status->value,
            ],
        ], $answer->wasRecentlyCreated ? 201 : 200);
    }
}
